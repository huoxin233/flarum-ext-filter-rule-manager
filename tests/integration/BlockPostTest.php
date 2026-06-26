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

class BlockPostTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Clean post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>This contains blockword</p></t>', 'is_approved' => 1, 'number' => 2, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Block Bad Words',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule',
                        'provider' => 'builtin',
                        'ruleType' => 'contains_word',
                        'operator' => 'EQUALS',
                        'value' => ['words' => ['blockword']]
                    ]),
                    'intervention_type' => 'block', // This makes it a block rule
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked word: {{matched_word}}',
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
    public function posting_clean_content_is_not_blocked()
    {
        $response = $this->submitReply('This is a completely clean post.', 2);

        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function posting_restricted_word_blocks_post_and_logs_it()
    {
        $response = $this->submitReply('This contains blockword which triggers a block.', 3);

        // Assert 422 Unprocessable Entity
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        // Assert the custom message was returned and interpolated correctly
        $this->assertNotEmpty($body['errors']);
        $error = $body['errors'][0];
        $this->assertEquals('Blocked word: blockword', $error['detail']);

        // Assert a log was created in filter_rule_block_logs
        $log = $this->database()->table('filter_rule_block_logs')->where('user_id', 3)->first();
        $this->assertNotNull($log, 'Block log should be created');
        $this->assertEquals(1, $log->ruleset_id, 'Ruleset ID should be logged');
    }

    /**
     * @test
     */
    public function editing_clean_post_to_include_blockword_is_blocked()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'I edited this to include blockword.'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode(), 'Editing a post to include a blockword should be blocked');
    }

    /**
     * @test
     */
    public function hiding_or_recovering_post_with_blockword_does_not_trigger_block()
    {
        // Hide it
        $response = $this->send(
            $this->request('PATCH', '/api/posts/2', [
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
        $this->assertEquals(200, $response->getStatusCode(), 'Hiding a post with a blockword should succeed');

        // Recover it
        $response = $this->send(
            $this->request('PATCH', '/api/posts/2', [
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
        $this->assertEquals(200, $response->getStatusCode(), 'Recovering a post with a blockword should succeed');
    }
}
