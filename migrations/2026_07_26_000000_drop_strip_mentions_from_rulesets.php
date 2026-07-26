<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('filter_rulesets', 'strip_mentions')) {
            $schema->table('filter_rulesets', function (Blueprint $table) {
                $table->dropColumn('strip_mentions');
            });
        }
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('filter_rulesets', 'strip_mentions')) {
            $schema->table('filter_rulesets', function (Blueprint $table) {
                $table->boolean('strip_mentions')->nullable()->after('evaluate_title');
            });
        }
    }
];
