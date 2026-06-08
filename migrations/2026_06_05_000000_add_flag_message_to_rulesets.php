<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) {
            $table->text('flag_message')->nullable();
            $table->boolean('evaluate_all_rules')->default(false);
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) {
            $table->dropColumn(['flag_message', 'evaluate_all_rules']);
        });
    }
];
