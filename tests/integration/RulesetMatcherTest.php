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


class RulesetMatcherTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'users' => [
                ['id' => 3, 'username' => 'normaluser', 'email' => 'normal@example.com', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'moderator', 'email' => 'mod@example.com', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 4, 'group_id' => 4], // Moderator group
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Clean Discussion', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 3, 'first_post_id' => 1, 'comment_count' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Bypass Group Ruleset',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['forbidden']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'bypass_group_ids' => json_encode([4]), // Group 4 bypasses this
                    'message' => 'Blocked for normal users',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Cascade Priority 1 (Silent)',
                    'priority' => 1,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['cascade_test']]
                    ]),
                    'intervention_type' => 'silent',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'block_cascade' => 1, // Stop subsequent evaluation!
                    'auto_flag' => 1,
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 3,
                    'name' => 'Cascade Priority 2 (Block)',
                    'priority' => 2,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['cascade_test']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'This should never be reached if Priority 1 cascades',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 4,
                    'name' => 'Title Evaluation Ruleset',
                    'priority' => 3,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['scam']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'evaluate_title' => 1, // Evaluate title!
                    'message' => 'Scam detected in title or content',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);
    }

    /** @test */
    public function bypass_groups_ignores_ruleset()
    {
        // Normal user (User 3) gets blocked by the word 'forbidden'
        $response1 = $this->submitReply('This is a forbidden word.', 3, 1);
        $this->assertEquals(422, $response1->getStatusCode());
        $body1 = json_decode($response1->getBody()->getContents(), true);
        $this->assertEquals('Blocked for normal users', $body1['errors'][0]['detail']);

        // Moderator (User 4, Group 4) successfully posts the same content
        $response2 = $this->submitReply('This is a forbidden word.', 4, 1);
        $this->assertEquals(201, $response2->getStatusCode());
    }

    /** @test */
    public function block_cascade_stops_subsequent_rulesets()
    {
        // 'cascade_test' hits Ruleset 2 (Silent, Auto-Flag, Block Cascade) and Ruleset 3 (Block).
        // Since Ruleset 2 cascades, Ruleset 3 should NEVER run.
        // Therefore, the post should succeed (201) and just be flagged, rather than being blocked (422).
        $response = $this->submitReply('This is a cascade_test post.', 3, 1);

        // Assert the post was successfully created (not blocked by Ruleset 3)
        $this->assertEquals(201, $response->getStatusCode());

        $postId = \Illuminate\Support\Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');

        // Verify that the silent flag from Ruleset 2 was created
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'Ruleset 2 (Priority 1) should have silently flagged the post.');
    }

    /** @test */
    public function evaluate_title_prepends_to_content_for_first_post()
    {
        // Create a new discussion where the content is clean, but the TITLE has 'scam'
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'This is a scam',
                            'content' => 'The content is perfectly clean.'
                        ],
                        'relationships' => [
                            'tags' => [
                                'data' => [
                                    ['type' => 'tags', 'id' => '1']
                                ]
                            ]
                        ]
                    ]
                ]
            ])
        );

        // It should be blocked because evaluate_title is true on Ruleset 4
        $this->assertEquals(422, $response->getStatusCode());
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Scam detected in title or content', $body['errors'][0]['detail']);
    }
}
