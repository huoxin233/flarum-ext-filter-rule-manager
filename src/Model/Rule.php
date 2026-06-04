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

/**
 * @property int    $id
 * @property int    $ruleset_id
 * @property string $provider
 * @property string $type
 * @property array|null $config
 * @property bool   $negate
 * @property int    $sort_order
 */
class Rule extends AbstractModel
{
    protected $table = 'filter_rules';

    public $timestamps = false;

    protected $casts = [
        'config' => 'array',
        'negate' => 'boolean',
    ];

    public function ruleset()
    {
        return $this->belongsTo(Ruleset::class, 'ruleset_id');
    }
}
