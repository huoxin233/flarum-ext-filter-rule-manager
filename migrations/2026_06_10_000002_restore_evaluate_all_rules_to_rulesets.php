<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('filter_rulesets', 'evaluate_all_rules')) {
                $table->boolean('evaluate_all_rules')->default(false);
            }
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('filter_rulesets', 'evaluate_all_rules')) {
                $table->dropColumn('evaluate_all_rules');
            }
        });
    }
];
