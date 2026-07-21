<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('filter_rulesets', 'strict_edit')) {
            $schema->table('filter_rulesets', function (Blueprint $table) {
                $table->boolean('strict_edit')->nullable()->default(null)->after('bypass_group_ids');
            });
        }
    },

    'down' => function (Builder $schema) {
        if ($schema->hasColumn('filter_rulesets', 'strict_edit')) {
            $schema->table('filter_rulesets', function (Blueprint $table) {
                $table->dropColumn('strict_edit');
            });
        }
    }
];
