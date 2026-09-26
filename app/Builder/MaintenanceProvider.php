<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DataSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Builder\Contracts\MaintenanceProvider as MaintenanceProviderContract;
use Modules\Builder\ValueObjects\MaintenanceState;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6amMart maintenance adapter.
 *
 * Mirrors the host's admin maintenance feature (Business Settings → Maintenance
 * Mode), but enforces it for the builder storefront when the dedicated
 * `vendor_storefront` system is selected. The master switch is the
 * `maintenance_mode` business setting; the systems list, duration window, and
 * message body live in the `data_settings` rows of type `maintenance_mode`
 * (read through the same `data_settings_maintenance_mode` cache the mobile API
 * config uses, so admin saves stay consistent).
 *
 * Duration semantics match App\Http\Middleware\MaintenanceMode exactly:
 *   - 'until_change'  → active while the master switch is on
 *   - dated window    → active only when now() is between start and end
 */
class MaintenanceProvider implements MaintenanceProviderContract
{
    /** The system key this storefront is gated by (admin checkbox). */
    private const SYSTEM_KEY = 'vendor_storefront';

    public function state(?StorefrontScope $scope = null): MaintenanceState
    {
        // Master switch off → never in maintenance.
        if (! (int) (Helpers::get_business_settings('maintenance_mode') ?? 0)) {
            return MaintenanceState::inactive();
        }

        $data = $this->maintenanceData();

        $systems = $this->decode($data['maintenance_system_setup'] ?? null, []);
        if (! \in_array(self::SYSTEM_KEY, (array) $systems, true)) {
            return MaintenanceState::inactive();
        }

        $duration = $this->decode($data['maintenance_duration_setup'] ?? null, []);
        if (! $this->withinWindow($duration)) {
            return MaintenanceState::inactive();
        }

        $message = $this->decode($data['maintenance_message_setup'] ?? null, []);

        return new MaintenanceState(
            active:  true,
            title:   $message['maintenance_message'] ?? null,
            body:    $message['message_body'] ?? null,
            endDate: $this->endDate($duration),
            phone:   ! empty($message['business_number'])
                ? (BusinessSetting::where('key', 'phone')->value('value') ?: null)
                : null,
            email:   ! empty($message['business_email'])
                ? (BusinessSetting::where('key', 'email')->value('value') ?: null)
                : null,
        );
    }

    /**
     * The three maintenance DataSetting rows, JSON-decoded, keyed by setting
     * key. Cached forever (same key the host config endpoint uses); the admin
     * save path forgets this key, so it stays fresh.
     *
     * @return array<string,mixed>
     */
    private function maintenanceData(): array
    {
        return Cache::rememberForever('data_settings_maintenance_mode', function () {
            return DataSetting::where('type', 'maintenance_mode')
                ->whereIn('key', [
                    'maintenance_system_setup',
                    'maintenance_duration_setup',
                    'maintenance_message_setup',
                ])
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Replicates the host MaintenanceMode middleware window logic:
     * "until_change" is always live; a dated window is live only between its
     * start and end. A malformed/missing window is treated as not-active
     * (fail-open) so a half-saved config never traps every storefront.
     */
    private function withinWindow(array $duration): bool
    {
        if (($duration['maintenance_duration'] ?? null) === 'until_change') {
            return true;
        }

        $start = $duration['start_date'] ?? null;
        $end   = $duration['end_date'] ?? null;
        if (! $start || ! $end) {
            return false;
        }

        try {
            return Carbon::now()->between(Carbon::parse($start), Carbon::parse($end));
        } catch (\Throwable) {
            return false;
        }
    }

    /** ISO end timestamp for the countdown, or null for an open-ended window. */
    private function endDate(array $duration): ?string
    {
        if (($duration['maintenance_duration'] ?? null) === 'until_change') {
            return null;
        }

        $end = $duration['end_date'] ?? null;
        if (! $end) {
            return null;
        }

        try {
            return Carbon::parse($end)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Decode a value that may already be an array (cache) or a JSON string. */
    private function decode(mixed $value, mixed $fallback): mixed
    {
        if (\is_array($value)) {
            return $value;
        }
        if (\is_string($value)) {
            $decoded = \json_decode($value, true);
            return \is_null($decoded) ? $fallback : $decoded;
        }
        return $fallback;
    }
}
