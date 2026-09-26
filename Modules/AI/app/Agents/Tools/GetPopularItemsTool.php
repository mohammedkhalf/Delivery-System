<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPopularItemsTool implements Tool
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
        return 'Get the most popular and top-ordered items or products on the platform, sorted by order count. Use this for "what is popular", "best sellers", or "trending" queries.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'veg_only' => $schema->boolean()->description('true for veg items only, false for non-veg, null for all')->required()->nullable(),
            'limit'    => $schema->number()->description('Number of items to return, default 8, or null for default')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args    = $request->all();
        $limit   = min((int) ($args['limit'] ?? 8), 10);
        $vegOnly = ($args['veg_only'] ?? null) !== null ? (bool) $args['veg_only'] : null;

        $items = Item::active()
            ->popular()
            ->with([
                'store:id,name,logo,rating,delivery_time,minimum_order,free_delivery',
                'category:id,name',
            ])
            ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
            ->when(!empty($this->zoneIds), fn ($q) => $q->whereHas('store', fn ($s) => $s->whereIn('zone_id', $this->zoneIds)))
            ->when($vegOnly !== null, fn ($q) => $q->type($vegOnly ? 'veg' : 'non_veg'))
            ->limit($limit)
            ->get([
                'id', 'name', 'price', 'discount', 'discount_type',
                'avg_rating', 'rating_count', 'order_count', 'image',
                'store_id', 'module_id', 'category_id', 'veg', 'recommended', 'stock',
                'variations', 'choice_options',
            ]);

        $products = $items->map($this->format(...))->values()->all();
        $this->context->recordTool('GetPopularItemsTool');
        $this->context->addProducts($products);

        if (count($products) === 0) {
            return 'No popular items found.';
        }

        $lines = implode('; ', array_map(function (array $p): string {
            $base = $p['name'] . ' [ID:' . $p['id'] . '] ' . $p['discounted_price'] . ' (ordered ' . $p['order_count'] . 'x)';
            if (!empty($p['variations'])) {
                $types = implode('/', array_column($p['variations'], 'type'));
                $base .= ' [variations:' . $types . ']';
            }
            return $base;
        }, $products));

        return 'Top ' . count($products) . ' popular items: ' . $lines;
    }

    private function format(Item $item): array
    {
        $price        = (float) $item->getAttribute('price');
        $discount     = (float) $item->getAttribute('discount');
        $discountType = (string) $item->getAttribute('discount_type');

        $discountedPrice = $discount > 0
            ? ($discountType === 'percent'
                ? round($price - $price * $discount / 100, 2)
                : round($price - $discount, 2))
            : $price;

        return [
            'id'               => $item->getKey(),
            'name'             => $item->getAttribute('name'),
            'price'            => $price,
            'discounted_price' => $discountedPrice,
            'discount'         => $discount,
            'discount_type'    => $discountType,
            'avg_rating'       => (float) $item->getAttribute('avg_rating'),
            'rating_count'     => (int) $item->getAttribute('rating_count'),
            'order_count'      => (int) $item->getAttribute('order_count'),
            'image'            => $item->getAttribute('image'),
            'image_full_url'   => $item->image_full_url,
            'veg'              => (bool) $item->getAttribute('veg'),
            'recommended'      => (bool) $item->getAttribute('recommended'),
            'stock'            => (int) $item->getAttribute('stock'),
            'store_id'         => (int) $item->getAttribute('store_id'),
            'module_id'        => (int) $item->getAttribute('module_id'),
            'store_name'       => $item->store?->getAttribute('name'),
            'store_logo'       => $item->store?->getAttribute('logo'),
            'store_logo_full_url' => $item->store?->logo_full_url,
            'store_avg_rating' => $item->store?->getAttribute('rating'),
            'delivery_time'    => $item->store?->getAttribute('delivery_time'),
            'minimum_order'    => $item->store?->getAttribute('minimum_order'),
            'free_delivery'    => (bool) $item->store?->getAttribute('free_delivery'),
            'category_id'      => (int) $item->getAttribute('category_id'),
            'category_name'    => $item->category?->getAttribute('name'),
            'variations'       => $this->parseVariations($item->getAttribute('variations')),
        ];
    }

    private function parseVariations(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        $decoded = \is_array($raw) ? $raw : json_decode((string) $raw, true);
        return \is_array($decoded) ? $decoded : [];
    }
}
