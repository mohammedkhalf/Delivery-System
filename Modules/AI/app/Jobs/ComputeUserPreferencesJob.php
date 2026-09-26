<?php

namespace Modules\AI\app\Jobs;

use Modules\AI\app\Services\Personalization\PersonalizationService;
use Modules\AI\app\Models\CustomerPreferenceSummary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComputeUserPreferencesJob implements ShouldQueue
{
    use Queueable;

    public ?int $userId;
    public ?int $moduleId;

    public function __construct(?int $userId = null, ?int $moduleId = null)
    {
        $this->userId = $userId;
        $this->moduleId = $moduleId;
    }

    public function handle(): void
    {
        if ($this->userId) {
            PersonalizationService::rebuildSummary($this->userId, $this->moduleId);
            return;
        }

        $dirtySummaries = CustomerPreferenceSummary::where('update_count', '>=', PersonalizationService::REBUILD_THRESHOLD)
            ->select('user_id', 'module_id')
            ->get();

        foreach ($dirtySummaries as $summary) {
            PersonalizationService::rebuildSummary($summary->user_id, $summary->module_id);
        }
    }
}
