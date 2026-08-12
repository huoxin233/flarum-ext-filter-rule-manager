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

abstract class AbstractBuiltinRule implements BuiltinRuleInterface
{
    public function validateConfig(array $config): void
    {
        // By default, do nothing. Override if validation is needed.
    }

    /**
     * Normalise either `[plural => string[]]` (new) or `[singular => string]`
     * (legacy) into a clean, trimmed, non-empty list.
     *
     * @return list<string>
     */
    protected function normalizeList(array $config, string $plural, string $singular): array
    {
        if (isset($config[$plural]) && is_array($config[$plural])) {
            $out = [];
            foreach ($config[$plural] as $v) {
                $v = trim((string) $v);
                if ($v !== '') {
                    $out[] = $v;
                }
            }

            return $out;
        }

        if (isset($config[$singular]) && is_string($config[$singular])) {
            $v = trim($config[$singular]);
            if ($v !== '') {
                return [$v];
            }
        }

        return [];
    }
}
