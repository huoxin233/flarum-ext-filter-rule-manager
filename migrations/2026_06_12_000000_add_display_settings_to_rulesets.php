<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) {
            $table->json('display_settings')->nullable();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) {
            $table->dropColumn('display_settings');
        });
    }
];
