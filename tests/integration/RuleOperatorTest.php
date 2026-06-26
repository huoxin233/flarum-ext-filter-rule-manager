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
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'AND',
                        'left' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['apple']]
                        ],
                        'right' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['banana']]
                        ]
                    ]),
                    'intervention_type' => 'block', // Use block for easier assertions
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
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'OR',
                        'left' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['cat']]
                        ],
                        'right' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['dog']]
                        ]
                    ]),
                    'intervention_type' => 'block', // Use block for easier assertions
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked by OR',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 3,
                    'name' => 'NOT Logic Ruleset',
                    'priority' => 2,
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'AND',
                        'left' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['bird']]
                        ],
                        'right' => [
                            'type' => 'not',
                            'node' => [
                                'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['cage']]
                            ]
                        ]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Blocked by NOT',
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

    /**
     * @test
     */
    public function not_logic_inverts_rule_matching()
    {
        // Contains bird but also cage (should NOT block)
        $response = $this->submitReply('I have a bird in a cage.', 6);
        $this->assertEquals(201, $response->getStatusCode(), 'Should not block because cage is present (NOT logic)');

        // Contains bird without cage (should block)
        $response = $this->submitReply('I have a bird flying free.', 7);
        $this->assertEquals(422, $response->getStatusCode(), 'Should block because bird is present and cage is NOT present');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Blocked by NOT', $body['errors'][0]['detail']);
    }
}
