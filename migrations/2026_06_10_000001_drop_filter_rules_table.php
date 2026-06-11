<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->dropIfExists('filter_rules');
    },
    'down' => function (Builder $schema) {
        if (!$schema->hasTable('filter_rules')) {
            $schema->create('filter_rules', function (Blueprint $table) {
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
            });
        }
    }
];
