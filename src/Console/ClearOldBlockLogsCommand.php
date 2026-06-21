<?php

namespace Huoxin\FilterRuleManager\Console;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Huoxin\FilterRuleManager\Model\FilterBlockLog;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Console\Command;

class ClearOldBlockLogsCommand extends Command
{
    protected $signature = 'huoxin:filter-rule:clear-logs';
    protected $description = 'Clear old filter rule evasion block logs.';

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $maxTimeout = Ruleset::max('evasion_timeout') ?? 0;
        $keepDays = (int) $this->settings->get('huoxin-filter.global_evasion_log_keep_days', 0);

        if ($keepDays <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subMinutes($maxTimeout)->subDays($keepDays);

        $deleted = FilterBlockLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted $deleted old block logs.");
    }
}
