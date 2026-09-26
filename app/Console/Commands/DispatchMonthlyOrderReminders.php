<?php

namespace App\Console\Commands;

use App\Jobs\MonthlyOrderReminderJob;
use App\Models\MonthlyOrderReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchMonthlyOrderReminders extends Command
{
    protected $signature = 'monthly-order-reminder:dispatch';
    protected $description = 'Process pending monthly order reminders whose remind_at is due';

    public function handle(): int
    {
        $count = 0;

        MonthlyOrderReminder::query()
            ->where('status', 'pending')
            ->whereDate('remind_at', '<=', now()->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($reminders) use (&$count) {
                foreach ($reminders as $reminder) {
                    try {
                        MonthlyOrderReminderJob::dispatchSync($reminder);
                        $count++;
                    } catch (\Throwable $e) {
                        Log::error('DispatchMonthlyOrderReminders failed for reminder', [
                            'reminder_id' => $reminder->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Processed {$count} reminder(s).");

        return self::SUCCESS;
    }
}
