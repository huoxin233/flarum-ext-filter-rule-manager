<?php

namespace Huoxin\FilterRuleManager\Modifier;

interface ModifierInterface
{
    public function key(): string;
    public function name(): string;
    public function description(): string;

    /**
     * Modify the content before it's evaluated.
     */
    public function modify(string $content): string;
}
