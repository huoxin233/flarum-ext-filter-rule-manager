<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Tests\integration;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;

class DifferentialEvaluationTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'discussions' => [
                ['id' => 1, 'title' => 'Clean Title', 'user_id' => 2, 'comment_count' => 3, 'participant_count' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>This contains blockword</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>This contains blockword</p></t>', 'is_approved' => 1, 'number' => 2, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                ['id' => 3, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>This contains blockword</p></t>', 'is_approved' => 1, 'number' => 3, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Default Ruleset (DE Enabled)',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule',
                        'provider' => 'builtin',
                        'ruleType' => 'contains_word',
                        'operator' => 'EQUALS',
                        'value' => ['words' => ['blockword']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked',
                    'is_active' => 1,
                    'strict_edit' => null, // Inherit global (false by default)
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Strict Ruleset (DE Disabled)',
                    'priority' => 10,
                    'compiled_ast' => json_encode([
                        'type' => 'rule',
                        'provider' => 'builtin',
                        'ruleType' => 'contains_word',
                        'operator' => 'EQUALS',
                        'value' => ['words' => ['blockword']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked Strict',
                    'is_active' => 1,
                    'strict_edit' => 1, // Force disabled DE
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);
    }

    /** @test */
    public function grandfathered_violation_can_be_edited_if_differential_evaluation_is_active()
    {
        // Deactivate the strict ruleset so only the default DE ruleset applies
        $this->database()->table('filter_rulesets')->where('id', 2)->update(['is_active' => 0]);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'This contains blockword and a harmless typo fix.'
                        ]
                    ]
                ]
            ])
        );

        // Should be allowed because the violation count (1) didn't change
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function adding_new_violations_to_grandfathered_post_is_blocked()
    {
        // Deactivate the strict ruleset
        $this->database()->table('filter_rulesets')->where('id', 2)->update(['is_active' => 0]);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Now contains 'blockword' twice
                            'content' => 'This contains blockword and another blockword.'
                        ]
                    ]
                ]
            ])
        );

        // DE should reject this because the violation state hash (__count) changed
        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function strict_edit_ruleset_blocks_harmless_edits_on_grandfathered_posts()
    {
        // Here, both rulesets are active. The DE ruleset passes, but the Strict ruleset blocks.
        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'This contains blockword and a harmless typo fix.'
                        ]
                    ]
                ]
            ])
        );

        // The Strict ruleset doesn't run DE, so it sees the violation and blocks immediately
        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function global_strict_edit_evaluation_setting_overrides_null_ruleset_inheritance()
    {
        // Deactivate the explicitly strict ruleset
        $this->database()->table('filter_rulesets')->where('id', 2)->update(['is_active' => 0]);
        // Enable global strict edit using the repository to ensure the array cache is updated!
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('huoxin-filter-rule-manager.strict_edit_evaluation', '1');

        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'This contains blockword and a harmless typo fix.'
                        ]
                    ]
                ]
            ])
        );

        // Even though Ruleset 1 has strict_edit=null, the global setting is true, so DE is disabled
        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function editing_discussion_title_only_does_not_trigger_content_violations()
    {
        // Deactivate the strict ruleset
        $this->database()->table('filter_rulesets')->where('id', 2)->update(['is_active' => 0]);

        // Change the title of the discussion. The first post contains 'blockword',
        // but because we are only editing the title, $onlyField = 'title' isolates the evaluation.
        $response = $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'title' => 'This is a new clean title'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), 'Title-only edit should ignore grandfathered content violations');
    }

    /** @test */
    public function editing_post_content_only_does_not_trigger_title_violations()
    {
        // Change the discussion title directly in DB to contain the blockword
        $this->database()->table('discussions')->where('id', 1)->update(['title' => 'Title with blockword']);

        // Deactivate the strict ruleset
        $this->database()->table('filter_rulesets')->where('id', 2)->update(['is_active' => 0]);
        // Also enable evaluate_title on the default ruleset
        $this->database()->table('filter_rulesets')->where('id', 1)->update(['evaluate_title' => 1]);

        // Edit the post content to be completely clean.
        // Because we are only editing content, $onlyField = 'content' isolates the evaluation,
        // and it ignores the grandfathered violation sitting in the title.
        $response = $this->send(
            $this->request('PATCH', '/api/posts/3', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'Completely clean content.'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), 'Content-only edit should ignore grandfathered title violations');
    }

    /** @test */
    public function partial_match_edited_to_full_match_is_blocked()
    {
        // Deactivate the strict ruleset
        $this->database()->table('filter_rulesets')->where('id', 2)->update(['is_active' => 0]);

        // Post 3 starts with the word "block" (which is only a partial match for "blockword", so it's clean)
        $this->database()->table('posts')->where('id', 3)->update(['content' => '<t><p>This contains just the word block</p></t>']);

        // The user edits the post, completing the word to "blockword"
        $response = $this->send(
            $this->request('PATCH', '/api/posts/3', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'This contains the word blockword'
                        ]
                    ]
                ]
            ])
        );

        // The engine correctly sees the __count of "blockword" go from 0 to 1, and blocks it.
        $this->assertEquals(422, $response->getStatusCode(), 'Changing a partial word to a full blocked word should be blocked');
    }

    private function setupWordCountTest(string $content): int
    {
        // Deactivate other rulesets to isolate this test
        $this->database()->table('filter_rulesets')->update(['is_active' => 0]);

        // Insert word count ruleset (max 5 words)
        $this->database()->table('filter_rulesets')->insert([
            'id' => 3,
            'name' => 'Word Count Max 5',
            'priority' => 20,
            'compiled_ast' => json_encode([
                'type' => 'rule',
                'provider' => 'builtin',
                'ruleType' => 'word_count',
                'operator' => 'EQUALS',
                'value' => ['max' => 5]
            ]),
            'intervention_type' => 'block',
            'display_mode' => 'banner',
            'scope_type' => 'global',
            'message' => 'Too long',
            'is_active' => 1,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);

        // Insert grandfathered post
        $postId = 10;
        $this->database()->table('posts')->insert([
            'id' => $postId,
            'discussion_id' => 1,
            'user_id' => 2,
            'type' => 'comment',
            'content' => '<t><p>'.$content.'</p></t>',
            'is_approved' => 1,
            'number' => 10,
            'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()
        ]);

        return $postId;
    }

    /** @test */
    public function word_count_grandfathered_edit_with_same_count_is_allowed()
    {
        // Grandfathered post with 7 words (limit is 5)
        $postId = $this->setupWordCountTest('One two three four five six seven');

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Edited to still have exactly 7 words, just different words
                            'content' => 'Apple banana orange grape pear peach plum'
                        ]
                    ]
                ]
            ])
        );

        // DE sees the token ['word_count' => '7'] didn't change, so it allows the edit
        $this->assertEquals(200, $response->getStatusCode(), 'Editing words but keeping the exact same count should be allowed');
    }

    /** @test */
    public function word_count_grandfathered_edit_increasing_count_is_blocked()
    {
        // Grandfathered post with 7 words (limit is 5)
        $postId = $this->setupWordCountTest('One two three four five six seven');

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Edited to 8 words
                            'content' => 'One two three four five six seven eight'
                        ]
                    ]
                ]
            ])
        );

        // DE sees token change from ['word_count' => '7'] to ['word_count' => '8'] and blocks
        $this->assertEquals(422, $response->getStatusCode(), 'Increasing the word count of a grandfathered post should be blocked');
    }

    /** @test */
    public function word_count_grandfathered_edit_decreasing_count_but_still_violating_is_blocked()
    {
        // Grandfathered post with 7 words (limit is 5)
        $postId = $this->setupWordCountTest('One two three four five six seven');

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Edited to 6 words (still violates max 5 limit)
                            'content' => 'One two three four five six'
                        ]
                    ]
                ]
            ])
        );

        // DE sees token change from ['word_count' => '7'] to ['word_count' => '6'].
        // Because the token changed, DE does NOT approve the edit. The rule evaluates normally,
        // sees 6 > 5, and blocks it. To fix the post, the user MUST bring it down to 5 or fewer.
        $this->assertEquals(422, $response->getStatusCode(), 'Decreasing but still violating count breaks DE and triggers block');
    }

    private function setupRegexTest(string $content, bool $scanAll = false): int
    {
        $this->database()->table('filter_rulesets')->update(['is_active' => 0]);

        $this->database()->table('filter_rulesets')->insert([
            'id' => 4,
            'name' => 'Regex Rule',
            'priority' => 30,
            'compiled_ast' => json_encode([
                'type' => 'rule',
                'provider' => 'builtin',
                'ruleType' => 'regex',
                'operator' => 'EQUALS',
                'value' => [
                    'patterns' => ['/[0-9]{4}-[0-9]{4}/', '/badword/'],
                    'scan_all' => $scanAll
                ]
            ]),
            'intervention_type' => 'block',
            'display_mode' => 'banner',
            'scope_type' => 'global',
            'message' => 'Blocked regex',
            'is_active' => 1,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);

        $postId = 11;
        $this->database()->table('posts')->insert([
            'id' => $postId,
            'discussion_id' => 1,
            'user_id' => 2,
            'type' => 'comment',
            'content' => '<t><p>'.$content.'</p></t>',
            'is_approved' => 1,
            'number' => 11,
            'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()
        ]);

        return $postId;
    }

    /** @test */
    public function regex_grandfathered_edit_is_allowed()
    {
        // Contains a phone number pattern match
        $postId = $this->setupRegexTest('Call me at 5555-1234');

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'Call me at 5555-1234 please'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), 'Harmless edit to regex grandfathered post is allowed');
    }

    /** @test */
    public function scan_all_disabled_allows_swapping_violations()
    {
        // scanAll = false. Match is "5555-1234", count = 1.
        $postId = $this->setupRegexTest('Call me at 5555-1234', false);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Swap one violation (phone number) for another configured violation (badword)
                            'content' => 'This is a badword'
                        ]
                    ]
                ]
            ])
        );

        // Swapping to a different pattern changes the token, triggering block
        $this->assertEquals(422, $response->getStatusCode(), 'Swapping to a DIFFERENT regex pattern changes the token, triggering block');
    }

    /** @test */
    public function scan_all_enabled_strictly_enforces_all_matched_tokens()
    {
        // scanAll = true. Matches both the number AND badword.
        $postId = $this->setupRegexTest('Call me at 5555-1234 badword', true);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Remove one violation, add another of the same type
                            'content' => 'Call me at 9999-8888 badword'
                        ]
                    ]
                ]
            ])
        );

        // Tokens change from ['matched_string' => '5555-1234, badword'] to ['matched_string' => '9999-8888, badword']
        $this->assertEquals(422, $response->getStatusCode(), 'Scan All strictly blocks any change to the array of matched strings');
    }

    private function setupContextualTest(): int
    {
        $this->database()->table('filter_rulesets')->update(['is_active' => 0]);

        $this->database()->table('filter_rulesets')->insert([
            'id' => 5,
            'name' => 'Newbie Block',
            'priority' => 40,
            'compiled_ast' => json_encode([
                'type' => 'rule',
                'provider' => 'builtin',
                'ruleType' => 'post_count',
                'operator' => 'EQUALS',
                'value' => ['max' => 5] // Block if user has <= 5 posts
            ]),
            'intervention_type' => 'block',
            'display_mode' => 'banner',
            'scope_type' => 'global',
            'message' => 'Newbies cannot edit',
            'is_active' => 1,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);

        $postId = 12;
        $this->database()->table('posts')->insert([
            'id' => $postId,
            'discussion_id' => 1,
            'user_id' => 2,
            'type' => 'comment',
            'content' => '<t><p>This is a completely clean post.</p></t>',
            'is_approved' => 1,
            'number' => 12,
            'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()
        ]);

        return $postId;
    }

    /** @test */
    public function contextual_demotion_allows_grandfathered_edits_via_differential_evaluation()
    {
        $postId = $this->setupContextualTest();

        // Let's artificially set the user's post count to 2.
        // Even though this post was created when they were clean, they are NOW in violation
        // of the contextual rule because their demographic state changed.
        $this->database()->table('users')->where('id', 2)->update(['comment_count' => 2]);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            // Harmless typo fix
                            'content' => 'This is a completely clean post (fixed typo).'
                        ]
                    ]
                ]
            ])
        );

        // This is a fascinating edge case!
        // 1. New content evaluates and triggers the `post_count` rule (returns ['post_count' => 2]).
        // 2. Differential Evaluation checks the OLD content.
        // 3. The OLD content ALSO triggers the rule because the rule checks the user's CURRENT post count (which is 2).
        // 4. Old tokens (['post_count' => 2]) === New tokens (['post_count' => 2]).
        // 5. DE approves the edit! The user is not punished for fixing a typo just because they were demoted.
        $this->assertEquals(200, $response->getStatusCode(), 'Contextual demotion should not prevent users from fixing typos in old posts');
    }
}
