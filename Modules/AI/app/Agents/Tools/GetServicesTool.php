<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\Service\Entities\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Service-module service suggestions. Read-only — listings only, no booking.
 * Registered only when the conversation's moduleType is 'service'.
 *
 * Returns a text summary (no product cards) listing name, category, effective
 * price (with original when discounted), rating, booking count and provider
 * for each service, so the LLM can answer "cleaning services", "best rated
 * services", "AC repair under 1000" etc. with real numbers.
 */
class GetServicesTool implements Tool
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
        return 'Suggest bookable services for the customer with category, price, rating and provider. Read-only — listings only, you cannot book through this tool. Use filters to narrow results when the user mentions a category, price ceiling, or keyword.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword'     => $schema->string()->description('Optional name keyword to match')->required()->nullable(),
            'category'    => $schema->string()->description('Optional category NAME to filter by, e.g. "AC & Appliance Repair" (use this for "services in <category>" drill-downs)')->required()->nullable(),
            'category_id' => $schema->number()->description('Optional service Category id to filter by (matches main or sub category)')->required()->nullable(),
            'max_price'   => $schema->number()->description('Maximum base price the user is willing to pay')->required()->nullable(),
            'sort'        => $schema->string()->description('Sort order: "rating" (default — best rated first), "popular" (most booked), "cheapest"')->required()->nullable(),
            'limit'       => $schema->number()->description('Number of services to return, default 5, max 8')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args         = $request->all();
        $keyword      = $args['keyword']     ?? null;
        $categoryName = $args['category']    ?? null;
        $categoryId   = $args['category_id'] ?? null;
        $maxPrice     = $args['max_price']   ?? null;
        $sort       = $args['sort']        ?? 'rating';
        $limit      = min((int) ($args['limit'] ?? 5), 8);

        $query = Service::active($this->zoneIds ?: null, $this->moduleId)
            ->with(['category:id,name', 'store:id,name'])
            ->when($keyword, function ($q) use ($keyword) {
                $keys = array_filter(array_map('trim', explode(' ', $keyword)));
                $q->where(function ($qq) use ($keys, $keyword) {
                    $qq->where('name', 'LIKE', "%{$keyword}%");
                    foreach ($keys as $kw) {
                        $qq->orWhere('name', 'LIKE', "%{$kw}%");
                    }
                });
            })
            ->when($categoryId, fn ($q) => $q->where(function ($qq) use ($categoryId) {
                $qq->where('category_id', (int) $categoryId)
                    ->orWhere('sub_category_id', (int) $categoryId);
            }))
            ->when($categoryName, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', 'LIKE', '%' . $categoryName . '%')))
            ->when($maxPrice !== null, fn ($q) => $q->where('base_price', '<=', (float) $maxPrice)->where('base_price', '>', 0));

        $query = match ($sort) {
            'popular'  => $query->orderByDesc('order_count')->orderByDesc('avg_rating'),
            'cheapest' => $query->orderBy('base_price'),
            default    => $query->orderByDesc('avg_rating')->orderByDesc('order_count'),
        };

        $services = $query->limit($limit)->get([
            'id', 'name', 'category_id', 'sub_category_id', 'store_id',
            'base_price', 'discount', 'discount_type', 'avg_rating', 'order_count',
        ]);

        $this->context->recordTool('GetServicesTool');

        if ($services->isEmpty()) {
            return 'No services match those criteria.';
        }

        $lines = $services->map(function (Service $s): string {
            $name     = trim((string) $s->getAttribute('name'));
            $rating   = (float) $s->getAttribute('avg_rating');
            $bookings = (int) $s->getAttribute('order_count');
            $category = $s->category?->name;
            $provider = $s->store?->getAttribute('name');

            $base     = (float) $s->getAttribute('base_price');
            $discount = (float) $s->getAttribute('discount');
            $price    = $base;
            if ($discount > 0) {
                $price = $s->getAttribute('discount_type') === 'percent'
                    ? max(0.0, $base - ($base * $discount / 100))
                    : max(0.0, $base - $discount);
            }

            $parts = [$name];
            if ($category) {
                $parts[] = '(' . $category . ')';
            }
            if ($base > 0) {
                $parts[] = ($discount > 0 && $price < $base)
                    ? (float) $price . ' (was ' . (float) $base . ')'
                    : (string) (float) $base;
            }
            if ($rating > 0) {
                $parts[] = '★' . number_format($rating, 1) . ($bookings > 0 ? ' (' . $bookings . ' bookings)' : '');
            } elseif ($bookings > 0) {
                $parts[] = $bookings . ' bookings';
            }
            if ($provider) {
                $parts[] = 'by ' . $provider;
            }

            return implode(' — ', $parts);
        })->all();

        return count($lines) . ' services: ' . implode(' | ', $lines);
    }
}
