<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Provider;

use Flarum\Foundation\ValidationException;

/**
 * Implement this interface alongside RuleProviderInterface if your provider
 * requires configuration validation before saving rulesets.
 */
interface ValidatesConfigInterface
{
    /**
     * Validate the given config for the specified rule type.
     * Must throw Flarum\Foundation\ValidationException if the config is malformed.
     *
     * @param string $type
     * @param array $config
     * @return void
     * @throws ValidationException
     */
    public function validateConfig(string $type, array $config): void;
}
