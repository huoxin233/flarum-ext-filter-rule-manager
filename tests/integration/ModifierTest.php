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
use PHPUnit\Framework\Attributes\Test;
use Huoxin\FilterRuleManager\Modifier\ModifierInterface;
use Huoxin\FilterRuleManager\Extend\FilterContentModifier;

class StripQuotesModifier implements ModifierInterface
{
    public function key(): string { return 'strip_quotes'; }
    public function name(): string { return 'Strip Quotes'; }
    public function description(): string { return 'test'; }

    public function modify(string $content): string
    {
        return preg_replace('/>.*?$/m', '', $content);
    }
}

class StripSpoilersModifier implements ModifierInterface
{
    public function key(): string { return 'strip_spoilers'; }
    public function name(): string { return 'Strip Spoilers'; }
    public function description(): string { return 'test'; }

    public function modify(string $content): string
    {
        return preg_replace('/\[spoiler\].*?\[\/spoiler\]/is', '', $content);
    }
}

class StateTrackingModifier implements ModifierInterface
{
    public static int $executionCount = 0;

    public function key(): string { return 'state_tracker'; }
    public function name(): string { return 'State Tracker'; }
    public function description(): string { return 'test'; }

    public function modify(string $content): string
    {
        self::$executionCount++;
        return $content; // Just track execution
    }
}

class ModifierTest extends FilterTestCase
{
    protected function setUp(): void
    {
        $this->extend(
            (new FilterContentModifier())
                ->register(StripQuotesModifier::class)
                ->register(StripSpoilersModifier::class)
                ->register(StateTrackingModifier::class)
        );

        parent::setUp();

        StateTrackingModifier::$executionCount = 0;
    }

    #[Test]
    public function rule_evaluates_modified_content_properly()
    {
        // Rule: block 'badword', but we apply strip_spoilers modifier
        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Block Bad Words Ignoring Spoilers',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule',
                        'provider' => 'builtin',
                        'ruleType' => 'contains_word',
                        'operator' => 'EQUALS',
                        'targetModifiers' => ['strip_spoilers'],
                        'value' => [
                            'words' => ['badword']
                        ]
                    ]),
                    'intervention_type' => 'block',
                    'display_mode' => 'toast',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);

        // If badword is inside spoiler, it gets stripped out before evaluation, so post is allowed
        $response1 = $this->submitReply('This is a test [spoiler] badword [/spoiler]', 2);
        $this->assertEquals(201, $response1->getStatusCode());

        // If badword is outside spoiler, it gets detected and blocked
        $response2 = $this->submitReply('This is a test badword [spoiler] clean [/spoiler]', 3);
        $this->assertEquals(422, $response2->getStatusCode());
    }

    #[Test]
    public function progressive_caching_optimizes_modifier_execution()
    {
        // Rule with two OR conditions that share the state_tracker modifier
        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Cache Test',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'OR',
                        'left' => [
                            'type' => 'rule',
                            'provider' => 'builtin',
                            'ruleType' => 'contains_word',
                            'operator' => 'EQUALS',
                            'targetModifiers' => ['state_tracker'],
                            'value' => [
                                'words' => ['word1']
                            ]
                        ],
                        'right' => [
                            'type' => 'rule',
                            'provider' => 'builtin',
                            'ruleType' => 'contains_word',
                            'operator' => 'EQUALS',
                            'targetModifiers' => ['state_tracker', 'strip_quotes'],
                            'value' => [
                                'words' => ['word2']
                            ]
                        ]
                    ]),
                    'intervention_type' => 'block',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);

        $response = $this->submitReply('Test content', 4);
        $this->assertEquals(201, $response->getStatusCode());

        // Because of progressive caching, state_tracker should execute EXACTLY ONCE
        // Even though it's referenced in two separate rules!
        $this->assertEquals(1, StateTrackingModifier::$executionCount);
    }

    #[Test]
    public function missing_modifier_fails_gracefully_and_returns_unmodified_content()
    {
        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Missing Modifier Test',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule',
                        'provider' => 'builtin',
                        'ruleType' => 'contains_word',
                        'operator' => 'EQUALS',
                        'targetModifiers' => ['non_existent_modifier', 'strip_spoilers'],
                        'value' => [
                            'words' => ['badword']
                        ]
                    ]),
                    'intervention_type' => 'block',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);

        // Even though 'non_existent_modifier' is missing, it should continue to 'strip_spoilers' and evaluate
        $response = $this->submitReply('Test [spoiler]badword[/spoiler]', 5);
        $this->assertEquals(201, $response->getStatusCode());
    }



    #[Test]
    public function modifier_does_not_leak_context_to_sibling_rules()
    {
        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Sibling Rule Context Test',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'AND',
                        'left' => [
                            'type' => 'rule',
                            'provider' => 'builtin',
                            'ruleType' => 'contains_word',
                            'operator' => 'EQUALS',
                            'targetModifiers' => ['strip_spoilers'],
                            'value' => [
                                'words' => ['badword']
                            ]
                        ],
                        'right' => [
                            'type' => 'rule',
                            'provider' => 'builtin',
                            'ruleType' => 'contains_word',
                            'operator' => 'EQUALS',
                            'value' => [
                                'words' => ['badword'],
                                // NO modifiers here. It should see the RAW string!
                            ]
                        ]
                    ]),
                    'intervention_type' => 'block',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);

        // Post: 'Test [spoiler]badword[/spoiler]'
        // Left Rule (has strip_spoilers): Sees 'Test ' -> Does NOT contain badword -> False.
        // Right Rule (no modifiers): Sees 'Test [spoiler]badword[/spoiler]' -> Contains badword -> True.
        // Result of AND group: False AND True = False (Allowed).
        // If the context leaked, both would be False!
        // But wait, to prove it didn't leak, we want to know what the right rule saw.
        // Actually, if the left rule is False, Flarum's AST might short-circuit!
        // Let's use OR. False OR True = True (Blocked).
        // If it leaked, it would be False OR False = False (Allowed).
        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Sibling Rule Context Test OR',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'OR',
                        'left' => [
                            'type' => 'rule',
                            'provider' => 'builtin',
                            'ruleType' => 'contains_word',
                            'operator' => 'EQUALS',
                            'targetModifiers' => ['strip_spoilers'],
                            'value' => [
                                'words' => ['badword']
                            ]
                        ],
                        'right' => [
                            'type' => 'rule',
                            'provider' => 'builtin',
                            'ruleType' => 'contains_word',
                            'operator' => 'EQUALS',
                            'value' => [
                                'words' => ['badword'],
                            ]
                        ]
                    ]),
                    'intervention_type' => 'block',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);

        // Left Rule evaluates to False (stripped out).
        // Right Rule evaluates to True (sees raw spoiler tag with badword).
        // Overall is blocked (422). If context leaked from Left to Right, it would be allowed (201).
        $response = $this->submitReply('Test [spoiler]badword[/spoiler]', 6);
        $this->assertEquals(422, $response->getStatusCode());
    }
}
