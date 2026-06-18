<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int    $id
 * @property string $name
 * @property int    $priority
 * @property string|null $expression
 * @property array|null $compiled_ast
 * @property string $intervention_type    info|warning|block|silent
 * @property string $display_mode   banner|header_banner|toast|modal|sidebar
 * @property string $message
 * @property string|null $flag_message
 * @property bool   $evaluate_all_rules
 * @property bool|null $evaluate_title
 * @property bool|null $evasion_active
 * @property int|null  $evasion_timeout
 * @property int|null  $evasion_threshold
 * @property bool   $block_cascade
 * @property bool   $is_active
 * @property string $scope_type     global|normal_post|private_post|tag
 * @property array|null $scope_tag_ids
 * @property array|null $group_ids
 * @property bool|null $auto_flag
 * @property bool|null $require_approval
 * @property array|null $display_settings
 */
class Ruleset extends AbstractModel
{
    protected $table = 'filter_rulesets';

    protected $casts = [
        'compiled_ast'       => 'array',
        'block_cascade'      => 'boolean',
        'is_active'          => 'boolean',
        'evaluate_title'     => 'boolean',
        'evaluate_all_rules' => 'boolean',
        'evasion_active'     => 'boolean',
        'evasion_timeout'    => 'integer',
        'evasion_threshold'  => 'integer',
        'auto_flag'          => 'boolean',
        'require_approval'   => 'boolean',
        'scope_tag_ids'      => 'array',
        'group_ids'          => 'array',
        'display_settings'   => 'array',
    ];

    protected static $activeRulesetsCache = null;

    public static function getActiveRulesets()
    {
        if (self::$activeRulesetsCache === null) {
            self::$activeRulesetsCache = static::active()->ordered()->get();
        }
        return self::$activeRulesetsCache;
    }

    public static function boot()
    {
        parent::boot();

        static::saved(function () {
            self::$activeRulesetsCache = null;
        });
        static::deleted(function () {
            self::$activeRulesetsCache = null;
        });
    }


    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBlock(Builder $query): Builder
    {
        return $query->where('intervention_type', 'block');
    }

    public function scopeFrontend(Builder $query): Builder
    {
        return $query->whereIn('intervention_type', ['info', 'warning']);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority');
    }
}
