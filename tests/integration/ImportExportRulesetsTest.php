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

use Flarum\Group\Group;
use Huoxin\FilterRuleManager\Model\Ruleset;
use PHPUnit\Framework\Attributes\Test;

class ImportExportRulesetsTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            Ruleset::class => [
                [
                    'id' => 1,
                    'name' => 'Test Ruleset 1',
                    'priority' => 10,
                    'is_active' => true,
                    'scope_type' => 'tag',
                    'scope_tag_ids' => json_encode([1, 2]),
                    'bypass_group_ids' => json_encode([1, 2]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'message' => 'Test msg',
                    'expression' => 'builtin.contains_word eq {"words": ["bad"]}',
                    'compiled_ast' => json_encode(['type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'eq', 'value' => ['words' => ['bad']]]),
                ]
            ],
            Group::class => [
                ['id' => 1, 'name_singular' => 'Admin', 'name_plural' => 'Admins', 'color' => '#BADA55', 'icon' => 'fas fa-wrench'],
                ['id' => 2, 'name_singular' => 'Guest', 'name_plural' => 'Guests', 'color' => '#BADA55', 'icon' => 'fas fa-wrench'],
                ['id' => 3, 'name_singular' => 'Member', 'name_plural' => 'Members', 'color' => '#BADA55', 'icon' => 'fas fa-wrench'],
            ]
        ]);
    }

    #[Test]
    public function can_export_rulesets()
    {
        $response = $this->send(
            $this->request('GET', '/api/filter-rule/export-rulesets', [
                'authenticatedAs' => 1, // Admin
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertCount(1, $json);
        $exported = $json[0];

        $this->assertEquals('Test Ruleset 1', $exported['name']);

        // Ensure sensitive fields are stripped
        $this->assertArrayNotHasKey('id', $exported);
        $this->assertArrayNotHasKey('created_at', $exported);
        $this->assertArrayNotHasKey('updated_at', $exported);
        $this->assertArrayHasKey('priority', $exported);
        $this->assertArrayNotHasKey('scope_tag_ids', $exported);
        $this->assertArrayNotHasKey('bypass_group_ids', $exported);

        // Ensure relations mapped to strings
        $this->assertEquals(['general', 'gaming'], $exported['scope_tags']); // based on Tag DB from FilterTestCase
        $this->assertEquals(['Admin', 'Guest'], $exported['bypass_groups']);
    }

    #[Test]
    public function import_append_mode()
    {
        // First ruleset has priority 10
        $payload = [
            'rulesets' => [
                [
                    'name' => 'Imported Ruleset',
                    'is_active' => true,
                    'scope_type' => 'global',
                    'intervention_type' => 'block',
                    'expression' => 'builtin.contains_word eq {"words": ["test"]}',
                    'compiled_ast' => ['type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'eq', 'value' => ['words' => ['test']]],
                    'scope_tags' => ['gaming'],
                    'bypass_groups' => ['Member']
                ]
            ],
            'mode' => 'append',
            'preserve_priority' => false
        ];

        $response = $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1, // Admin
                'json' => $payload
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Check database
        $rulesets = Ruleset::orderBy('priority', 'asc')->get();
        $this->assertCount(2, $rulesets);

        // Original ruleset is untouched
        $this->assertEquals('Test Ruleset 1', $rulesets[0]->name);
        $this->assertEquals(10, $rulesets[0]->priority);

        // Imported ruleset is appended
        $this->assertEquals('Imported Ruleset', $rulesets[1]->name);
        $this->assertEquals(11, $rulesets[1]->priority); // Auto-incremented from 10

        // Keys mapped back correctly
        $this->assertEquals([2], $rulesets[1]->scope_tag_ids); // 'gaming' tag has ID 2
        $this->assertEquals([3], $rulesets[1]->bypass_group_ids); // 'Member' group has ID 3
    }

    #[Test]
    public function import_override_mode()
    {
        $payload = [
            'rulesets' => [
                [
                    'name' => 'Imported Ruleset Override',
                    'is_active' => true,
                    'scope_type' => 'global',
                    'intervention_type' => 'block',
                    'expression' => 'builtin.contains_word eq {"words": ["test"]}',
                    'compiled_ast' => ['type' => 'rule'],
                ]
            ],
            'mode' => 'override',
            'preserve_priority' => false
        ];

        $response = $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => $payload
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $rulesets = Ruleset::all();
        $this->assertCount(1, $rulesets); // Old one deleted!

        $this->assertEquals('Imported Ruleset Override', $rulesets[0]->name);
        $this->assertEquals(1, $rulesets[0]->priority); // Starts from 1 because max was 0
    }

    #[Test]
    public function import_preserve_priority()
    {
        $payload = [
            'rulesets' => [
                [
                    'name' => 'Imported Priority',
                    'priority' => 99,
                    'is_active' => true,
                    'scope_type' => 'global',
                    'intervention_type' => 'block',
                    'expression' => 'test',
                    'compiled_ast' => [],
                ]
            ],
            'mode' => 'append',
            'preserve_priority' => true
        ];

        $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => $payload
            ])
        );

        $imported = Ruleset::where('name', 'Imported Priority')->first();
        $this->assertEquals(99, $imported->priority);
    }

    #[Test]
    public function import_handles_missing_foreign_keys_gracefully()
    {
        $payload = [
            'rulesets' => [
                [
                    'name' => 'Bad Foreign Keys',
                    'is_active' => true,
                    'scope_type' => 'global',
                    'intervention_type' => 'block',
                    'expression' => 'test',
                    'compiled_ast' => [],
                    'scope_tags' => ['does-not-exist'],
                    'bypass_groups' => ['Imaginary Group']
                ]
            ],
            'mode' => 'append',
            'preserve_priority' => false
        ];

        $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => $payload
            ])
        );

        $imported = Ruleset::where('name', 'Bad Foreign Keys')->first();
        // Should fallback to null when foreign keys don't exist
        $this->assertNull($imported->scope_tag_ids);
        $this->assertNull($imported->bypass_group_ids);
    }

    #[Test]
    public function invalid_payload_rejected()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'rulesets' => 'not-an-array',
                    'mode' => 'append',
                    'preserve_priority' => false
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function missing_rulesets_key_rejected()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'mode' => 'append',
                    'preserve_priority' => false
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function empty_rulesets_array_accepted()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'rulesets' => [],
                    'mode' => 'append',
                    'preserve_priority' => false
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function missing_required_keys_rejected()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule/import-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'rulesets' => [
                        [
                            // Missing required 'name' and 'expression' keys!
                            'priority' => 1,
                            'is_active' => true,
                            'scope_type' => 'global',
                            'intervention_type' => 'block'
                        ]
                    ],
                    'mode' => 'append',
                    'preserve_priority' => false
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }
}
