<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'filter_rulesets',
    function (Blueprint $table) {
        $table->increments('id');
        $table->string('name', 100);
        $table->unsignedInteger('priority')->default(0);

        // General settings
        $table->boolean('is_active')->default(true);
        $table->string('scope_type', 32)->default('global');     // global | normal_post | private_post | tag
        $table->json('scope_tag_ids')->nullable();
        $table->json('group_ids')->nullable();

        // Display settings
        $table->string('intervention_type', 16)->default('info');      // info | warning | block | silent | require_approval | auto_flag
        $table->string('display_mode', 32)->default('banner');   // banner | header_banner | sidebar | toast | modal
        $table->text('message')->nullable();
        $table->text('flag_message')->nullable();
        $table->json('display_settings')->nullable();

        // Moderation
        $table->boolean('auto_flag')->nullable();
        $table->boolean('require_approval')->nullable();
        $table->boolean('block_cascade')->default(false);

        // Expression evaluation
        $table->text('expression')->nullable();
        $table->json('compiled_ast')->nullable();
        $table->boolean('evaluate_all_rules')->default(false);
        $table->boolean('evaluate_title')->nullable();

        // Evasion tracking
        $table->boolean('evasion_active')->nullable();
        $table->integer('evasion_timeout')->nullable();
        $table->integer('evasion_threshold')->nullable();

        $table->timestamps();
    }
);
