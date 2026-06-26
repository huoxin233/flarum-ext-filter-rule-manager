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
use Flarum\Testing\integration\TestCase;
use Huoxin\FilterRuleManager\Model\Ruleset;
use PHPUnit\Framework\Attributes\Test;

class CreateRulesetTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function can_create_valid_ruleset_and_compiles_ast()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 1, // Admin
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Test API Ruleset',
                            'expression' => 'builtin.contains_word eq {"words": ["aaa"]}',
                            'interventionType' => 'info',
                            'displayMode' => 'banner',
                            'message' => 'Matched {{matched_word}}',
                            'flagMessage' => '',
                            'evaluateAllRules' => false,
                            'isActive' => true,
                            'scopeType' => 'global'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertEquals('Test API Ruleset', $body['data']['attributes']['name']);

        $rulesetId = $body['data']['id'];

        $ruleset = Ruleset::find($rulesetId);
        $this->assertNotNull($ruleset);
        $this->assertEquals('builtin.contains_word eq {"words": ["aaa"]}', $ruleset->expression);
        $this->assertIsArray($ruleset->compiled_ast);
        $this->assertNotEmpty($ruleset->compiled_ast);

        // Assert priority is incremented correctly
        $this->assertEquals(10, $ruleset->priority);
    }

    #[Test]
    public function invalid_expression_syntax_returns_422()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 1, // Admin
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Bad Syntax Ruleset',
                            'expression' => 'builtin.contains_word eq {', // Missing closing bracket!
                            'interventionType' => 'info',
                            'displayMode' => 'banner',
                            'isActive' => true,
                            'scopeType' => 'global'
                        ]
                    ]
                ]
            ])
        );

        // Flarum global exception handler converts ValidationException to 422
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('errors', $body);

        // Assert the error points to the expression field
        $error = $body['errors'][0];
        $this->assertEquals('422', $error['status']);
        $this->assertStringContainsString('expression', $error['source']['pointer']);
    }

    #[Test]
    public function normal_users_cannot_create_rulesets()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 2, // Normal User
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Sneaky Ruleset',
                            'expression' => 'builtin.contains_word eq {"words": ["aaa"]}',
                            'interventionType' => 'info',
                            'displayMode' => 'banner',
                            'isActive' => true,
                            'scopeType' => 'global'
                        ]
                    ]
                ]
            ])
        );

        // Should be forbidden or unauthorized depending on how Flarum returns it (usually 403)
        $this->assertEquals(403, $response->getStatusCode());
    }
}
