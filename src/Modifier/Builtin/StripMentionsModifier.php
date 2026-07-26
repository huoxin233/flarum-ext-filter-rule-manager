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

use Huoxin\FilterRuleManager\Modifier\ModifierInterface;

class StripMentionsModifier implements ModifierInterface
{
    public function modify(string $content): string
    {
        // Strip mentions: @"User Name"#123, @"User Name"#p123, and @username
        $targetContent = preg_replace('/@"?[^"#\n]+"?#(?:p)?\d+/', '', $content) ?? $content;
        $targetContent = preg_replace('/@\w+/', '', $targetContent) ?? $targetContent;

        return $targetContent;
    }
}
