<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Flarum\Testing\integration\TestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class RuleOperatorTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'AND Logic Ruleset',
                    'priority' => 0,
                    'rule_operator' => 'AND',
                    'effect_type' => 'block', // Use block for easier assertions
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked by AND',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'OR Logic Ruleset',
                    'priority' => 1,
                    'rule_operator' => 'OR',
                    'effect_type' => 'block', // Use block for easier assertions
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked by OR',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ],
            'filter_rules' => [
                // Rules for AND logic
                [
                    'id' => 1,
                    'ruleset_id' => 1,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['apple']]),
                    'sort_order' => 0
                ],
                [
                    'id' => 2,
                    'ruleset_id' => 1,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['banana']]),
                    'sort_order' => 1
                ],
                // Rules for OR logic
                [
                    'id' => 3,
                    'ruleset_id' => 2,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['cat']]),
                    'sort_order' => 0
                ],
                [
                    'id' => 4,
                    'ruleset_id' => 2,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['dog']]),
                    'sort_order' => 1
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function and_logic_requires_all_rules_to_match()
    {
        // Only contains apple (one of the AND rules)
        $response = $this->submitReply('I like apple.', 2);
        $this->assertEquals(201, $response->getStatusCode(), 'Should not block because banana is missing');

        // Contains both
        $response = $this->submitReply('I like apple and banana.', 3);
        $this->assertEquals(422, $response->getStatusCode(), 'Should block because both words are present');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Blocked by AND', $body['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function or_logic_requires_any_rule_to_match()
    {
        // Contains only one OR rule
        $response = $this->submitReply('I have a cat.', 4);
        $this->assertEquals(422, $response->getStatusCode(), 'Should block because cat is present');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Blocked by OR', $body['errors'][0]['detail']);

        // Contains the other OR rule
        $response = $this->submitReply('I have a dog.', 5);
        $this->assertEquals(422, $response->getStatusCode(), 'Should block because dog is present');
    }
}
