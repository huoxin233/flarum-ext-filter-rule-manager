<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'filter_rules',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('ruleset_id');
        $table->string('provider', 100);
        $table->string('type', 100);
        $table->json('config')->nullable();
        $table->boolean('negate')->default(false);
        $table->unsignedInteger('sort_order')->default(0);

        $table->foreign('ruleset_id')
              ->references('id')
              ->on('filter_rulesets')
              ->onDelete('cascade');
    }
);
