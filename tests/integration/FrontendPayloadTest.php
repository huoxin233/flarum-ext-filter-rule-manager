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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class FrontendPayloadTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huoxin-filter-rule-manager');

        $this->prepareDatabase([
            User::class => [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@machine.local', 'is_email_confirmed' => 1],
                ['id' => 2, 'username' => 'normalUser', 'email' => 'normal@machine.local', 'is_email_confirmed' => 1],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Test Ruleset',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['badword']]
                    ]),
                    'intervention_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'message' => 'Warning Message',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ]
        ]);
    }

    #[Test]
    public function it_obfuscates_payload_by_default()
    {
        // Settings are empty by default, so obfuscation is ON (true fallback).
        $response = $this->send($this->request('GET', '/', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        $html = $response->getBody()->getContents();

        // Extract the payload
        preg_match('/"filterRuleRulesets":\s*(".*?"|\[.*?\])/', $html, $matches);

        $this->assertNotEmpty($matches, 'filterRuleRulesets should be present in the payload');

        $payloadValue = $matches[1];

        // Ensure it is a string (quotes) and NOT an array (brackets)
        $this->assertStringStartsWith('"', $payloadValue, 'Payload should be a string (obfuscated)');

        // Strip quotes and decode
        $base64 = trim($payloadValue, '"');
        $decoded = base64_decode($base64);

        // It shouldn't be plain readable JSON without XORing it first
        $this->assertStringNotContainsString('badword', $decoded);
    }

    #[Test]
    public function it_sends_plain_json_when_obfuscation_disabled()
    {
        $this->setting('huoxin-filter.obfuscate_active', '0');

        $response = $this->send($this->request('GET', '/', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        $html = $response->getBody()->getContents();

        preg_match('/"filterRuleRulesets":\s*(".*?"|\[.*?\])/', $html, $matches);

        $this->assertNotEmpty($matches, 'filterRuleRulesets should be present in the payload');

        $payloadValue = $matches[1];

        // Ensure it is an array bracket
        $this->assertStringStartsWith('[', $payloadValue, 'Payload should be a plain JSON array');

        // It should contain the cleartext word since it is not obfuscated
        $this->assertStringContainsString('badword', $payloadValue);
    }

    #[Test]
    public function it_hides_payload_from_guests()
    {
        $response = $this->send($this->request('GET', '/')); // Unauthenticated

        $this->assertEquals(200, $response->getStatusCode());
        $html = $response->getBody()->getContents();

        preg_match('/"filterRuleRulesets":\s*(".*?"|\[.*?\])/', $html, $matches);

        $this->assertNotEmpty($matches, 'filterRuleRulesets should be present in the payload');

        $payloadValue = $matches[1];

        // Ensure it is an empty array
        $this->assertEquals('[]', $payloadValue, 'Payload should be empty for guests');
    }

    #[Test]
    public function it_filters_out_bypassed_rulesets_and_strips_group_ids()
    {
        $this->setting('huoxin-filter.obfuscate_active', '0');

        // Add a second ruleset that is explicitly bypassed by group 3 (Member)
        $this->database()->table('filter_rulesets')->insert([
            'id' => 2,
            'name' => 'Bypassed Ruleset',
            'priority' => 1,
            'compiled_ast' => json_encode(['type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['secret']]]),
            'intervention_type' => 'warning',
            'display_mode' => 'banner',
            'scope_type' => 'global',
            'message' => 'Secret Message',
            'is_active' => 1,
            'bypass_group_ids' => json_encode([3]), // Normal user (2) has group 3 by default
            'auto_flag' => 0,
            'require_approval' => 0,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);

        $response = $this->send($this->request('GET', '/', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        $html = $response->getBody()->getContents();

        preg_match('/"filterRuleRulesets":\s*(".*?"|\[.*?\])/', $html, $matches);
        $payloadValue = $matches[1];

        // The bypassed ruleset should NOT be in the payload
        $this->assertStringNotContainsString('Bypassed Ruleset', $payloadValue);

        // The standard ruleset SHOULD still be in the payload
        $this->assertStringContainsString('Test Ruleset', $payloadValue);

        // The string "bypass_group_ids" MUST NOT exist in the payload
        $this->assertStringNotContainsString('bypass_group_ids', $payloadValue);
    }
}
