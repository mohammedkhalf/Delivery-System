<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicleCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lists the ride vehicle categories available in the customer's zone, with
 * their base fare and per-km rate. Pure read-only — no booking, no driver
 * matching. The RiderVehicleCategory model applies an `is_ride = 1` global
 * scope (DMVehicle), so we only ever see ride-eligible rows here.
 *
 * Fares are sourced through the `tripFares` hasMany relation (RideFare),
 * which is keyed by (vehicle_category_id, zone_id). When the customer
 * spans multiple zones (overlapping coverage areas), we use the first
 * matching fare row to keep the answer concise.
 */
class GetRideVehicleTypesTool implements Tool
{
    /**
     * @param int[] $zoneIds Overlapping zones the customer falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly array $zoneIds = [],
    ) {}

    public function description(): string
    {
        return 'List the ride vehicle types available in the customer\'s area (Bike, Sedan, SUV, etc.) along with each one\'s base fare and per-km rate. Use this when the customer asks "what ride types are there?", "what vehicles do you have?", "is bike available?", or compares ride categories. Read-only — does not book a ride.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetRideVehicleTypesTool');

        if (empty($this->zoneIds)) {
            return 'Ride options aren\'t available without a delivery area — please set your location first.';
        }

        // tripFares relation lives on DMVehicle (parent class). Eager-load
        // only the rows that match the user's zones so the LLM doesn't see
        // fare data from other regions.
        $categories = RiderVehicleCategory::where('status', 1)
            ->whereHas('tripFares', fn ($q) => $q->whereIn('zone_id', $this->zoneIds))
            ->with(['tripFares' => fn ($q) => $q->whereIn('zone_id', $this->zoneIds)
                ->select(['id', 'vehicle_category_id', 'zone_id', 'base_fare', 'base_fare_per_km'])])
            ->get(['id', 'name', 'type', 'status']);

        if ($categories->isEmpty()) {
            return 'Ride options aren\'t configured for your area yet. Try checking back later, or contact support if this is unexpected.';
        }

        $lines = $categories->map(function (RiderVehicleCategory $cat) {
            // Use the first available fare row — when a user's zones overlap,
            // showing every zone's row would spam the response.
            $fare = $cat->tripFares->first();
            if (! $fare) {
                return null;
            }
            $base   = round((float) $fare->getAttribute('base_fare'), 2);
            $perKm  = round((float) $fare->getAttribute('base_fare_per_km'), 2);
            $type   = $cat->getAttribute('type'); // 'car', 'motor_bike', etc.
            return '• ' . $cat->getAttribute('name')
                . ($type ? ' (' . $type . ')' : '')
                . ' — base ' . $base . ' + ' . $perKm . '/km';
        })->filter()->values();

        if ($lines->isEmpty()) {
            return 'Vehicle categories exist for your area but their fares aren\'t set up — please contact support.';
        }

        return 'Ride types available in your area: ' . PHP_EOL
            . $lines->implode(PHP_EOL) . PHP_EOL
            . 'Ask "how much for ~X km?" for a fare estimate.';
    }
}
