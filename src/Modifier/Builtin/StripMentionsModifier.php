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

class StripMentionsModifier implements ModifierInterface
{
    public function key(): string
    {
        return 'strip_mentions';
    }

    public function name(): string
    {
        return 'huoxin-filter-rule-manager.admin.modifiers.strip_mentions';
    }

    public function description(): string
    {
        return 'huoxin-filter-rule-manager.admin.modifiers.strip_mentions_help';
    }

    public function modify(string $content, ?EvaluationContext $context = null): string
    {
        // Strip mentions: @"User Name"#123, @"User Name"#p123, and @username
        $targetContent = preg_replace('/@"?[^"#\n]+"?#(?:p)?\d+/', '', $content) ?? $content;
        $targetContent = preg_replace('/@[\p{L}\p{N}_-]+/u', '', $targetContent) ?? $targetContent;

        return $targetContent;
    }
}
