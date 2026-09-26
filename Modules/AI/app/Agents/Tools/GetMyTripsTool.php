<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use App\Models\Module;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lists the authenticated customer's recent ride history, or returns
 * aggregate spend stats. Read-only — no cancellation, no rebooking from
 * chat (those happen in the dedicated ride screens of the app).
 *
 * Schema notes from the audit:
 *   - RideRequest.customer_id holds the user FK (not user_id).
 *   - Status column is `current_status` — NOT `status`. Values:
 *     pending | accepted | ongoing | completed | cancelled | returning | returned.
 *   - Fare is split across `paid_fare` (final with discount), `actual_fare`
 *     (calculated at end of trip), `estimated_fare` (at booking). We
 *     resolve in that order so the user always sees the most accurate
 *     number that exists.
 *   - SoftDeletes is on; we use the default scope so trashed trips don't
 *     leak into the user's view.
 */
class GetMyTripsTool implements Tool
{
    private const VALID_STATUSES = [
        'pending', 'accepted', 'ongoing', 'completed',
        'cancelled', 'returning', 'returned',
    ];

    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user = null,
    ) {}

    public function description(): string
    {
        return 'Look up the authenticated customer\'s ride history. Use for "show my rides", "my last trip", "how much have I spent on rides", "my cancelled trips". Supports status filtering and a summary mode for aggregate questions. Requires an authenticated user — guests are politely deflected.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter by trip status. Allowed: "pending", "accepted", "ongoing", "completed", "cancelled", "returning", "returned". Pass null for any status.')
                ->required()
                ->nullable(),
            'limit' => $schema->number()
                ->description('Number of trips to return, default 5, max 10. Ignored when summary is true.')
                ->required()
                ->nullable(),
            'summary' => $schema->boolean()
                ->description('When true, returns aggregate stats only (count + total spent) instead of a per-trip list. Use for "how much have I spent on rides" type questions.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetMyTripsTool');

        if (! $this->user) {
            return 'Please sign in to see your ride history.';
        }

        $args    = $request->all();
        $status  = isset($args['status']) && $args['status'] !== null
            ? trim((string) $args['status'])
            : null;
        $limit   = max(1, min(10, (int) ($args['limit'] ?? 5)));
        $summary = ($args['summary'] ?? null) === true;

        if ($status !== null && ! in_array($status, self::VALID_STATUSES, true)) {
            return 'Unknown trip status "' . $status . '". Try: ' . implode(', ', self::VALID_STATUSES) . '.';
        }

        // Module FK on RideRequest is set on create from the ride-share
        // Module row (RideRequest.php boot hook). Explicit filter so a
        // shared customer_id across modules can't bleed in.
        $rideShareModuleId = Module::where('module_type', 'ride-share')->value('id');

        $query = RideRequest::where('customer_id', $this->user->getKey())
            ->when($rideShareModuleId, fn ($q) => $q->where('module_id', $rideShareModuleId))
            ->when($status !== null, fn ($q) => $q->where('current_status', $status));

        if ($summary) {
            // Build a fresh chained query for the count so we don't share
            // builder state with the spend aggregation below.
            $count = (clone $query)->count();
            if ($count === 0) {
                return $status
                    ? 'No ' . $status . ' rides found.'
                    : 'You don\'t have any rides yet.';
            }
            // Sum the best-available fare column per row. We can't do this
            // in SQL cleanly because of the coalesce-with-zero rule, so
            // pull the three columns and resolve in PHP.
            $rows = (clone $query)->get(['paid_fare', 'actual_fare', 'estimated_fare']);
            $total = 0.0;
            foreach ($rows as $r) {
                $total += $this->resolveFare($r);
            }
            $statusLabel = $status ? $status . ' ' : '';
            return $count . ' ' . $statusLabel . 'ride(s) on record. Total spent: ' . round($total, 2) . '.';
        }

        $trips = $query
            ->with([
                'coordinate:id,ride_request_id,pickup_address,destination_address',
                'vehicleCategory:id,name',
                'zone:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id', 'ref_id', 'current_status',
                'estimated_fare', 'actual_fare', 'paid_fare',
                'created_at', 'vehicle_category_id', 'zone_id',
            ]);

        if ($trips->isEmpty()) {
            return $status
                ? 'No ' . $status . ' rides found.'
                : 'You don\'t have any rides yet.';
        }

        $total = 0.0;
        $lines = $trips->map(function (RideRequest $t) use (&$total) {
            $fare         = $this->resolveFare($t);
            $total       += $fare;
            $date         = $t->getAttribute('created_at')?->format('Y-m-d') ?? '—';
            $ref          = $t->getAttribute('ref_id') ?: ('#' . $t->getKey());
            $statusText   = $t->getAttribute('current_status') ?? '—';
            $category     = $t->vehicleCategory?->getAttribute('name') ?? '—';
            $pickup       = $t->coordinate?->getAttribute('pickup_address') ?? '—';
            $dropoff      = $t->coordinate?->getAttribute('destination_address') ?? '—';
            // Trim long addresses so the response stays readable.
            $route = $this->shorten($pickup) . ' → ' . $this->shorten($dropoff);
            return '• ' . $ref . ' (' . $date . ') — ' . $category . ', ' . $route
                . ': ' . round($fare, 2) . ', ' . $statusText;
        })->implode(PHP_EOL);

        $countWord  = $trips->count() === 1 ? 'ride' : 'rides';
        return $trips->count() . ' recent ' . $countWord . ':' . PHP_EOL
            . $lines . PHP_EOL
            . 'Total shown: ' . round($total, 2) . '.';
    }

    /**
     * Pick the most accurate fare available for a trip row. Lifecycle:
     *   paid_fare  → set at end of trip with coupon/discount applied
     *   actual_fare → set at trip completion before coupon
     *   estimated_fare → set at booking time
     */
    private function resolveFare(RideRequest $trip): float
    {
        $paid = (float) $trip->getAttribute('paid_fare');
        if ($paid > 0) return $paid;
        $actual = (float) $trip->getAttribute('actual_fare');
        if ($actual > 0) return $actual;
        return (float) $trip->getAttribute('estimated_fare');
    }

    private function shorten(string $address, int $maxLen = 32): string
    {
        $address = trim($address);
        if ($address === '') return '—';
        if (mb_strlen($address) <= $maxLen) return $address;
        return mb_substr($address, 0, $maxLen - 1) . '…';
    }
}
