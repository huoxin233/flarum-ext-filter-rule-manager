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
use Huoxin\FilterRuleManager\Repository\RulesetRepository;
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
 * 
 * To ensure maximum performance, the raw payload is stored in Flarum's global
 * Application Cache. User-specific filtering (e.g., bypass groups) is applied 
 * lazily at runtime, and internal group IDs are securely stripped before injection.
 */
class InjectFrontendRulesets
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected RulesetRepository $repository
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);
        // Temporarily removed until Flarum natively supports a "Nobody" permission
        if ($actor->isGuest() /* || $actor->can('huoxin-filter-rule-manager.bypassAllRules') */) {
            $document->payload['filterRuleRulesets'] = [];

            return;
        }

        $rulesetsArray = $this->repository->getActiveFrontendRulesetsArray();

        if (empty($rulesetsArray)) {
            $document->payload['filterRuleRulesets'] = [];
            return;
        }

        $filteredRulesets = [];
        $userGroups = null;

        foreach ($rulesetsArray as $r) {
            if (! empty($r['bypass_group_ids'])) {
                if ($userGroups === null) {
                    $userGroups = $actor->groups->pluck('id')->toArray();
                }

                if (array_intersect($userGroups, $r['bypass_group_ids'])) {
                    continue;
                }
            }

            unset($r['bypass_group_ids']);
            $filteredRulesets[] = $r;
        }

        $rulesetsArray = $filteredRulesets;
        $isObfuscated = (bool) $this->settings->get('huoxin-filter-rule-manager.obfuscate_active', true);

        if ($isObfuscated) {
            $json = json_encode($rulesetsArray);
            $key = $this->settings->get('huoxin-filter-rule-manager.obfuscate_key', 'HuoxinFilterRuleManager');
            if (empty($key)) {
                $key = 'HuoxinFilterRuleManager';
            }

            $keyLen = strlen($key);

            for ($i = 0, $len = strlen($json); $i < $len; $i++) {
                $json[$i] = chr(ord($json[$i]) ^ ord($key[$i % $keyLen]));
            }

            $document->payload['filterRuleRulesets'] = base64_encode($json);
        } else {
            $document->payload['filterRuleRulesets'] = $rulesetsArray;
        }
    }
}
