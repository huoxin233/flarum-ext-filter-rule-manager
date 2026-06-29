<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Service;

use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Provider\RuleProviderInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class RuleEvaluator
{
    public function __construct(
        protected Container $container,
        protected LoggerInterface $logger,
        protected TranslatorInterface $translator
    ) {
    }

    /**
     * @return array<string, RuleProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->container->make(FilterRuleProvider::REGISTRY_KEY);
    }

    public function evaluateRuleset(Ruleset $ruleset, EvaluationContext $context, array $providers): ?array
    {
        $ast = $ruleset->compiled_ast;
        if (empty($ast)) {
            return null;
        }

        return $this->evaluateAST($ast, $context, $providers, $ruleset);
    }

    public function evaluateAST(array $node, EvaluationContext $context, array $providers, Ruleset $ruleset): ?array
    {
        if ($node['type'] === 'logical') {
            $left = $this->evaluateAST($node['left'], $context, $providers, $ruleset);

            if ($node['operator'] === 'OR') {
                if ($left !== null && ! $ruleset->evaluate_all_rules) {
                    return $left;
                }
                $right = $this->evaluateAST($node['right'], $context, $providers, $ruleset);

                if ($left !== null && $right !== null) {
                    return $this->mergeResults([$left, $right]);
                }

                return $left !== null ? $left : $right;
            }

            if ($node['operator'] === 'AND') {
                if ($left === null) {
                    return null;
                }
                $right = $this->evaluateAST($node['right'], $context, $providers, $ruleset);
                if ($right === null) {
                    return null;
                }

                return $this->mergeResults([$left, $right]);
            }
        }

        if ($node['type'] === 'not') {
            $result = $this->evaluateAST($node['node'], $context, $providers, $ruleset);

            return $result === null ? [] : null;
        }

        if ($node['type'] === 'rule') {
            return $this->evaluateRuleNode($node, $context, $providers);
        }

        return null;
    }

    private function mergeResults(array $results): array
    {
        $merged = [];
        foreach ($results as $r) {
            if ($r !== null) {
                foreach ($r as $key => $val) {
                    if (isset($merged[$key]) && is_string($val) && is_string($merged[$key])) {
                        $existing = array_map('trim', explode(',', $merged[$key]));
                        $new = array_map('trim', explode(',', $val));
                        $merged[$key] = implode(', ', array_unique(array_merge($existing, $new)));
                    } else {
                        $merged[$key] = $val;
                    }
                }
            }
        }

        return $merged;
    }

    public function evaluateRuleNode(array $node, EvaluationContext $context, array $providers): ?array
    {
        $provider = $providers[$node['provider']] ?? null;
        if ($provider === null) {
            return null;
        }

        if (! in_array($node['ruleType'], $provider->getSupportedBackendTypes(), true)) {
            return null;
        }

        try {
            $isObject = is_array($node['value']) && ! array_is_list($node['value']);

            if ($isObject) {
                $config = array_merge($node['value'], ['operator' => $node['operator']]);
            } else {
                $config = ['operator' => $node['operator'], 'value' => $node['value']];
            }
            $result = $provider->evaluate($node['ruleType'], $config, $context);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('[filter-rule-manager] provider evaluate() threw', [
                'provider' => $node['provider'],
                'type' => $node['ruleType'],
                'exception' => $e,
            ]);

            return null;
        }
    }

    public function scopeMatches(Ruleset $ruleset, $discussion): bool
    {
        $isPrivate = false;
        if ($discussion) {
            $isPrivate = (bool) ($discussion->is_private ?? false);
        }

        switch ($ruleset->scope_type) {
            case 'global':
                return true;

            case 'normal_post':
                return ! $isPrivate;

            case 'private_post':
                return (bool) $isPrivate;

            case 'tag':
                if (empty($ruleset->scope_tag_ids) || $discussion === null) {
                    return false;
                }

                $tags = $discussion->tags;
                if (! $tags) {
                    return false;
                }

                $tagIds = $tags->pluck('id')->toArray();

                return count(array_intersect($ruleset->scope_tag_ids, $tagIds)) > 0;

            default:
                return false;
        }
    }

    public function interpolate(string $template, array $tokens): string
    {
        if (preg_match('/^[a-zA-Z0-9\-_]+(?:\.[a-zA-Z0-9\-_]+)+$/', $template)) {
            $trans = $this->translator->trans($template, $tokens);
            if ($trans !== $template && $trans !== '') {
                $template = is_array($trans) ? $trans[0] : $trans;
            }
        }

        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($tokens) {
            if (isset($tokens[$m[1]])) {
                $val = $tokens[$m[1]];
                if (is_array($val)) {
                    // Flatten multi-dimensional arrays from recursive merges
                    $flatten = function ($array) use (&$flatten) {
                        $result = [];
                        foreach ($array as $item) {
                            if (is_array($item)) {
                                $result = array_merge($result, $flatten($item));
                            } else {
                                $result[] = $item;
                            }
                        }

                        return $result;
                    };
                    $val = implode(', ', array_unique($flatten($val)));
                }

                return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
            }

            return $m[0];
        }, $template);
    }
}
