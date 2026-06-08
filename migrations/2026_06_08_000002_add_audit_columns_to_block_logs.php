<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('filter_rule_block_logs', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('filter_rule_block_logs', 'is_cleared')) {
                $table->boolean('is_cleared')->default(false);
            }
            if (!$schema->hasColumn('filter_rule_block_logs', 'content')) {
                $table->longText('content')->nullable();
            }
            if (!$schema->hasColumn('filter_rule_block_logs', 'message')) {
                $table->text('message')->nullable();
            }
            if (!$schema->hasColumn('filter_rule_block_logs', 'tokens')) {
                $table->json('tokens')->nullable();
            }
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('filter_rule_block_logs', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('filter_rule_block_logs', 'is_cleared')) {
                $table->dropColumn('is_cleared');
            }
            if ($schema->hasColumn('filter_rule_block_logs', 'content')) {
                $table->dropColumn('content');
            }
            if ($schema->hasColumn('filter_rule_block_logs', 'message')) {
                $table->dropColumn('message');
            }
            if ($schema->hasColumn('filter_rule_block_logs', 'tokens')) {
                $table->dropColumn('tokens');
            }
        });
    }
];
