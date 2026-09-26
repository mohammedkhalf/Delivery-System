<?php

namespace App\Jobs;

use App\CentralLogics\Helpers;
use App\Models\MonthlyOrderReminder;
use App\Models\NotificationMessage;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonthlyOrderReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300;

    public function __construct(public MonthlyOrderReminder $reminder) {}

    public function handle(): void
    {
        try {
            $this->reminder->refresh();
            if ($this->reminder->status !== 'pending') {
                return;
            }

            if (!Helpers::get_business_settings('monthly_order_reminder')) {
                return;
            }

            $user = $this->reminder->user;
            if (!$user || !$user->cm_firebase_token || $user->cm_firebase_token === '@') {
                return;
            }

            $notification = NotificationMessage::with(['translations' => function ($query) use ($user) {
                $query->where('locale', $user->current_language_key ?: 'en');
            }])
                ->where('key', 'monthly_order_reminder')
                ->where('status', 1)
                ->first();

            if (!$notification) {
                return;
            }

            $message = $notification->translations->first()->value ?? $notification->message;
            if (trim((string) $message) === '') {
                return;
            }

            $title = translate('Time to Reorder!');
            $body  = Helpers::text_variable_data_format(
                value: $message,
                user_name: trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
            );

            $data = [
                'title'       => $title,
                'description' => $body,
                'body'        => $body,
                'image'       => '',
                'type'        => 'monthly_order_reminder',
                'order_id'    => (string) $this->reminder->order_id,
                'data_id'     => (string) $this->reminder->id,
            ];

            Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);

            UserNotification::create([
                'data'    => json_encode($data),
                'user_id' => $user->id,
            ]);

            $this->reminder->update([
                'status'      => 'sent',
                'notified_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthlyOrderReminderJob handle failed', [
                'reminder_id' => $this->reminder->id ?? null,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('MonthlyOrderReminderJob failed', [
            'reminder_id' => $this->reminder->id ?? null,
            'error'       => $e->getMessage(),
        ]);
    }
}
