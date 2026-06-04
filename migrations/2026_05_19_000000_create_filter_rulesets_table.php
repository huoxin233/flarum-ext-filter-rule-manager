<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'filter_rulesets',
    function (Blueprint $table) {
        $table->increments('id');
        $table->string('name', 100);
        $table->unsignedInteger('priority')->default(0);

        // Validation happens in the API controllers (validEnum). Storing as
        // varchar avoids future ALTER TABLE pain when new effect / display /
        // scope values are added.
        $table->string('rule_operator', 16)->default('AND');     // AND | OR
        $table->string('effect_type', 16)->default('info');      // info | warning | block
        $table->string('display_mode', 32)->default('banner');   // banner | header_banner | sidebar | toast | modal
        $table->string('scope_type', 32)->default('global');     // global | normal_post | private_post | tag

        $table->text('message');
        $table->boolean('block_cascade')->default(false);
        $table->boolean('is_active')->default(true);

        $table->json('scope_tag_ids')->nullable();
        $table->json('group_ids')->nullable();

        $table->timestamps();
    }
);
