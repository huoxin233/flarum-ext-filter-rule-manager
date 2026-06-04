<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('filter_rulesets')) {
            $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('filter_rulesets', 'auto_flag')) {
                    $table->boolean('auto_flag')->default(false)->after('block_cascade');
                }
                if (!$schema->hasColumn('filter_rulesets', 'require_approval')) {
                    $table->boolean('require_approval')->default(false)->after('auto_flag');
                }
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasTable('filter_rulesets')) {
            $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('filter_rulesets', 'auto_flag')) {
                    $table->dropColumn('auto_flag');
                }
                if ($schema->hasColumn('filter_rulesets', 'require_approval')) {
                    $table->dropColumn('require_approval');
                }
            });
        }
    },
];
