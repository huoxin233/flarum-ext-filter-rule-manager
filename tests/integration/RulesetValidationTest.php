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

class RulesetValidationTest extends FilterTestCase
{
    /** @test */
    public function admin_can_save_valid_regex()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Valid Regex Test',
                            'expression' => 'builtin.regex eq {"patterns": ["/valid_regex/i"]}',
                            'interventionType' => 'block',
                            'displayMode' => 'banner',
                            'message' => 'Blocked!',
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), 'Admin should be able to save a valid regex.');
    }

    /** @test */
    public function admin_gets_validation_error_on_invalid_regex()
    {
        $response = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Invalid Regex Test',
                            'expression' => 'builtin.regex eq {"patterns": ["/malformed_regex(/i"]}',
                            'interventionType' => 'block',
                            'displayMode' => 'banner',
                            'message' => 'Blocked!',
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode(), 'Admin should receive a 422 validation error for invalid regex.');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertStringContainsString('Invalid regex pattern', $body['errors'][0]['detail']);
    }

    /** @test */
    public function admin_gets_validation_error_on_complex_nested_expression()
    {
        // Tests that the recursive AST visitor dives through NOT and AND/OR wrappers
        // and correctly runs validation on deeply nested nodes.
        $response = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Complex Regex Test',
                            'expression' => 'not (builtin.contains_word eq {"words": ["foo"]} and builtin.regex eq {"patterns": ["/nested_malformed(/i"]})',
                            'interventionType' => 'block',
                            'displayMode' => 'banner',
                            'message' => 'Blocked!',
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode(), 'Admin should receive a 422 validation error for deep nested invalid regex.');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertStringContainsString('Invalid regex pattern', $body['errors'][0]['detail']);
    }

    /** @test */
    public function admin_gets_validation_error_when_updating_ruleset()
    {
        // First, create a valid ruleset
        $createResponse = $this->send(
            $this->request('POST', '/api/filter-rule-rulesets', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'attributes' => [
                            'name' => 'Valid initial',
                            'expression' => 'builtin.regex eq {"patterns": ["/valid/i"]}',
                            'interventionType' => 'block',
                            'displayMode' => 'banner',
                            'message' => 'Blocked!',
                        ]
                    ]
                ]
            ])
        );
        $this->assertEquals(201, $createResponse->getStatusCode());
        $id = json_decode($createResponse->getBody()->getContents(), true)['data']['id'];

        // Now, attempt to update it with an invalid regex
        $updateResponse = $this->send(
            $this->request('PATCH', "/api/filter-rule-rulesets/{$id}", [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'filter-rule-rulesets',
                        'id' => $id,
                        'attributes' => [
                            'expression' => 'builtin.regex eq {"patterns": ["/malformed(/i"]}',
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(422, $updateResponse->getStatusCode(), 'Admin should receive a 422 validation error when updating with invalid regex.');
        $body = json_decode($updateResponse->getBody()->getContents(), true);
        $this->assertStringContainsString('Invalid regex pattern', $body['errors'][0]['detail']);
    }
}
