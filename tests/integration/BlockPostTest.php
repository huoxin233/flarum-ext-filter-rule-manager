<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Flarum\Testing\integration\TestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class BlockPostTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags');
        $this->extension('flarum-approval');
        $this->extension('flarum-tags');
        $this->extension('fof-byobu');
        $this->extension('huoxin-filter-rule-manager');

        $this->prepareDatabase([
            'users' => [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@machine.local', 'is_email_confirmed' => 1],
                ['id' => 2, 'username' => 'normalUser1', 'email' => 'normal1@machine.local', 'is_email_confirmed' => 1],
                ['id' => 3, 'username' => 'normalUser2', 'email' => 'normal2@machine.local', 'is_email_confirmed' => 1],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test Discussion', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Block Bad Words',
                    'priority' => 0,
                    'rule_operator' => 'AND',
                    'effect_type' => 'block', // This makes it a block rule
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked word: {{matched_word}}',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ],
            'filter_rules' => [
                [
                    'id' => 1,
                    'ruleset_id' => 1,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['blockword']]),
                    'sort_order' => 0
                ]
            ]
        ]);
    }

    protected function submitReply(string $content, int $userId = 2)
    {
        return $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $userId,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => $content
                        ],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]
                        ]
                    ]
                ]
            ])
        );
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
}
