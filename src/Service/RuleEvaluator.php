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

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Huoxin\FilterRuleManager\Extend\FilterContentModifier;
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Modifier\ModifierInterface;
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

    public function evaluateRuleset(Ruleset $ruleset, EvaluationContext $context, array $providers = []): ?array
    {
        $ast = $ruleset->compiled_ast;
        if (! $ast) {
            return null;
        }

        if (empty($providers)) {
            $providers = $this->getProviders();
        }
        $modifiedContentCache = [];

        return $this->evaluateAST($ast, $context, $providers, $ruleset, $modifiedContentCache);
    }

    private function evaluateAST(array $node, EvaluationContext $context, array $providers, Ruleset $ruleset, array &$modifiedContentCache): ?array
    {
        if (! isset($node['type'])) {
            return null;
        }

        if ($node['type'] === 'logical') {
            if (! isset($node['left']) || ! isset($node['right']) || ! isset($node['operator'])) {
                return null;
            }

            $left = $this->evaluateAST($node['left'], $context, $providers, $ruleset, $modifiedContentCache);

            if ($node['operator'] === 'OR') {
                if ($left !== null && ! $ruleset->evaluate_all_rules) {
                    return $left;
                }
                $right = $this->evaluateAST($node['right'], $context, $providers, $ruleset, $modifiedContentCache);

                if ($left !== null && $right !== null) {
                    return $this->mergeResults([$left, $right]);
                }

                return $left !== null ? $left : $right;
            }

            if ($node['operator'] === 'AND') {
                if ($left === null) {
                    return null;
                }
                $right = $this->evaluateAST($node['right'], $context, $providers, $ruleset, $modifiedContentCache);
                if ($right === null) {
                    return null;
                }

                return $this->mergeResults([$left, $right]);
            }
        }

        if ($node['type'] === 'not') {
            if (! isset($node['node'])) {
                return null;
            }
            $result = $this->evaluateAST($node['node'], $context, $providers, $ruleset, $modifiedContentCache);

            return $result === null ? [] : null;
        }

        if ($node['type'] === 'rule') {
            if (! isset($node['provider'])) {
                return null;
            }

            return $this->evaluateRuleNode($node, $context, $providers, $modifiedContentCache);
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

    public function evaluateRuleNode(array $node, EvaluationContext $context, array $providers, array &$modifiedContentCache): ?array
    {
        $provider = $providers[$node['provider']] ?? null;
        if ($provider === null) {
            return null;
        }

        if (! in_array($node['ruleType'], $provider->getSupportedBackendTypes(), true)) {
            return null;
        }

        $originalContent = $context->content;

        $targetModifiers = [];
        if (! empty($node['targetModifiers']) && is_array($node['targetModifiers'])) {
            $targetModifiers = $node['targetModifiers'];
        }

        try {
            if (! empty($targetModifiers) && $this->container->bound(FilterContentModifier::REGISTRY_KEY)) {
                $currentCacheKey = '';
                $modifiers = $this->container->make(FilterContentModifier::REGISTRY_KEY);

                foreach ($targetModifiers as $modifierKey) {
                    $currentCacheKey = $currentCacheKey === '' ? $modifierKey : $currentCacheKey.','.$modifierKey;

                    if (isset($modifiedContentCache[$currentCacheKey])) {
                        $context->content = $modifiedContentCache[$currentCacheKey];
                    } else {
                        if (isset($modifiers[$modifierKey])) {
                            /** @var ModifierInterface $modifierClass */
                            $modifierClass = $this->container->make($modifiers[$modifierKey]['class']);
                            $context->content = $modifierClass->modify($context->content, $context);
                        }
                        $modifiedContentCache[$currentCacheKey] = $context->content;
                    }
                }
            }

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
        } finally {
            $context->content = $originalContent;
        }
    }

    /**
     * @param Discussion|null $discussion
     * @param Post|null $post
     */
    public function scopeMatches(Ruleset $ruleset, $discussion, ?Post $post = null): bool
    {
        $postContext = $ruleset->post_context ?? 'all';
        if ($postContext !== 'all' && $post !== null) {
            $isDiscussionStart = ($post->number === 1)
                || ($post->number === null && (! $discussion || ! $discussion->exists || $discussion->first_post_id === null || $discussion->first_post_id === $post->id));

            if ($postContext === 'discussion_start' && ! $isDiscussionStart) {
                return false;
            }

            if ($postContext === 'reply' && $isDiscussionStart) {
                return false;
            }
        }

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

                /** @phpstan-ignore-next-line */
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

    public function interpolate(?string $template, array $tokens): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        if (preg_match('/^[a-zA-Z0-9\-_]+(?:\.[a-zA-Z0-9\-_]+)+$/', $template)) {
            $trans = $this->translator->trans($template, $tokens);
            if ($trans !== $template && $trans !== '') {
                $template = $trans;
            }
        }

        // 1. Process positive conditional blocks: {{#token}}...{{/token}}
        if (str_contains($template, '{{#')) {
            while (str_contains($template, '{{#')) {
                $prev = $template;
                $template = preg_replace_callback('/\{\{#(\w+)\}\}([\s\S]*?)\{\{\/\1\}\}/', function (array $m) use ($tokens) {
                    $key = $m[1];
                    $hasValue = isset($tokens[$key]) && $tokens[$key] !== '' && $tokens[$key] !== [];

                    return $hasValue ? $m[2] : '';
                }, $template);
                if ($template === $prev) {
                    break;
                }
            }
        }

        // 2. Process inverted conditional blocks: {{^token}}...{{/token}}
        if (str_contains($template, '{{^')) {
            while (str_contains($template, '{{^')) {
                $prev = $template;
                $template = preg_replace_callback('/\{\{\^(\w+)\}\}([\s\S]*?)\{\{\/\1\}\}/', function (array $m) use ($tokens) {
                    $key = $m[1];
                    $isEmpty = ! isset($tokens[$key]) || $tokens[$key] === '' || $tokens[$key] === [];

                    return $isEmpty ? $m[2] : '';
                }, $template);
                if ($template === $prev) {
                    break;
                }
            }
        }

        // 3. Process variable interpolation: {{token}}
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
