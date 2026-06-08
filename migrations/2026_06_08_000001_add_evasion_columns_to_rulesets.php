<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('filter_rulesets', 'evasion_active')) {
                $table->boolean('evasion_active')->default(false);
            }
            if (!$schema->hasColumn('filter_rulesets', 'evasion_timeout')) {
                $table->integer('evasion_timeout')->default(5);
            }
            if (!$schema->hasColumn('filter_rulesets', 'evasion_threshold')) {
                $table->integer('evasion_threshold')->default(2);
            }
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('filter_rulesets', 'evasion_active')) {
                $table->dropColumn('evasion_active');
            }
            if ($schema->hasColumn('filter_rulesets', 'evasion_timeout')) {
                $table->dropColumn('evasion_timeout');
            }
            if ($schema->hasColumn('filter_rulesets', 'evasion_threshold')) {
                $table->dropColumn('evasion_threshold');
            }
        });
    }
];
