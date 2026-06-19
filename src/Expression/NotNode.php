<?php

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
