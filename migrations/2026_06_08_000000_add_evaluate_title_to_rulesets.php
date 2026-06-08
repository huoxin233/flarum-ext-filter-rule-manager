<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasColumn('filter_rulesets', 'evaluate_title')) {
            $schema->table('filter_rulesets', function (Blueprint $table) {
                $table->boolean('evaluate_title')->default(true);
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('filter_rulesets', 'evaluate_title')) {
            $schema->table('filter_rulesets', function (Blueprint $table) {
                $table->dropColumn('evaluate_title');
            });
        }
    }
];
