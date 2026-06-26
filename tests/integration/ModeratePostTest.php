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
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;

class ModeratePostTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            User::class => [
                ['id' => 8, 'username' => 'user8', 'email' => 'user8@machine.local', 'is_email_confirmed' => 1],
                ['id' => 9, 'username' => 'user9', 'email' => 'user9@machine.local', 'is_email_confirmed' => 1],
                ['id' => 10, 'username' => 'user10', 'email' => 'user10@machine.local', 'is_email_confirmed' => 1],
                ['id' => 11, 'username' => 'user11', 'email' => 'user11@machine.local', 'is_email_confirmed' => 1],
                ['id' => 12, 'username' => 'user12', 'email' => 'user12@machine.local', 'is_email_confirmed' => 1],
                ['id' => 13, 'username' => 'user13', 'email' => 'user13@machine.local', 'is_email_confirmed' => 1],
            ],
            Post::class => [
                // Existing post to test edits
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Clean post</p></t>', 'is_approved' => 1, 'number' => 2, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                // User 10: unapproved post to test approval clearing logs
                ['id' => 3, 'discussion_id' => 1, 'user_id' => 10, 'type' => 'comment', 'content' => '<t><p>Post to approve</p></t>', 'is_approved' => 0, 'number' => 3, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                // User 11: post that already has an autoMod flag
                ['id' => 4, 'discussion_id' => 1, 'user_id' => 11, 'type' => 'comment', 'content' => '<t><p>Post with flag</p></t>', 'is_approved' => 0, 'number' => 4, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                // User 2: unapproved post without a flag
                ['id' => 5, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Unapproved but clean</p></t>', 'is_approved' => 0, 'number' => 5, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
            ],
            Flag::class => [
                ['id' => 1, 'post_id' => 4, 'type' => 'autoMod', 'user_id' => null, 'reason' => null, 'reason_detail' => 'Matched custom: existing', 'created_at' => Carbon::now()->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Both Enabled',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'OR',
                        'left' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['word_both'], 'scan_all' => true]
                        ],
                        'right' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['apple'], 'scan_all' => true]
                        ]
                    ]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'flag_message' => 'Matched custom: {{matched_word}}',
                    'evaluate_all_rules' => 1,
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'evasion_active' => 1,
                    'evasion_threshold' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Flag Only',
                    'priority' => 1,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['word_flag']]
                    ]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 3,
                    'name' => 'Approval Only',
                    'priority' => 2,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['word_approval']]
                    ]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 4,
                    'name' => 'Inactive Evasion',
                    'priority' => 3,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['inactive_word']
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 0, // INACTIVE!
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'evasion_active' => 1, // BUT EVASION ACTIVE
                    'evasion_timeout' => 15,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 5,
                    'name' => 'HTML Decode Test',
                    'priority' => 4,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['AT&T']]
                    ]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'flag_message' => 'Brand: {{matched_word}}',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 6,
                    'name' => 'High Threshold',
                    'priority' => 5,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['high_threshold_word']]
                    ]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'evasion_active' => 1,
                    'evasion_threshold' => 3,
                    'evasion_timeout' => 15,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ],
            // For evasion tests
            'filter_rule_block_logs' => [
                [
                    'id' => 1,
                    'user_id' => 7,
                    'ruleset_id' => 1,
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(), // 2 mins ago (within default 5 min window)
                ],
                [
                    'id' => 2,
                    'user_id' => 8,
                    'ruleset_id' => 1,
                    'created_at' => Carbon::now()->subMinutes(10)->toDateTimeString(), // 10 mins ago (outside default 5 min window)
                ],
                [
                    'id' => 3,
                    'user_id' => 9,
                    'ruleset_id' => 4, // Inactive ruleset
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(), // 2 mins ago
                ],
                [
                    'id' => 4,
                    'user_id' => 10,
                    'ruleset_id' => 1,
                    'is_cleared' => 0,
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
                ],
                [
                    'id' => 5,
                    'user_id' => 12,
                    'ruleset_id' => 6,
                    'is_cleared' => 0,
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
                ],
                [
                    'id' => 6,
                    'user_id' => 12,
                    'ruleset_id' => 6,
                    'is_cleared' => 0,
                    'created_at' => Carbon::now()->subMinutes(1)->toDateTimeString(),
                ],
                [
                    'id' => 7,
                    'user_id' => 13,
                    'ruleset_id' => 6,
                    'is_cleared' => 0,
                    'created_at' => Carbon::now()->subMinutes(3)->toDateTimeString(),
                ],
                [
                    'id' => 8,
                    'user_id' => 13,
                    'ruleset_id' => 6,
                    'is_cleared' => 0,
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
                ],
                [
                    'id' => 9,
                    'user_id' => 13,
                    'ruleset_id' => 6,
                    'is_cleared' => 0,
                    'created_at' => Carbon::now()->subMinutes(1)->toDateTimeString(),
                ]
            ]
        ]);
    }

    #[Test]
    public function posting_clean_content_publishes_normally()
    {
        $response = $this->submitReply('This is a completely clean post.', 8);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Clean post should be approved');

        $flag = $this->database()->table('flags')->where('post_id', $postId)->first();
        $this->assertNull($flag, 'Clean post should not have any flags');
    }

    #[Test]
    public function posting_triggers_both_approval_and_flag()
    {
        // Notice we include both words from the OR ruleset 1
        $response = $this->submitReply('This contains word_both and apple.', 3);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Assert is_approved is 0
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved (0)');

        // Assert flag is created and type is 'autoMod'
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'autoMod flag should be created');

        // Assert the custom aggregated flag message!
        $this->assertEquals('Matched custom: word_both, apple', $flag->reason_detail);
    }

    #[Test]
    public function posting_triggers_flag_only()
    {
        $response = $this->submitReply('This contains word_flag which only flags.', 4);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Post should NOT be held for approval
        $this->assertEquals(1, $post->is_approved, 'Post should remain approved');

        // Flag should be created as 'autoMod'
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'autoMod flag should be created');
    }

    #[Test]
    public function posting_triggers_approval_only_without_flag()
    {
        $response = $this->submitReply('This contains word_approval which only approves.', 5);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Assert is_approved is 0
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved (0)');

        // Assert NO flag is created
        $flag = $this->database()->table('flags')->where('post_id', $postId)->first();
        $this->assertNull($flag, 'No flag should be created for approval-only ruleset');
    }

    #[Test]
    public function editing_clean_post_to_restricted_word_triggers_moderation()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/2', [
                'authenticatedAs' => 2, // It's okay to reuse 2 here, it's a PATCH
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'I edited this to include word_both.'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $post = $this->database()->table('posts')->where('id', 2)->first();
        $this->assertEquals(0, $post->is_approved, 'Edited post should become unapproved');

        $flag = $this->database()->table('flags')->where('post_id', 2)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'Flag should be created for edited post');
    }

    #[Test]
    public function starting_discussion_with_restricted_word_hides_discussion()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 6,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'title' => 'New Discussion',
                            'content' => 'This contains word_both.'
                        ],
                        'relationships' => [
                            'tags' => ['data' => [['type' => 'tags', 'id' => '1']]]
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $discussionId = Arr::get($body, 'data.id');

        $postId = null;
        foreach (Arr::get($body, 'included', []) as $included) {
            if ($included['type'] === 'posts') {
                $postId = $included['id'];
                break;
            }
        }

        $discussion = $this->database()->table('discussions')->where('id', $discussionId)->first();
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(0, $post->is_approved, 'First post should be unapproved');
        $this->assertEquals(0, $discussion->is_approved, 'Discussion should be unapproved');
    }

    #[Test]
    public function evasion_detection_forces_flag_and_approval_despite_clean_content()
    {
        // Notice we are sending completely CLEAN content, but the user is in the filter_rule_block_logs (within 15 mins)
        $response = $this->submitReply('I promise this is a completely clean and nice post without any bad words.', 7);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Evasion should force the post into approval queue
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved due to evasion');

        // Evasion should force the creation of an autoMod flag
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'autoMod flag should be created due to evasion');

        $this->assertStringContainsString('evasion', $flag->reason_detail, 'Flag reason should mention filter evasion');
    }

    #[Test]
    public function evasion_detection_ignores_expired_timeout()
    {
        // User 8 was blocked 10 mins ago, but default global timeout is 5 mins.
        $response = $this->submitReply('I promise this is a clean post.', 8);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Post should be approved because evasion timeout expired');
    }

    #[Test]
    public function evasion_detection_ignores_inactive_rulesets()
    {
        // User 9 was blocked 2 mins ago by Ruleset 4. Ruleset 4 is inactive, so evasion should not trigger.
        $response = $this->submitReply('I promise this is a clean post.', 9);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Post should be approved because evasion ruleset is inactive');
    }

    #[Test]
    public function approving_a_post_clears_user_evasion_logs()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/3', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'isApproved' => true
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $log = $this->database()->table('filter_rule_block_logs')->where('id', 4)->first();
        $this->assertEquals(1, $log->is_cleared, 'User block logs should be cleared when their post is approved');
    }

    #[Test]
    public function hiding_or_recovering_post_does_not_trigger_moderation()
    {
        $response = $this->submitReply('This contains word_flag which only flags.', 2);
        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');

        $this->send(
            $this->request('PATCH', "/api/posts/$postId", [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'isHidden' => true
                        ]
                    ]
                ]
            ])
        );

        $this->send(
            $this->request('PATCH', "/api/posts/$postId", [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'isHidden' => false
                        ]
                    ]
                ]
            ])
        );

        $flagsCount = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->count();
        $this->assertEquals(1, $flagsCount, 'Hiding/recovering post should not create duplicate moderation flags');
    }

    #[Test]
    public function editing_post_with_existing_automod_flag_does_not_duplicate_flag()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/4', [
                'authenticatedAs' => 11,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'I edited this to include word_flag again.'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $flagsCount = $this->database()->table('flags')->where('post_id', 4)->where('type', 'autoMod')->count();
        $this->assertEquals(1, $flagsCount, 'Editing a post that already has an autoMod flag should not duplicate it');
    }

    #[Test]
    public function editing_unapproved_post_adds_automod_flag()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/5', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'I edited this to include word_both.'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $post = $this->database()->table('posts')->where('id', 5)->first();
        $this->assertEquals(0, $post->is_approved, 'Post should still be unapproved');

        $flag = $this->database()->table('flags')->where('post_id', 5)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'An autoMod flag should be added when editing an unapproved post to contain forbidden words');
    }

    #[Test]
    public function flag_message_decodes_html_entities()
    {
        $response = $this->submitReply('I use AT&T.', 2);
        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');

        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag);

        $this->assertEquals('Brand: AT&T', $flag->reason_detail, 'Flag reason detail should decode HTML entities like &amp;');
    }

    #[Test]
    public function evasion_detection_requires_threshold_to_be_met()
    {
        // User 12 has 2 block logs for Ruleset 6 (Threshold is 3).
        $response = $this->submitReply('I promise this is a clean post.', 12);
        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Post should be approved because evasion threshold is not met (2 < 3)');
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNull($flag, 'No flag should be created because evasion threshold is not met');
    }

    #[Test]
    public function evasion_detection_triggers_when_threshold_is_met()
    {
        // User 13 has 3 block logs for Ruleset 6 (Threshold is 3).
        $response = $this->submitReply('I promise this is a clean post.', 13);
        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved because evasion threshold is met (3 == 3)');
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'Flag should be created because evasion threshold is met');
    }
}
