<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\CentralLogics\StoreLogic;
use App\Models\Store;
use Modules\Rental\Entities\Vehicle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Rental-module provider lookup. Read-only — suggestion data only.
 * Registered only when the conversation's moduleType is 'rental'.
 * Use this when the user asks for "providers", "vendors", "companies",
 * "top providers", "popular providers" — NOT for individual vehicles.
 *
 * Returns text with provider name, rating, total trips and active
 * vehicle count so the LLM can compare providers meaningfully.
 */
class GetRentalProvidersTool implements Tool
{
    /**
     * @param int[] $zoneIds Overlapping zones the user falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
        private readonly array $zoneIds = [],
    ) {}

    public function description(): string
    {
        return 'Get rental PROVIDERS (vendor companies that rent out vehicles) with their rating, trip volume and active vehicle count. Use this when the user asks "providers", "top providers", "popular providers", "vendors", "rental companies" — NOT for individual vehicles. Read-only.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->description('Optional provider name keyword to filter the list')->required()->nullable(),
            'limit'   => $schema->number()->description('Number of providers to return, default 5, max 8')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args    = $request->all();
        $keyword = $args['keyword'] ?? null;
        $limit   = min((int) ($args['limit'] ?? 5), 8);

        $providerStats = Vehicle::active()
            ->selectRaw('provider_id, COUNT(*) as vehicle_count, SUM(total_trip) as total_trips')
            ->groupBy('provider_id')
            ->orderByDesc('total_trips')
            ->limit(80)
            ->get()
            ->keyBy('provider_id');

        if ($providerStats->isEmpty()) {
            $this->context->recordTool('GetRentalProvidersTool');
            return 'No rental providers available.';
        }

        // Order by trip volume from $providerStats (stores has no avg_rating column).
        $orderedIds = $providerStats->keys()->all();

        $stores = Store::whereIn('id', $orderedIds)
            ->where('status', 1)
            ->where('active', 1)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->when($keyword, fn ($q) => $q->where('name', 'LIKE', '%' . $keyword . '%'))
            ->when(!empty($this->zoneIds), fn ($q) => $q->where(function ($qq) {
                foreach ($this->zoneIds as $zid) {
                    $qq->orWhereJsonContains('pickup_zone_id', (string) $zid);
                }
            }))
            ->get(['id', 'name', 'address', 'rating'])
            ->sortBy(fn (Store $s) => array_search($s->getKey(), $orderedIds))
            ->take($limit)
            ->values();

        $this->context->recordTool('GetRentalProvidersTool');

        if ($stores->isEmpty()) {
            return 'No rental providers match those criteria.';
        }

        $lines = $stores->map(function (Store $s) use ($providerStats): string {
            $stats        = $providerStats->get($s->getKey());
            $vehicleCount = (int) ($stats->vehicle_count ?? 0);
            $totalTrips   = (int) ($stats->total_trips ?? 0);

            // Store::getRatingAttribute() already returns [r5, r4, r3, r2, r1].
            $rating  = 0.0;
            $buckets = $s->getAttribute('rating');
            if (is_array($buckets) && count($buckets) === 5 && array_sum($buckets) > 0) {
                $rating = (float) (StoreLogic::calculate_store_rating($buckets)['rating'] ?? 0);
            }

            $parts = [$s->getAttribute('name')];
            if ($rating > 0) {
                $parts[] = '★' . number_format($rating, 1);
            }
            if ($vehicleCount > 0) {
                $parts[] = $vehicleCount . ' vehicles';
            }
            if ($totalTrips > 0) {
                $parts[] = $totalTrips . ' trips';
            }
            if ($s->getAttribute('address')) {
                $parts[] = (string) $s->getAttribute('address');
            }

            return implode(' — ', $parts);
        })->all();

        return count($lines) . ' providers: ' . implode(' | ', $lines);
    }
}
