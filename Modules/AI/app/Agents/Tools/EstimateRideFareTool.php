<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\RideShare\Entities\FareManagement\RideFare;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Fare estimator. The host's real estimator (CommonTrait::estimatedFare)
 * calls Google Maps via getRoutes() to derive the route distance — that's
 * an external HTTP hop we don't want to incur from a chat tool, both for
 * latency and quota reasons.
 *
 * So this tool works in two modes:
 *
 *   1. With distance_km supplied → returns per-category totals using
 *      base_fare + (base_fare_per_km * distance) plus a "this excludes
 *      waiting/idle/surge/tax" disclaimer.
 *   2. Without distance_km → returns the fare structure (base + per-km)
 *      so the LLM can ask the user for a distance and compute later.
 *
 * Waiting / idle / delay / cancellation fees are runtime fees applied to
 * actual trips — they are deliberately omitted from estimates because the
 * customer doesn't know in advance how long they'll wait.
 */
class EstimateRideFareTool implements Tool
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
        return 'Estimate a ride fare. If the customer gives an approximate distance ("about 10 km", "8 kilometres"), pass it as distance_km and the tool returns totals per vehicle category. If no distance is mentioned, pass null and the tool returns the per-km fare structure so you can ask the customer for the distance. Estimates exclude waiting, idle, surge, and tax — always relay that caveat in the response.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'distance_km' => $schema->number()
                ->description('Approximate trip distance in kilometres, as stated by the customer. Pass null if they have not given a distance — the tool will return the per-km structure so you can ask.')
                ->required()
                ->nullable(),
            'vehicle_category_name' => $schema->string()
                ->description('Optional filter — restrict the estimate to one category by name (e.g. "bike", "sedan"). Case-insensitive substring match. Pass null to estimate across all categories.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('EstimateRideFareTool');

        if (empty($this->zoneIds)) {
            return 'Fare estimates need a delivery area first — please set your location.';
        }

        $args        = $request->all();
        $distanceKm  = ($args['distance_km'] ?? null) !== null ? (float) $args['distance_km'] : null;
        $categoryHit = isset($args['vehicle_category_name']) && $args['vehicle_category_name'] !== null
            ? trim((string) $args['vehicle_category_name'])
            : null;

        // Sanity-check the distance. A chat-provided distance over 200km
        // almost certainly means the user is confused or testing — flag it
        // rather than returning a nonsense estimate.
        if ($distanceKm !== null && ($distanceKm <= 0 || $distanceKm > 200)) {
            return 'That distance looks off — ride estimates are valid for ~0–200 km. Please check the kilometres and try again.';
        }

        $rows = RideFare::whereIn('zone_id', $this->zoneIds)
            ->with('vehicleCategory:id,name,status')
            ->get(['id', 'vehicle_category_id', 'zone_id', 'base_fare', 'base_fare_per_km']);

        if ($rows->isEmpty()) {
            return 'Fare data isn\'t set up for your area yet — please contact support.';
        }

        // Filter by category name if the user named one ("just sedans").
        // Substring match keeps "bike" / "motor bike" / "motorbike" all aligned.
        if ($categoryHit !== null && $categoryHit !== '') {
            $needle = mb_strtolower($categoryHit);
            $rows   = $rows->filter(fn (RideFare $r) =>
                $r->vehicleCategory
                && mb_stripos((string) $r->vehicleCategory->getAttribute('name'), $needle) !== false
                && (int) $r->vehicleCategory->getAttribute('status') === 1
            );
            if ($rows->isEmpty()) {
                return 'No ride category matching "' . $categoryHit . '" is available in your area.';
            }
        } else {
            // Drop inactive categories from the default cross-category view.
            $rows = $rows->filter(fn (RideFare $r) =>
                $r->vehicleCategory && (int) $r->vehicleCategory->getAttribute('status') === 1
            );
        }

        // Deduplicate by category — if the user spans multiple zones we'll
        // see the same category twice; keep the first row per category.
        $rows = $rows->unique('vehicle_category_id');

        if ($distanceKm === null) {
            $lines = $rows->map(function (RideFare $r) {
                $name  = $r->vehicleCategory?->getAttribute('name') ?? 'Unknown';
                $base  = round((float) $r->getAttribute('base_fare'), 2);
                $perKm = round((float) $r->getAttribute('base_fare_per_km'), 2);
                return '• ' . $name . ': base ' . $base . ' + ' . $perKm . '/km';
            })->values()->implode(PHP_EOL);

            return 'Fare structure in your area:' . PHP_EOL . $lines . PHP_EOL
                . 'About how many kilometres is the trip? I\'ll calculate the estimate.';
        }

        $lines = $rows->map(function (RideFare $r) use ($distanceKm) {
            $name  = $r->vehicleCategory?->getAttribute('name') ?? 'Unknown';
            $base  = (float) $r->getAttribute('base_fare');
            $perKm = (float) $r->getAttribute('base_fare_per_km');
            $total = round($base + ($perKm * $distanceKm), 2);
            return '• ' . $name . ': ' . $total
                . ' (base ' . round($base, 2) . ' + ' . round($distanceKm, 1) . '×' . round($perKm, 2) . ')';
        })->values()->implode(PHP_EOL);

        return 'Estimated fare for ~' . round($distanceKm, 1) . ' km in your area:' . PHP_EOL
            . $lines . PHP_EOL
            . 'Note: this excludes waiting time, idle time, surge, and tax — actual fare may differ.';
    }
}
