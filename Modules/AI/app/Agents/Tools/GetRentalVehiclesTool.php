<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\Rental\Entities\Vehicle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Rental-module vehicle suggestions. Read-only — listings only, no booking.
 * Registered only when the conversation's moduleType is 'rental'.
 *
 * Returns a text summary (no product cards) listing brand+name, supported
 * pricing models with prices, rating, trip count and provider for each
 * vehicle, so the LLM can answer "best vehicles", "show me sedans under
 * 800/hour", "BMWs available" etc. with real numbers.
 */
class GetRentalVehiclesTool implements Tool
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
        return 'Suggest rental vehicles for the customer with brand, prices (hourly/daily/distance), rating and provider. Read-only — listings only, you cannot book through this tool. Use filters to narrow results when the user mentions a brand, category, price ceiling, or pricing model.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword'      => $schema->string()->description('Optional name/brand keyword to match')->required()->nullable(),
            'brand_id'     => $schema->number()->description('Optional VehicleBrand id to filter by')->required()->nullable(),
            'category_id'  => $schema->number()->description('Optional VehicleCategory id to filter by')->required()->nullable(),
            'pricing_type' => $schema->string()->description('Preferred pricing model the user mentioned: "hourly", "daily", or "distance"')->required()->nullable(),
            'max_price'    => $schema->number()->description('Maximum price the user is willing to pay (interpreted in the pricing_type unit)')->required()->nullable(),
            'sort'         => $schema->string()->description('Sort order: "rating" (default — best rated first), "trips" (most rented), "cheapest"')->required()->nullable(),
            'limit'        => $schema->number()->description('Number of vehicles to return, default 5, max 8')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args        = $request->all();
        $keyword     = $args['keyword']      ?? null;
        $brandId     = $args['brand_id']     ?? null;
        $categoryId  = $args['category_id']  ?? null;
        $pricingType = $args['pricing_type'] ?? null;
        $maxPrice    = $args['max_price']    ?? null;
        $sort        = $args['sort']         ?? 'rating';
        $limit       = min((int) ($args['limit'] ?? 5), 8);

        $priceColumn = match ($pricingType) {
            'hourly'   => 'hourly_price',
            'daily'    => 'day_wise_price',
            'distance' => 'distance_price',
            default    => null,
        };

        $query = Vehicle::active()
            ->with(['brand:id,name', 'category:id,name', 'provider:id,name'])
            ->when($keyword, function ($q) use ($keyword) {
                $keys = array_filter(array_map('trim', explode(' ', $keyword)));
                $q->where(function ($qq) use ($keys, $keyword) {
                    $qq->where('name', 'LIKE', "%{$keyword}%")
                        ->orWhere('tag', 'LIKE', "%{$keyword}%");
                    foreach ($keys as $kw) {
                        $qq->orWhere('name', 'LIKE', "%{$kw}%");
                    }
                });
            })
            ->when($brandId, fn ($q) => $q->where('brand_id', (int) $brandId))
            ->when($categoryId, fn ($q) => $q->where('category_id', (int) $categoryId))
            ->when($priceColumn && $maxPrice !== null, fn ($q) => $q->where($priceColumn, '<=', (float) $maxPrice)->where($priceColumn, '>', 0))
            // pickup_zone_id is a JSON column on Provider — match if any of
            // the user's overlapping zones is in that list.
            ->when(!empty($this->zoneIds), function ($q) {
                $q->whereHas('provider', function ($p) {
                    $p->where(function ($pp) {
                        foreach ($this->zoneIds as $zid) {
                            $pp->orWhereJsonContains('pickup_zone_id', (string) $zid);
                        }
                    });
                });
            });

        $query = match ($sort) {
            'trips'    => $query->orderByDesc('total_trip')->orderByDesc('avg_rating'),
            'cheapest' => $priceColumn
                ? $query->orderBy($priceColumn)
                : $query->orderByRaw('LEAST(
                    CASE WHEN hourly_price IS NULL OR hourly_price = 0 THEN 999999999 ELSE hourly_price END,
                    CASE WHEN distance_price IS NULL OR distance_price = 0 THEN 999999999 ELSE distance_price END,
                    CASE WHEN day_wise_price IS NULL OR day_wise_price = 0 THEN 999999999 ELSE day_wise_price END
                ) ASC'),
            default    => $query->orderByDesc('avg_rating')->orderByDesc('total_trip'),
        };

        $vehicles = $query->limit($limit)->get([
            'id', 'name', 'brand_id', 'category_id', 'provider_id',
            'hourly_price', 'day_wise_price', 'distance_price', 'discount_price',
            'trip_hourly', 'trip_day_wise', 'trip_distance',
            'avg_rating', 'total_trip', 'seating_capacity', 'air_condition',
            'transmission_type', 'fuel_type',
        ]);

        $this->context->recordTool('GetRentalVehiclesTool');

        if ($vehicles->isEmpty()) {
            return 'No rental vehicles match those criteria.';
        }

        $lines = $vehicles->map(function (Vehicle $v): string {
            $brand    = $v->brand?->name;
            $name     = trim(($brand ? $brand . ' ' : '') . $v->getAttribute('name'));
            $rating   = (float) $v->getAttribute('avg_rating');
            $trips    = (int) $v->getAttribute('total_trip');
            $category = $v->category?->name;
            $provider = $v->provider?->getAttribute('name');

            $prices = [];
            if ((int) $v->getAttribute('trip_hourly') === 1 && (float) $v->getAttribute('hourly_price') > 0) {
                $prices[] = $v->getAttribute('hourly_price') . '/hr';
            }
            if ((int) $v->getAttribute('trip_day_wise') === 1 && (float) $v->getAttribute('day_wise_price') > 0) {
                $prices[] = $v->getAttribute('day_wise_price') . '/day';
            }
            if ((int) $v->getAttribute('trip_distance') === 1 && (float) $v->getAttribute('distance_price') > 0) {
                $prices[] = $v->getAttribute('distance_price') . '/km';
            }

            $parts = [$name];
            if ($category) {
                $parts[] = '(' . $category . ')';
            }
            if (!empty($prices)) {
                $parts[] = implode(', ', $prices);
            }
            if ($rating > 0) {
                $parts[] = '★' . number_format($rating, 1) . ($trips > 0 ? ' (' . $trips . ' trips)' : '');
            } elseif ($trips > 0) {
                $parts[] = $trips . ' trips';
            }
            if ($provider) {
                $parts[] = 'by ' . $provider;
            }

            return implode(' — ', $parts);
        })->all();

        return count($lines) . ' vehicles: ' . implode(' | ', $lines);
    }
}
