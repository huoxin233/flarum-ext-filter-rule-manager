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
 * @property string $rule_operator  AND|OR
 * @property string $effect_type    info|warning|block
 * @property string $display_mode   banner|toast|modal|sidebar
 * @property string $message
 * @property string|null $flag_message
 * @property bool   $evaluate_all_rules
 * @property bool   $block_cascade
 * @property bool   $is_active
 * @property string $scope_type     global|normal_post|private_post|tag
 * @property array|null $scope_tag_ids
 * @property array|null $group_ids
 * @property bool   $auto_flag
 * @property bool   $require_approval
 */
class Ruleset extends AbstractModel
{
    protected $table = 'filter_rulesets';

    protected $casts = [
        'block_cascade'      => 'boolean',
        'is_active'          => 'boolean',
        'evaluate_all_rules' => 'boolean',
        'auto_flag'          => 'boolean',
        'require_approval' => 'boolean',
        'scope_tag_ids'  => 'array',
        'group_ids'      => 'array',
    ];

    public function rules()
    {
        return $this->hasMany(Rule::class, 'ruleset_id')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBlock(Builder $query): Builder
    {
        return $query->where('effect_type', 'block');
    }

    public function scopeFrontend(Builder $query): Builder
    {
        return $query->whereIn('effect_type', ['info', 'warning']);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority');
    }
}
