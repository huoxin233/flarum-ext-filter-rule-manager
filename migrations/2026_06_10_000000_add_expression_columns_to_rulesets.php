<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('filter_rulesets', 'expression')) {
                $table->text('expression')->nullable();
            }
            if (!$schema->hasColumn('filter_rulesets', 'compiled_ast')) {
                $table->json('compiled_ast')->nullable();
            }
            if ($schema->hasColumn('filter_rulesets', 'rule_operator')) {
                $table->dropColumn('rule_operator');
            }
            if ($schema->hasColumn('filter_rulesets', 'evaluate_all_rules')) {
                $table->dropColumn('evaluate_all_rules');
            }
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('filter_rulesets', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('filter_rulesets', 'expression')) {
                $table->dropColumn('expression');
            }
            if ($schema->hasColumn('filter_rulesets', 'compiled_ast')) {
                $table->dropColumn('compiled_ast');
            }
            if (!$schema->hasColumn('filter_rulesets', 'rule_operator')) {
                $table->string('rule_operator', 10)->default('AND');
            }
            if (!$schema->hasColumn('filter_rulesets', 'evaluate_all_rules')) {
                $table->boolean('evaluate_all_rules')->default(true);
            }
        });
    }
];
