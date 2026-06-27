<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Listener;

use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Injects active info/warning rulesets (with their rules) into the forum
 * page payload so FilterEngine has zero-latency access on first render.
 *
 * Only frontend-scope rulesets are included — block rulesets are evaluated
 * server-side and never need to be sent to the browser.
 *
 * The payload is gated on the actor being authenticated. Anonymous visitors
 * cannot compose posts, and exposing moderation rules (especially regex
 * patterns) to everyone would be unnecessary information disclosure.
 */
class InjectFrontendRulesets
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);

        // DEBUG: Dump actor state before guest check
        $debugCookies = json_encode($request->getCookieParams());
        $debugSession = $request->getAttribute('session');
        $debugSessionToken = $debugSession ? $debugSession->get('access_token') : 'NO_SESSION';
        throw new \RuntimeException(
            "DEBUG-INJECT-PAYLOAD:\n" .
            "Actor class: " . get_class($actor) . "\n" .
            "Actor ID: " . ($actor->id ?? 'null') . "\n" .
            "Is Guest: " . ($actor->isGuest() ? 'YES' : 'NO') . "\n" .
            "Session access_token: " . ($debugSessionToken ?? 'null') . "\n" .
            "Request cookies: " . $debugCookies
        );

        // Temporarily removed until Flarum natively supports a "Nobody" permission
        if ($actor->isGuest() /* || $actor->can('huoxin-filter-rule-manager.bypassAllRules') */) {
            $document->payload['filterRuleRulesets'] = [];

            return;
        }

        $userGroups = $actor->groups->pluck('id')->toArray();

        $rulesets = Ruleset::active()
            ->frontend()
            ->ordered()
            ->get()
            ->filter(function (Ruleset $r) use ($userGroups) {
                if (is_array($r->bypass_group_ids) && count($r->bypass_group_ids) > 0) {
                    if (count(array_intersect($userGroups, $r->bypass_group_ids)) > 0) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (Ruleset $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'priority' => $r->priority,
                'compiled_ast' => $r->compiled_ast,
                'interventionType' => $r->intervention_type,
                'evaluateAllRules' => $r->evaluate_all_rules,
                'displayMode' => $r->display_mode,
                'message' => $r->message,
                'evaluateTitle' => $r->evaluate_title === null ? null : (bool) $r->evaluate_title,
                'blockCascade' => $r->block_cascade,
                'scopeType' => $r->scope_type,
                'scopeTagIds' => $r->scope_tag_ids ?? [],
                'displaySettings' => $r->display_settings,
            ]);

        $rulesetsArray = $rulesets->values()->toArray();
        $isObfuscated = (bool) $this->settings->get('huoxin-filter.obfuscate_active', true);



        if ($isObfuscated) {
            $json = json_encode($rulesetsArray);
            $key = $this->settings->get('huoxin-filter.obfuscate_key', 'HuoxinFilterRuleManager');
            if (empty($key)) {
                $key = 'HuoxinFilterRuleManager';
            }
            $out = '';
            $keyLen = strlen($key);

            for ($i = 0, $len = strlen($json); $i < $len; $i++) {
                $out .= chr(ord($json[$i]) ^ ord($key[$i % $keyLen]));
            }

            $document->payload['filterRuleRulesets'] = base64_encode($out);
        } else {
            $document->payload['filterRuleRulesets'] = $rulesetsArray;
        }
    }
}
