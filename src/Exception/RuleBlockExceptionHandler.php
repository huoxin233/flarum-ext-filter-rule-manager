<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Exception;

use Flarum\Foundation\ErrorHandling\HandledError;

/**
 * Converts RuleBlockException to a 422 JSON:API response.
 *
 * The frontend `requestErrorCatch` override checks `errors[0].code === 'filter_rule_block'`
 * and reads the merged `filterRules` field to render alerts per display_mode.
 *
 * A human-readable `detail` string is included so that any fallback error UI
 * (in the unlikely event our override does not run first) still shows
 * something sensible instead of stringifying the array.
 */
class RuleBlockExceptionHandler
{
    public function handle(RuleBlockException $e): HandledError
    {
        $messages = array_map(fn (array $alert) => $alert['message'] ?? '', $e->alerts);
        $detailText = implode("\n", array_filter($messages));

        return (new HandledError($e, 'filter_rule_block', 422))
            ->withDetails([
                [
                    'detail'         => $detailText !== '' ? $detailText : 'Filter rule block alert triggered.',
                    'filterRules' => $e->alerts,
                ],
            ]);
    }
}
