<?php

namespace Huoxin\FilterRuleManager\Expression;

class LogicalNode implements NodeInterface
{
    public function __construct(
        public string $operator, // 'AND', 'OR'
        public NodeInterface $left,
        public NodeInterface $right
    ) {}

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
