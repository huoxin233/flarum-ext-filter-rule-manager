<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Expression;

class RuleNode implements NodeInterface
{
    public function __construct(
        public string $provider,
        public string $ruleType,
        public string $operator,
        public mixed $value
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => 'rule',
            'provider' => $this->provider,
            'ruleType' => $this->ruleType,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }
}
