<?php

namespace Huoxin\FilterRuleManager\Modifier;

use Huoxin\FilterRuleManager\Model\EvaluationContext;

interface ModifierInterface
{
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /**
     * Modify the content before it's evaluated.
     */
    public function modify(string $content, ?EvaluationContext $context = null): string;
}
