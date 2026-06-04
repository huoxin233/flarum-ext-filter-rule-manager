<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('filter_rule_block_logs')) {
            $schema->create('filter_rule_block_logs', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->unsigned()->nullable();
                $table->integer('ruleset_id')->unsigned();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('filter_rule_block_logs');
    },
];
