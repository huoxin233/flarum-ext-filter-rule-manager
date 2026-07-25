<?php

namespace Huoxin\FilterRuleManager\Modifier;

interface ModifierInterface
{
    /**
     * Modify the content before it's evaluated.
     */
    public function modify(string $content): string;
}
