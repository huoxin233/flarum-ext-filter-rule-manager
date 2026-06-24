<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager;

use Flarum\Extend;
use Flarum\Post\Event\Saving;
use Huoxin\FilterRuleManager\Api\Controller;
use Huoxin\FilterRuleManager\Console\ClearOldBlockLogsCommand;
use Huoxin\FilterRuleManager\Exception\RuleBlockException;
use Huoxin\FilterRuleManager\Exception\RuleBlockExceptionHandler;
use Huoxin\FilterRuleManager\Listener\EvaluateBlockRulesets;
use Huoxin\FilterRuleManager\Listener\ExecuteModerationActions;
use Huoxin\FilterRuleManager\Listener\InjectFrontendRulesets;
use Huoxin\FilterRuleManager\Provider\FilterRuleManagerServiceProvider;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;

return [
    // ── Frontend assets ──────────────────────────────────────────────────────
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(InjectFrontendRulesets::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    // ── Translations ─────────────────────────────────────────────────────────
    new Extend\Locales(__DIR__.'/locale'),

    // ── Service provider (rule provider registry) ─────────────────────────
    (new Extend\ServiceProvider())
        ->register(FilterRuleManagerServiceProvider::class),

    // ── API routes (admin only) ───────────────────────────────────────────────
    (new Extend\Routes('api'))
        ->post('/filter-rule-rulesets/reorder', 'filter-rule.rulesets.reorder', Controller\ReorderRulesetsController::class)
        ->get('/filter-rule-providers', 'filter-rule.providers.index', Controller\ListProvidersController::class),

    // ── Block evaluation: fires on post save ──────────────────────────────────
    (new Extend\Event())
        ->listen(Saving::class, EvaluateBlockRulesets::class)
        ->subscribe(ExecuteModerationActions::class),

    // ── Custom exception → structured 422 response ────────────────────────────
    (new Extend\ErrorHandling())
        ->handler(RuleBlockException::class, RuleBlockExceptionHandler::class),

    // ── Default Settings ──────────────────────────────────────────────────
    (new Extend\Settings())
        ->default('huoxin-filter.global_evaluate_title', true)
        ->default('huoxin-filter.global_auto_flag', true)
        ->default('huoxin-filter.global_require_approval', true)
        ->default('huoxin-filter.global_evasion_active', false)
        ->default('huoxin-filter.global_evasion_timeout', 5)
        ->default('huoxin-filter.global_evasion_threshold', 2)
        ->default('huoxin-filter.global_evasion_log_keep_days', 30),

    // ── Prune old block logs command ──────────────────────────────────────
    (new Extend\Console())
        ->command(ClearOldBlockLogsCommand::class)
        ->schedule(ClearOldBlockLogsCommand::class, function (ScheduleEvent $event) {
            $event->daily();
        }),
    new Extend\ApiResource(Api\Resource\RulesetResource::class),
];
