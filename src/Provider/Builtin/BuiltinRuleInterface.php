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

interface BuiltinRuleInterface
{
    public function name(): string;

    public function label(TranslatorInterface $translator): string;

    public function providedTokens(): array;

    public function evaluate(array $config, EvaluationContext $context): ?array;

    public function validateConfig(array $config): void;
}
