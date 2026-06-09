<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->getConnection()->getDriverName() !== 'sqlite') {
            $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
                $table->boolean('evaluate_title')->nullable()->default(null)->change();
                $table->boolean('evasion_active')->nullable()->default(null)->change();
                $table->integer('evasion_timeout')->nullable()->default(null)->change();
                $table->integer('evasion_threshold')->nullable()->default(null)->change();
                $table->boolean('auto_flag')->nullable()->default(null)->change();
                $table->boolean('require_approval')->nullable()->default(null)->change();
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->getConnection()->getDriverName() !== 'sqlite') {
            $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
                $table->boolean('evaluate_title')->default(true)->change();
                $table->boolean('evasion_active')->default(false)->change();
                $table->integer('evasion_timeout')->default(5)->change();
                $table->integer('evasion_threshold')->default(2)->change();
                $table->boolean('auto_flag')->default(false)->change();
                $table->boolean('require_approval')->default(false)->change();
            });
        }
    }
];
