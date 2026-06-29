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

class BuiltinRulesTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Word Count Max 5',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'word_count', 'operator' => 'EQUALS', 'value' => ['max' => 5]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Post exceeds max word count of 5',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Group 4 Only Block',
                    'priority' => 1,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'group', 'operator' => 'EQUALS', 'value' => ['groupIds' => [4]]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked for Moderators only',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4] // Make User 3 a Moderator (Group 4)
            ]
        ]);
    }

    /** @test */
    public function word_count_blocks_posts_correctly()
    {
        // 5 words -> Should pass
        $response = $this->submitReply('One two three four five.', 2);
        $this->assertEquals(201, $response->getStatusCode(), 'Should allow post with 5 words');

        // 6 words -> Should block
        // Use user 5 to avoid Flarum's floodgate rate limiting (429) for consecutive posts
        $response2 = $this->submitReply('One two three four five six.', 5);
        $this->assertEquals(422, $response2->getStatusCode(), 'Should block post with 6 words');

        $body = json_decode($response2->getBody()->getContents(), true);
        $this->assertEquals('Post exceeds max word count of 5', $body['errors'][0]['detail']);
    }

    /** @test */
    public function group_blocks_posts_correctly()
    {
        // Normal user (ID: 2) -> Not in group 4, should pass
        $response = $this->submitReply('This is a test post.', 2);
        $this->assertEquals(201, $response->getStatusCode(), 'Should allow post for normal user');

        // Moderator (ID: 3) -> Is in group 4, should block
        $response2 = $this->submitReply('This is a test post.', 3);
        $this->assertEquals(422, $response2->getStatusCode(), 'Should block post for moderator');

        $body = json_decode($response2->getBody()->getContents(), true);
        $this->assertEquals('Blocked for Moderators only', $body['errors'][0]['detail']);
    }
}
