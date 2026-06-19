<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Carbon\Carbon;

class ScopeTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'discussions' => [
                // Private discussion (for User 3)
                ['id' => 2, 'title' => 'Private Discussion', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 3, 'first_post_id' => 2, 'comment_count' => 1, 'is_private' => 1],
                // Normal discussion with Gaming tag
                ['id' => 3, 'title' => 'Gaming Discussion', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'is_private' => 0],
                // Private discussion (for User 2)
                ['id' => 4, 'title' => 'Private Discussion 2', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 2, 'first_post_id' => 4, 'comment_count' => 1, 'is_private' => 1],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 2],
            ],
            'posts' => [
                ['id' => 2, 'discussion_id' => 2, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                ['id' => 3, 'discussion_id' => 3, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                ['id' => 4, 'discussion_id' => 4, 'user_id' => 4, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
            ],
            'recipients' => [
                ['id' => 1, 'discussion_id' => 2, 'user_id' => 3, 'group_id' => null],
                ['id' => 2, 'discussion_id' => 4, 'user_id' => 2, 'group_id' => null],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Private Only Ruleset',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['secret']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'private_post',
                    'message' => 'Blocked by Private',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Normal Only Ruleset',
                    'priority' => 1,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['publicword']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'normal_post',
                    'message' => 'Blocked by Normal',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 3,
                    'name' => 'Tag Scoped Ruleset',
                    'priority' => 2,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['gameword']]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'tag',
                    'scope_tag_ids' => json_encode(['2']), // Tag ID 2 (Gaming)
                    'message' => 'Blocked by Tag',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function private_ruleset_only_triggers_on_private_discussions()
    {
        // Normal discussion (ID 1) bypasses private rule
        $response = $this->submitReply('This has secret word.', 2, 1);
        $this->assertEquals(201, $response->getStatusCode());

        // Private discussion (ID 2) gets blocked
        $response = $this->submitReply('This has secret word.', 3, 2);
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Blocked by Private', $body['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function normal_ruleset_only_triggers_on_normal_discussions()
    {
        // Private discussion (ID 4) bypasses normal rule
        $response = $this->submitReply('This has publicword.', 2, 4);
        $this->assertEquals(201, $response->getStatusCode());

        // Normal discussion (ID 1) gets blocked
        $response = $this->submitReply('This has publicword.', 3, 1);
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Blocked by Normal', $body['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function tag_ruleset_only_triggers_on_specific_tags()
    {
        // Discussion 1 (General tag) bypasses Gaming tag rule
        $response = $this->submitReply('This has gameword.', 2, 1);
        $this->assertEquals(201, $response->getStatusCode());

        // Discussion 3 (Gaming tag) gets blocked
        $response = $this->submitReply('This has gameword.', 3, 3);
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Blocked by Tag', $body['errors'][0]['detail']);
    }
}
