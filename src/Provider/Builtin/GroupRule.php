<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Provider\Builtin;

use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class GroupRule extends AbstractBuiltinRule
{
    public function name(): string
    {
        return 'group';
    }

    public function label(TranslatorInterface $translator): string
    {
        return $translator->trans('huoxin-filter-rule-manager.admin.type_group');
    }

    public function providedTokens(): array
    {
        return [
            ['name' => 'matched_group', 'description' => 'The user group ID that triggered the rule.'],
        ];
    }

    public function evaluate(array $config, EvaluationContext $context): ?array
    {
        if ($context->actor === null) {
            return null;
        }

        $userGroups = $context->actor->groups->pluck('id')->toArray();
        $targetGroups = $config['groupIds'] ?? [];

        if (! is_array($targetGroups)) {
            $targetGroups = [$targetGroups];
        }

        $targetGroups = array_map('intval', $targetGroups);
        $intersect = array_intersect($userGroups, $targetGroups);

        if (count($intersect) > 0) {
            return ['matched_group' => implode(', ', $intersect)];
        }

        return null;
    }
}
