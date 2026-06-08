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
    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);
        if ($actor->isGuest()) {
            $document->payload['filterRuleRulesets'] = [];
            return;
        }

        $rulesets = Ruleset::active()
            ->frontend()
            ->ordered()
            ->with('rules')
            ->get()
            ->map(fn (Ruleset $r) => [
                'id'           => $r->id,
                'name'         => $r->name,
                'priority'     => $r->priority,
                'ruleOperator' => $r->rule_operator,
                'effectType'   => $r->effect_type,
                'displayMode'      => $r->display_mode,
                'message'          => $r->message,
                'evaluateAllRules' => $r->evaluate_all_rules,
                'evaluateTitle'    => $r->evaluate_title,
                'blockCascade'     => $r->block_cascade,
                'scopeType'        => $r->scope_type,
                'scopeTagIds'  => $r->scope_tag_ids ?? [],
                'rules'        => $r->rules->map(fn ($rule) => [
                    'provider'  => $rule->provider,
                    'type'      => $rule->type,
                    'config'    => $rule->config ?? [],
                    'negate'    => $rule->negate,
                    'sortOrder' => $rule->sort_order,
                ])->toArray(),
            ])
            ->toArray();

        $document->payload['filterRuleRulesets'] = $rulesets;
    }
}
