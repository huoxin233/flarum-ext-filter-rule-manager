<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Flarum\Testing\integration\TestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class BlockPostTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
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
