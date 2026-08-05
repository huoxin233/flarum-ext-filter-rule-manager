<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Modifier\Builtin;

use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Modifier\ModifierInterface;

class StripUrlsModifier implements ModifierInterface
{
    public function key(): string
    {
        return 'strip_urls';
    }

    public function name(): string
    {
        return 'huoxin-filter-rule-manager.admin.modifiers.strip_urls';
    }

    public function description(): string
    {
        return 'huoxin-filter-rule-manager.admin.modifiers.strip_urls_help';
    }

    public function modify(string $content, ?EvaluationContext $context = null): string
    {
        $targetContent = preg_replace('/https?:\/\/[^\s]+/i', '', $content) ?? $content;

        return $targetContent;
    }
}
