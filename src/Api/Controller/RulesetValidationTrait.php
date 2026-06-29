<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Api\Controller;

use Huoxin\FilterRuleManager\Expression\LogicalNode;
use Huoxin\FilterRuleManager\Expression\NodeInterface;
use Huoxin\FilterRuleManager\Expression\NotNode;
use Huoxin\FilterRuleManager\Expression\RuleNode;
use Huoxin\FilterRuleManager\Provider\ValidatesConfigInterface;

trait RulesetValidationTrait
{
    protected function sanitizeIds($raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', $raw), fn ($id) => $id > 0));

        return $ids === [] ? null : $ids;
    }

    protected function validEnum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    protected function validateAstNode(NodeInterface $node, array $providers): void
    {
        if ($node instanceof RuleNode) {
            $provider = $providers[$node->provider] ?? null;
            if ($provider instanceof ValidatesConfigInterface) {
                $isObject = is_array($node->value) && ! array_is_list($node->value);
                if ($isObject) {
                    $config = array_merge($node->value, ['operator' => $node->operator]);
                } else {
                    $config = ['operator' => $node->operator, 'value' => $node->value];
                }
                $provider->validateConfig($node->ruleType, $config);
            }
        } elseif ($node instanceof LogicalNode) {
            $this->validateAstNode($node->left, $providers);
            $this->validateAstNode($node->right, $providers);
        } elseif ($node instanceof NotNode) {
            $this->validateAstNode($node->node, $providers);
        }
    }
}
