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

use RuntimeException;

/**
 * Thrown by EvaluateBlockRulesets when one or more block rulesets are triggered.
 *
 * Carries structured alert data so the frontend can render it per display_mode.
 * Registered with Flarum's error handling pipeline via Extend\ErrorHandling.
 */
class RuleBlockException extends RuntimeException
{
    /**
     * @param array $alerts  Array of triggered alert data:
     *   [
     *     ['display_mode' => 'modal', 'effect_type' => 'block', 'message' => '...', 'tokens' => [...]]
     *   ]
     */
    public function __construct(public readonly array $alerts)
    {
        parent::__construct('Filter rule block alert triggered.');
    }
}
