<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Provider;

use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Provider\Builtin\BuiltinRuleInterface;
use Huoxin\FilterRuleManager\Provider\Builtin\ContainsWordRule;
use Huoxin\FilterRuleManager\Provider\Builtin\GroupRule;
use Huoxin\FilterRuleManager\Provider\Builtin\RegexRule;
use Huoxin\FilterRuleManager\Provider\Builtin\WordCountRule;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backend side of the builtin provider — kept in lockstep with the JS version.
 */
class BuiltinProvider implements RuleProviderInterface, ValidatesConfigInterface
{
    /**
     * @var array<string, BuiltinRuleInterface>
     */
    protected array $rules = [];

    public function __construct(protected TranslatorInterface $translator)
    {
        $this->registerRule(new ContainsWordRule());
        $this->registerRule(new RegexRule());
        $this->registerRule(new GroupRule());
        $this->registerRule(new WordCountRule());
    }

    protected function registerRule(BuiltinRuleInterface $rule): void
    {
        $this->rules[$rule->name()] = $rule;
    }

    public function getBackendTypeLabels(): array
    {
        $labels = [];
        foreach ($this->rules as $name => $rule) {
            $labels[$name] = $rule->label($this->translator);
        }

        return $labels;
    }

    public function getSupportedBackendTypes(): array
    {
        return array_keys($this->rules);
    }

    /**
     * Tokens this provider exposes per rule type, for use in ruleset messages.
     *
     * @param string $type The rule type being evaluated
     * @return list<array{name:string, description:string}>
     */
    public function getProvidedTokens(string $type): array
    {
        if (isset($this->rules[$type])) {
            return $this->rules[$type]->providedTokens();
        }

        return [];
    }

    public function evaluate(string $type, array $config, EvaluationContext $context): ?array
    {
        if (isset($this->rules[$type])) {
            return $this->rules[$type]->evaluate($config, $context);
        }

        return null;
    }

    public function validateConfig(string $type, array $config): void
    {
        if (isset($this->rules[$type])) {
            $this->rules[$type]->validateConfig($config);
        }
    }
}
