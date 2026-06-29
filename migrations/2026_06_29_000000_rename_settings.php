<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        
        $settings = [
            'huoxin-filter.global_evaluate_title' => 'huoxin-filter-rule-manager.global_evaluate_title',
            'huoxin-filter.global_auto_flag' => 'huoxin-filter-rule-manager.global_auto_flag',
            'huoxin-filter.global_require_approval' => 'huoxin-filter-rule-manager.global_require_approval',
            'huoxin-filter.global_evasion_active' => 'huoxin-filter-rule-manager.global_evasion_active',
            'huoxin-filter.global_evasion_timeout' => 'huoxin-filter-rule-manager.global_evasion_timeout',
            'huoxin-filter.global_evasion_threshold' => 'huoxin-filter-rule-manager.global_evasion_threshold',
            'huoxin-filter.global_evasion_log_keep_days' => 'huoxin-filter-rule-manager.global_evasion_log_keep_days',
            'huoxin-filter.obfuscate_active' => 'huoxin-filter-rule-manager.obfuscate_active',
            'huoxin-filter.obfuscate_key' => 'huoxin-filter-rule-manager.obfuscate_key',
        ];

        foreach ($settings as $old => $new) {
            $db->table('settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }
    },
    
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        
        $settings = [
            'huoxin-filter.global_evaluate_title' => 'huoxin-filter-rule-manager.global_evaluate_title',
            'huoxin-filter.global_auto_flag' => 'huoxin-filter-rule-manager.global_auto_flag',
            'huoxin-filter.global_require_approval' => 'huoxin-filter-rule-manager.global_require_approval',
            'huoxin-filter.global_evasion_active' => 'huoxin-filter-rule-manager.global_evasion_active',
            'huoxin-filter.global_evasion_timeout' => 'huoxin-filter-rule-manager.global_evasion_timeout',
            'huoxin-filter.global_evasion_threshold' => 'huoxin-filter-rule-manager.global_evasion_threshold',
            'huoxin-filter.global_evasion_log_keep_days' => 'huoxin-filter-rule-manager.global_evasion_log_keep_days',
            'huoxin-filter.obfuscate_active' => 'huoxin-filter-rule-manager.obfuscate_active',
            'huoxin-filter.obfuscate_key' => 'huoxin-filter-rule-manager.obfuscate_key',
        ];

        foreach ($settings as $old => $new) {
            $db->table('settings')
                ->where('key', $new)
                ->update(['key' => $old]);
        }
    }
];
