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

class NotNode implements NodeInterface
{
    public function __construct(
        public NodeInterface $node
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => 'not',
            'node' => $this->node->toArray(),
        ];
    }
}
