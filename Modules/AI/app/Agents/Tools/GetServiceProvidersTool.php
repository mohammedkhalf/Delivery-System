<?php

namespace Modules\AI\app\Agents\Tools;

use App\CentralLogics\StoreLogic;
use App\Models\Store;
use Modules\AI\app\Agents\AiResponseContext;
use Modules\Service\Entities\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Service-module provider lookup. Read-only — suggestion data only.
 * Registered only when the conversation's moduleType is 'service'.
 * Use this when the user asks for "providers", "vendors", "companies",
 * "professionals", "top providers" — NOT for individual services.
 *
 * Returns text with provider name, rating, booking volume and active
 * service count so the LLM can compare providers meaningfully.
 */
class GetServiceProvidersTool implements Tool
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
        return 'Get service PROVIDERS (vendor companies / professionals that offer services) with their rating, booking volume and active service count. Use this when the user asks "providers", "top providers", "popular providers", "vendors", "companies", "professionals" — NOT for individual services. Read-only.';
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

        $providerStats = Service::where('status', 1)
            ->where('is_approved', 1)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->selectRaw('store_id, COUNT(*) as service_count, SUM(order_count) as total_bookings')
            ->groupBy('store_id')
            ->orderByDesc('total_bookings')
            ->limit(80)
            ->get()
            ->keyBy('store_id');

        if ($providerStats->isEmpty()) {
            $this->context->recordTool('GetServiceProvidersTool');
            return 'No service providers available.';
        }

        // Order by booking volume from $providerStats (stores has no avg_rating column).
        $orderedIds = $providerStats->keys()->all();

        $stores = Store::whereIn('id', $orderedIds)
            ->where('status', 1)
            ->where('active', 1)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->when($keyword, fn ($q) => $q->where('name', 'LIKE', '%' . $keyword . '%'))
            ->when(!empty($this->zoneIds), fn ($q) => $q->whereIn('zone_id', $this->zoneIds))
            ->get(['id', 'name', 'address', 'rating', 'zone_id'])
            ->sortBy(fn (Store $s) => array_search($s->getKey(), $orderedIds))
            ->take($limit)
            ->values();

        $this->context->recordTool('GetServiceProvidersTool');

        if ($stores->isEmpty()) {
            return 'No service providers match those criteria.';
        }

        $lines = $stores->map(function (Store $s) use ($providerStats): string {
            $stats         = $providerStats->get($s->getKey());
            $serviceCount  = (int) ($stats->service_count ?? 0);
            $totalBookings = (int) ($stats->total_bookings ?? 0);

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
            if ($serviceCount > 0) {
                $parts[] = $serviceCount . ' services';
            }
            if ($totalBookings > 0) {
                $parts[] = $totalBookings . ' bookings';
            }
            if ($s->getAttribute('address')) {
                $parts[] = (string) $s->getAttribute('address');
            }

            return implode(' — ', $parts);
        })->all();

        return count($lines) . ' providers: ' . implode(' | ', $lines);
    }
}
