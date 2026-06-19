<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Api\Controller;

trait RulesetValidationTrait
{
    protected function sanitizeIds($raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', $raw), fn ($id) => $id > 0));

        return $ids === [] ? null : $ids;
    }

    protected function validEnum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
