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

class StripImagesModifier implements ModifierInterface
{
    public function key(): string
    {
        return 'strip_images';
    }

    public function name(): string
    {
        return 'huoxin-filter-rule-manager.admin.modifiers.strip_images';
    }

    public function description(): string
    {
        return 'huoxin-filter-rule-manager.admin.modifiers.strip_images_help';
    }

    public function modify(string $content, ?EvaluationContext $context = null): string
    {
        $targetContent = preg_replace('/\[img[^\]]*\](.*?)\[\/img\]/is', '', $content) ?? $content;
        $targetContent = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '', $targetContent) ?? $targetContent;

        return $targetContent;
    }
}
