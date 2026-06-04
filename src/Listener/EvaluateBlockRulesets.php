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

use Flarum\Post\Event\Saving;
use Huoxin\FilterRuleManager\Exception\RuleBlockException;
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Model\Rule;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Provider\RuleProviderInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;

class EvaluateBlockRulesets
{
    public function __construct(
        protected Container $container,
        protected LoggerInterface $logger
    ) {
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;

        // Only run on new post creation. The plan's submit flow only covers
        // first-time submission; blocking on edits would surprise moderators
        // fixing typos in old content under newer rules.
        if ($post->exists) {
            return;
        }

        $content    = (string) $post->content;
        $discussion = $post->discussion;

        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()->block()->ordered()->with('rules')->get();

        /** @var array<string, RuleProviderInterface> $providers */
        $providers = $this->container->make(FilterRuleProvider::REGISTRY_KEY);

        $triggered = [];

        foreach ($rulesets as $ruleset) {
            if (!$this->scopeMatches($ruleset, $discussion)) {
                continue;
            }

            $tokens = $this->evaluateRuleset($ruleset, $content, $providers);
            if ($tokens === null) {
                continue;
            }

            $triggered[] = [
                'display_mode' => $ruleset->display_mode,
                'effect_type'  => 'block',
                'message'      => $this->interpolate($ruleset->message, $tokens),
                'tokens'       => $tokens,
            ];

            if ($ruleset->block_cascade) {
                break;
            }
        }

        if (!empty($triggered)) {
            throw new RuleBlockException($triggered);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, RuleProviderInterface> $providers
     */
    private function evaluateRuleset(Ruleset $ruleset, string $content, array $providers): ?array
    {
        $results = [];

        // rules() relation already sorts by sort_order, but be explicit.
        $rules = $ruleset->rules->sortBy('sort_order')->values();

        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($rule, $content, $providers);
        }

        if (empty($results)) {
            return null;
        }

        $op = $ruleset->rule_operator;

        $triggered = $op === 'AND'
            ? !in_array(null, $results, true)
            : count(array_filter($results, fn ($r) => $r !== null)) > 0;

        if (!$triggered) {
            return null;
        }

        $merged = [];
        foreach ($results as $r) {
            if ($r !== null) {
                $merged = array_merge($merged, $r);
            }
        }

        return $merged;
    }

    /**
     * @param array<string, RuleProviderInterface> $providers
     */
    private function evaluateRule(Rule $rule, string $content, array $providers): ?array
    {
        $provider = $providers[$rule->provider] ?? null;
        if ($provider === null) {
            $this->logger->warning('[filter-rule-manager] rule references unregistered provider', [
                'provider' => $rule->provider,
                'type'     => $rule->type,
                'rule_id'  => $rule->id,
                'ruleset'  => $rule->ruleset_id,
            ]);
            return null;
        }

        if (!in_array($rule->type, $provider->getSupportedBackendTypes(), true)) {
            $this->logger->warning('[filter-rule-manager] provider does not support type', [
                'provider' => $rule->provider,
                'type'     => $rule->type,
                'rule_id'  => $rule->id,
            ]);
            return null;
        }

        try {
            $result = $provider->evaluate($rule->type, $content, $rule->config ?? []);
        } catch (\Throwable $e) {
            $this->logger->error('[filter-rule-manager] provider evaluate() threw', [
                'provider'  => $rule->provider,
                'type'      => $rule->type,
                'rule_id'   => $rule->id,
                'exception' => $e,
            ]);
            return null;
        }

        if ($rule->negate) {
            return $result === null ? [] : null;
        }

        return $result;
    }

    private function scopeMatches(Ruleset $ruleset, $discussion): bool
    {
        switch ($ruleset->scope_type) {
            case 'global':
                return true;

            case 'normal_post':
                return !($discussion?->is_private ?? false);

            case 'private_post':
                return (bool) ($discussion?->is_private ?? false);

            case 'tag':
                if (empty($ruleset->scope_tag_ids) || $discussion === null || !method_exists($discussion, 'tags')) {
                    return false;
                }
                $tagIds = $discussion->tags->pluck('id')->toArray();
                return count(array_intersect($ruleset->scope_tag_ids, $tagIds)) > 0;

            default:
                return false;
        }
    }

    private function interpolate(string $template, array $tokens): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($tokens) {
            if (isset($tokens[$m[1]])) {
                return htmlspecialchars((string) $tokens[$m[1]], ENT_QUOTES, 'UTF-8');
            }
            return $m[0];
        }, $template);
    }
}
