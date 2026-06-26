<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */


use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'filter_rule_block_logs',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('ruleset_id');

        $table->timestamp('created_at')->useCurrent();
        $table->boolean('is_cleared')->default(false);
        $table->longText('content')->nullable();
        $table->text('message')->nullable();
        $table->json('tokens')->nullable();

        // Relationships
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('ruleset_id')->references('id')->on('filter_rulesets')->onDelete('cascade');
        $table->index(['user_id', 'is_cleared', 'created_at'], 'filter_rule_block_logs_uid_clear_created_idx');
        $table->index('created_at');
    }
);
