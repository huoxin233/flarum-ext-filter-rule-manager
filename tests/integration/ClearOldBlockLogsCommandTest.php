<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Huoxin\FilterRuleManager\Console\ClearOldBlockLogsCommand;
use Huoxin\FilterRuleManager\Model\FilterBlockLog;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ClearOldBlockLogsCommandTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'filter_rulesets' => [
                [
                    'id' => 10,
                    'name' => 'Evasion Ruleset',
                    'intervention_type' => 'block',
                    'evasion_active' => 1,
                    'evasion_timeout' => 10,
                    'is_active' => 1,
                ],
            ],
            'filter_rule_block_logs' => [
                // Extremely old, should be deleted
                ['id' => 100, 'user_id' => 2, 'ruleset_id' => 10, 'is_cleared' => 0, 'created_at' => Carbon::now()->subDays(31)->toDateTimeString()],
                // Right on the edge but older than maxTimeout (10m) + keepDays (30d), should be deleted
                ['id' => 101, 'user_id' => 2, 'ruleset_id' => 10, 'is_cleared' => 0, 'created_at' => Carbon::now()->subDays(30)->subMinutes(11)->toDateTimeString()],
                // Just inside the keep window, should NOT be deleted
                ['id' => 102, 'user_id' => 2, 'ruleset_id' => 10, 'is_cleared' => 0, 'created_at' => Carbon::now()->subDays(30)->subMinutes(9)->toDateTimeString()],
                // Recent log, should NOT be deleted
                ['id' => 103, 'user_id' => 2, 'ruleset_id' => 10, 'is_cleared' => 0, 'created_at' => Carbon::now()->subDays(5)->toDateTimeString()],
            ]
        ]);
    }

    public function test_it_deletes_old_logs_and_keeps_new_ones()
    {
        /** @var SettingsRepositoryInterface $settings */
        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
        $settings->set('huoxin-filter.global_evasion_log_keep_days', 30);

        // Run the command manually
        $command = $this->app()->getContainer()->make(ClearOldBlockLogsCommand::class);
        $command->setLaravel($this->app()->getContainer());
        $output = new BufferedOutput();
        $command->run(new ArrayInput([]), $output);

        // Assert
        $logs = FilterBlockLog::all()->keyBy('id');

        $this->assertFalse($logs->has(100), 'Log older than 31 days should be deleted');
        $this->assertFalse($logs->has(101), 'Log exactly on the edge + 1 min should be deleted');

        $this->assertTrue($logs->has(102), 'Log just inside the window should be kept');
        $this->assertTrue($logs->has(103), 'Recent log should be kept');

        $outputStr = $output->fetch();
        $this->assertStringContainsString('Deleted 2 old block logs.', $outputStr);
    }
}
