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

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * @property int    $id
 * @property int|null $user_id
 * @property int    $ruleset_id
 * @property Carbon $created_at
 * @property bool   $is_cleared
 * @property string|null $content
 * @property string|null $message
 * @property array|null $tokens
 */
class FilterBlockLog extends AbstractModel
{
    protected $table = 'filter_rule_block_logs';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'is_cleared' => 'boolean',
        'tokens' => 'array',
    ];

    protected $fillable = ['user_id', 'ruleset_id', 'content', 'message', 'tokens', 'created_at', 'is_cleared'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ruleset()
    {
        return $this->belongsTo(Ruleset::class);
    }
}
