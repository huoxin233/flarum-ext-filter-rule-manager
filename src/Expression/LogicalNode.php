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

class LogicalNode implements NodeInterface
{
    public function __construct(
        public string $operator, // 'AND', 'OR'
        public NodeInterface $left,
        public NodeInterface $right
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => 'logical',
            'operator' => $this->operator,
            'left' => $this->left->toArray(),
            'right' => $this->right->toArray(),
        ];
    }
}
