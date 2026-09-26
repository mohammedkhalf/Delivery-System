<?php

namespace App\Support;

use App\Models\BusinessSetting;

class DisbursementScheduleResolver
{
    public static function forDeliveryMan(): string
    {
        return self::build('dm');
    }

    public static function forStore(): string
    {
        return self::build('store');
    }

    private static function build(string $prefix): string
    {
        $settings = BusinessSetting::whereIn('key', [
            "{$prefix}_disbursement_time_period",
            "{$prefix}_disbursement_week_start",
            "{$prefix}_disbursement_create_time",
        ])->pluck('value', 'key')->all();

        $frequency = $settings["{$prefix}_disbursement_time_period"] ?? 'daily';
        $weekStart = $settings["{$prefix}_disbursement_week_start"] ?? 'sunday';
        $createAt  = $settings["{$prefix}_disbursement_create_time"] ?? '00:00';

        [$hour, $min] = array_pad(explode(':', $createAt), 2, '0');
        $hour = (int) $hour;
        $min  = (int) $min;

        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day  = array_search(strtolower($weekStart), $days, true);
        if ($day === false) {
            $day = 0;
        }

        return match ($frequency) {
            'weekly'  => "{$min} {$hour} * * {$day}",
            'monthly' => "{$min} {$hour} 28-31 * *",
            default   => "{$min} {$hour} * * *",
        };
    }
}
