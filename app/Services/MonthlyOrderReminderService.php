<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\MonthlyOrderReminder;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class MonthlyOrderReminderService
{
    public function scheduleForOrder(Order $order, bool $optIn = false): void
    {
        try {
            if (!$optIn) {
                return;
            }

            if (!Helpers::get_business_settings('monthly_order_reminder')) {
                return;
            }

            if (!in_array($order->module_type, ['pharmacy', 'grocery'])) {
                return;
            }

            if (!$order->user_id) {
                return;
            }

            if (MonthlyOrderReminder::where('order_id', $order->id)->exists()) {
                return;
            }

            $reminderBefore = (int) (Helpers::get_business_settings('monthly_order_reminder_days_before') ?? 3);
            $reminderUnit   = Helpers::get_business_settings('monthly_order_reminder_before_unit') ?? 'day';
            $daysBefore     = match ($reminderUnit) {
                'week'  => $reminderBefore * 7,
                'month' => $reminderBefore * 30,
                default => $reminderBefore,
            };
            $remindAt   = now()->addMonth()->subDays($daysBefore)->startOfDay();

            MonthlyOrderReminder::create([
                'user_id'     => $order->user_id,
                'order_id'    => $order->id,
                'module_id'   => $order->module_id,
                'module_type' => $order->module_type,
                'zone_id'     => $order->zone_id ?? '',
                'remind_at'   => $remindAt->toDateString(),
                'status'      => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthlyOrderReminderService scheduleForOrder failed', [
                'order_id' => $order->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
