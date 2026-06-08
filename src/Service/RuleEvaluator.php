<?php

namespace Huoxin\FilterRuleManager\Service;

use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Model\Rule;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Provider\RuleProviderInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;

class RuleEvaluator
{
    public function __construct(
        protected Container $container,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, RuleProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->container->make(FilterRuleProvider::REGISTRY_KEY);
    }

    public function evaluateRuleset(Ruleset $ruleset, string $content, array $providers): ?array
    {
        $results = [];
        $rules = $ruleset->rules->sortBy('sort_order')->values();
        $op = $ruleset->rule_operator;
        $evaluateAllRules = (bool) $ruleset->evaluate_all_rules;

        foreach ($rules as $rule) {
            $r = $this->evaluateRule($rule, $content, $providers, $ruleset);
            $results[] = $r;

            if (! $evaluateAllRules) {
                if ($op === 'OR' && $r !== null) {
                    break;
                }
                if ($op === 'AND' && $r === null) {
                    break;
                }
            }
        }

        if (empty($results)) {
            return null;
        }

        $triggered = $op === 'AND'
            ? ! in_array(null, $results, true)
            : count(array_filter($results, fn ($r) => $r !== null)) > 0;

        if (! $triggered) {
            return null;
        }

        $merged = [];
        foreach ($results as $r) {
            if ($r !== null) {
                foreach ($r as $key => $val) {
                    if (isset($merged[$key]) && is_string($val) && is_string($merged[$key])) {
                        // Concatenate without duplicating
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

    public function evaluateRule(Rule $rule, string $content, array $providers, ?Ruleset $ruleset = null): ?array
    {
        $provider = $providers[$rule->provider] ?? null;
        if ($provider === null) {
            return null;
        }

        if (! in_array($rule->type, $provider->getSupportedBackendTypes(), true)) {
            return null;
        }

        try {
            $config = $rule->config ?? [];
            $result = $provider->evaluate($rule->type, $content, $config);
        } catch (\Throwable $e) {
            $this->logger->error('[filter-rule-manager] provider evaluate() threw', [
                'provider' => $rule->provider,
                'type' => $rule->type,
                'rule_id' => $rule->id,
                'exception' => $e,
            ]);
            return null;
        }

        if ($rule->negate) {
            return $result === null ? [] : null;
        }

        return $result;
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
        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($tokens) {
            if (isset($tokens[$m[1]])) {
                return htmlspecialchars((string) $tokens[$m[1]], ENT_QUOTES, 'UTF-8');
            }
            return $m[0];
        }, $template);
    }
}
