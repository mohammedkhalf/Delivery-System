<?php

namespace Modules\AI\app\Console\Commands;

use Modules\AI\app\Services\Personalization\PersonalizationService;
use Modules\AI\app\Models\CustomerPreference;
use Modules\AI\app\Models\CustomerPreferenceSummary;
use Illuminate\Console\Command;

class ComputeCustomerPreferences extends Command
{
    protected $signature = 'preferences:compute {--user_id= : Rebuild summary for a specific user} {--rebuild : Rebuild only dirty summaries}';
    protected $description = 'Rebuild customer preference summaries';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $userId = $this->option('user_id');

        if ($userId) {
            $this->info("Rebuilding summaries for user #{$userId}...");
            $moduleIds = CustomerPreference::where('user_id', $userId)
                ->distinct()
                ->pluck('module_id');

            foreach ($moduleIds as $moduleId) {
                PersonalizationService::rebuildSummary((int) $userId, $moduleId);
            }
            $this->info("Done.");
            return;
        }

        if ($this->option('rebuild')) {
            $dirty = CustomerPreferenceSummary::where('update_count', '>=', PersonalizationService::REBUILD_THRESHOLD)
                ->select('user_id', 'module_id')
                ->get();

            $this->info("Rebuilding {$dirty->count()} dirty summaries...");
            $bar = $this->output->createProgressBar($dirty->count());
            $bar->start();

            foreach ($dirty as $summary) {
                PersonalizationService::rebuildSummary($summary->user_id, $summary->module_id);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Done.");
            return;
        }

        $summaries = CustomerPreferenceSummary::select('user_id', 'module_id')->get();

        if ($summaries->isEmpty()) {
            $this->info("No summaries found. Users need to interact first to generate preferences.");
            return;
        }

        $this->info("Rebuilding ALL {$summaries->count()} summaries...");
        $bar = $this->output->createProgressBar($summaries->count());
        $bar->start();

        foreach ($summaries as $summary) {
            PersonalizationService::rebuildSummary($summary->user_id, $summary->module_id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("All summaries rebuilt successfully.");
    }
}
