<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchProductsTool implements Tool
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
        return 'Search for items or products by name or keyword. Works across all module types (food, grocery, pharmacy, e-commerce, etc.). Returns matching active products with prices, discounts, ratings, and vendor info.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'       => $schema->string()->description('Search keyword or product name')->required(),
            'category_id' => $schema->number()->description('Filter by category ID (use GetCategoriesTool first), or null for all categories')->required()->nullable(),
            'max_price'   => $schema->number()->description('Maximum price filter, or null for no limit')->required()->nullable(),
            'veg_only'    => $schema->boolean()->description('true for veg/vegetarian items only, false for non-veg, null for all')->required()->nullable(),
            'limit'       => $schema->number()->description('Number of results to return. Default 8 for a keyword search. When the customer asks for a whole store/restaurant menu or "all items" from a category, pass a higher number (e.g. 30) — the tool returns the full menu (up to 50) for store/category matches.')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args    = $request->all();
        $query   = trim((string) ($args['query'] ?? ''));
        // Keep the requested limit raw here; the real cap is applied AFTER we
        // know the match kind — a store-name match means the customer wants the
        // whole menu, so it gets a much higher ceiling than a keyword search.
        $requestedLimit = isset($args['limit']) && $args['limit'] !== null ? (int) $args['limit'] : null;
        $limit   = min($requestedLimit ?? 8, 12);
        $maxPrice = ($args['max_price'] ?? null) !== null ? (float) $args['max_price'] : null;
        $catId   = ($args['category_id'] ?? null) !== null ? (int) $args['category_id'] : null;
        $vegOnly = ($args['veg_only'] ?? null) !== null ? (bool) $args['veg_only'] : null;

        $keywords = array_filter(array_map('trim', explode(' ', $query)));

        // "Browse everything" intent — "all foods", "show me all items", the bare
        // module-type word, etc. MUST skip the store/category cascade: a generic
        // word like "food" matches a store NAME ("Italian Fast Food", "Food Fair")
        // and wrongly narrows the result to that one store. When this is set, the
        // search returns popular items across ALL stores in the module/zone.
        $browseAll = $this->isGenericBrowse($query);

        // Auto-resolution cascade when no explicit category_id was passed.
        // Order: STORE → CATEGORY → exact-NAME → loose-NAME. Store and category
        // matches require the FULL phrase (no per-keyword OR) so a multi-word
        // query like "Buffalo Pizza" cannot accidentally fire the "Pizza"
        // category just because one keyword happens to name a category.
        $storeIds   = [];
        $autoCatIds = [];
        $matchKind  = 'name';      // strict full-phrase name match
        $looseName  = false;       // fallback flag — true after strict missed

        // For store/category matching, drop the generic module word the customer
        // often appends ("SK General Store grocery", "grocery from X"). Leaving
        // "grocery"/"food"/"items" in makes the store-name LIKE fail because the
        // store name ("Sk General Store") doesn't contain that extra word. The
        // original $query is still used for item-name search below.
        $core         = $this->coreSearchQuery($query);
        $cascadeQuery = $core !== '' ? $core : $query;

        if ($catId === null && $query !== '' && ! $browseAll) {
            $storeIds = Store::where('status', 1)
                ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
                ->when(!empty($this->zoneIds), fn ($q) => $q->whereIn('zone_id', $this->zoneIds))
                ->where('name', 'like', "%{$cascadeQuery}%")
                ->pluck('id')
                ->all();

            if (!empty($storeIds)) {
                $matchKind = 'store';
            } else {
                $autoCatIds = Category::where('status', 1)
                    ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
                    ->where('name', 'like', "%{$cascadeQuery}%")
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                if (!empty($autoCatIds)) {
                    $matchKind = 'category';
                }
            }
        }

        // A store/category match — or a "show me everything" browse — is a
        // "give me the full list" request, so raise the ceiling. A plain keyword
        // search stays tight to avoid a wall of results.
        if ($browseAll || $matchKind === 'store' || $matchKind === 'category') {
            $limit = min($requestedLimit ?? 30, 50);
        }

        $runQuery = function (bool $loose) use (
            $catId, $maxPrice, $vegOnly, $matchKind, $storeIds, $autoCatIds,
            $keywords, $query, $limit, $browseAll
        ) {
            return Item::active()
                ->with([
                    'store:id,name,logo,rating,delivery_time,minimum_order,free_delivery,zone_id',
                    'category:id,name',
                ])
                ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
                ->when(!empty($this->zoneIds), fn ($q) => $q->whereHas('store', fn ($s) => $s->whereIn('zone_id', $this->zoneIds)))
                ->when($catId, fn ($q) => $q->where(function ($c) use ($catId) {
                    $c->where('category_id', $catId)
                        ->orWhereJsonContains('category_ids', ['id' => (string) $catId])
                        ->orWhereHas('category', fn ($cat) => $cat->where('parent_id', $catId));
                }))
                ->when($maxPrice !== null, fn ($q) => $q->where('price', '<=', $maxPrice))
                ->when($vegOnly !== null, fn ($q) => $q->type($vegOnly ? 'veg' : 'non_veg'))
                ->when($matchKind === 'store', fn ($q) => $q->whereIn('store_id', $storeIds))
                ->when($matchKind === 'category', fn ($q) => $q->where(function ($c) use ($autoCatIds) {
                    foreach ($autoCatIds as $cid) {
                        $c->orWhere('category_id', (int) $cid)
                            ->orWhereJsonContains('category_ids', ['id' => $cid]);
                    }
                }))
                ->when($matchKind === 'name' && ! $loose && ! $browseAll, fn ($q) => $q->where('name', 'like', "%{$query}%"))
                ->when($matchKind === 'name' && $loose, fn ($q) => $q->where(function ($c) use ($keywords, $query) {
                    $c->where('name', 'like', "%{$query}%");
                    foreach ($keywords as $kw) {
                        if (mb_strlen($kw) >= 3) {
                            $c->orWhere('name', 'like', "%{$kw}%");
                        }
                    }
                }))
                ->orderByDesc('order_count')
                ->limit($limit)
                ->get([
                    'id', 'name', 'price', 'discount', 'discount_type',
                    'avg_rating', 'rating_count', 'order_count', 'image',
                    'store_id', 'module_id', 'category_id', 'veg', 'recommended', 'stock',
                    'maximum_cart_quantity', 'variations', 'food_variations', 'choice_options',
                ]);
        };

        $items = $runQuery(false);
        if ($items->isEmpty() && $matchKind === 'name' && count($keywords) > 1) {
            $items     = $runQuery(true);
            $looseName = true;
        }

        $products = $items->map($this->format(...))->values()->all();
        $this->context->recordTool('SearchProductsTool');
        $this->context->addProducts($products);

        $matchedAs = match (true) {
            $matchKind === 'store'              => ' (matched store name)',
            $matchKind === 'category'           => ' (matched category name)',
            $matchKind === 'name' && $looseName => ' (no exact item — showing related items by keyword)',
            default                             => '',
        };

        if (count($products) === 0) {
            return $browseAll
                ? 'No items are available right now.'
                : "No products found for \"{$query}\"{$matchedAs}.";
        }

        $lines = implode('; ', array_map(function (array $p): string {
            $base = $p['name'] . ' [ID:' . $p['id'] . '] ' . $p['discounted_price'] . ($p['veg'] ? ' (veg)' : '');
            if (!empty($p['variation_labels'])) {
                $base .= ' [variations:' . implode('/', $p['variation_labels']) . ']';
            }
            return $base;
        }, $products));

        if ($browseAll) {
            return count($products) . ' item(s) available across stores: ' . $lines;
        }

        return count($products) . ' product(s) found for "' . $query . '"' . $matchedAs . ': ' . $lines;
    }

    /**
     * "Browse everything" intent — "all foods", "show me all items", "any food",
     * the bare module-type word, etc. Such queries must NOT hit the store/category
     * cascade, because a word like "food" matches a store name ("Italian Fast
     * Food", "Food Fair") and wrongly narrows the result to that one store.
     * Returns true → the search returns popular items across ALL stores.
     */
    private function isGenericBrowse(string $query): bool
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return true;
        }
        // Drop scattered filler words ("show me all the …").
        $q = preg_replace('/\b(show|me|give|list|all|any|some|the|please|see|view|browse|available)\b/u', ' ', $q) ?? $q;
        $q = trim(preg_replace('/\s+/', ' ', $q) ?? '');

        $generic = [
            '', 'food', 'foods', 'item', 'items', 'product', 'products',
            'dish', 'dishes', 'meal', 'meals', 'menu', 'menus',
            'grocery', 'groceries', 'medicine', 'medicines', 'drug', 'drugs',
            'everything', 'anything', 'stuff', 'option', 'options',
        ];
        return in_array($q, $generic, true);
    }

    /**
     * Strip generic module words + filler so a "<store name> + type" phrase still
     * resolves to the store. "sk general store grocery" → "sk general store";
     * "grocery from FreshMart" → "fresh mart"-ish core. Only used for the
     * store/category cascade — item-name search keeps the original query.
     * Returns '' when nothing specific remains (caller falls back to $query).
     */
    private function coreSearchQuery(string $query): string
    {
        $strip = [
            'food', 'foods', 'grocery', 'groceries', 'item', 'items',
            'product', 'products', 'medicine', 'medicines', 'drug', 'drugs',
            'dish', 'dishes', 'meal', 'meals', 'menu', 'menus',
            'give', 'me', 'from', 'show', 'all', 'the', 'please', 'for',
            'some', 'any', 'a', 'an', 'of', 'in', 'my',
        ];
        $tokens = array_filter(array_map('trim', preg_split('/\s+/', strtolower($query)) ?: []));
        $kept   = array_values(array_filter($tokens, fn ($t) => $t !== '' && !in_array($t, $strip, true)));
        return trim(implode(' ', $kept));
    }

    private function parseVariations(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        $decoded = \is_array($raw) ? $raw : json_decode($raw, true);
        return \is_array($decoded) ? $decoded : [];
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
            'id'                   => $item->getKey(),
            'name'                 => $item->getAttribute('name'),
            'price'                => $price,
            'discounted_price'     => $discountedPrice,
            'discount'             => $discount,
            'discount_type'        => $discountType,
            'avg_rating'           => (float) $item->getAttribute('avg_rating'),
            'rating_count'         => (int) $item->getAttribute('rating_count'),
            'order_count'          => (int) $item->getAttribute('order_count'),
            'image'                => $item->getAttribute('image'),
            'image_full_url'       => $item->image_full_url,
            'veg'                  => (bool) $item->getAttribute('veg'),
            'recommended'          => (bool) $item->getAttribute('recommended'),
            'stock'                => (int) $item->getAttribute('stock'),
            'maximum_cart_quantity'=> (int) $item->getAttribute('maximum_cart_quantity'),
            'store_id'             => (int) $item->getAttribute('store_id'),
            'module_id'            => (int) $item->getAttribute('module_id'),
            'store_name'           => $item->store?->getAttribute('name'),
            'store_logo'           => $item->store?->getAttribute('logo'),
            'store_logo_full_url'  => $item->store?->logo_full_url,
            'store_avg_rating'     => $item->store?->getAttribute('rating'),
            'delivery_time'        => $item->store?->getAttribute('delivery_time'),
            'minimum_order'        => $item->store?->getAttribute('minimum_order'),
            'free_delivery'        => (bool) $item->store?->getAttribute('free_delivery'),
            'category_id'          => (int) $item->getAttribute('category_id'),
            'category_name'        => $item->category?->getAttribute('name'),
            'variations'           => $this->parseVariations($item->getAttribute('variations')),
            'food_variations'      => $this->parseVariations($item->getAttribute('food_variations')),
            'variation_labels'     => $this->variationLabels($item),
            'choice_options'       => $this->parseVariations($item->getAttribute('choice_options')),
        ];
    }

    /**
     * Flat list of selectable option labels for the model-facing search line
     * and the AiChatService context hint — works for BOTH variation systems:
     *   - non-food `variations`:      [{type, price}]            → ["Large", "Small"]
     *   - food `food_variations`:     [{name, values:[{label}]}] → ["Large", "Spicy"]
     *
     * @return string[]
     */
    private function variationLabels(Item $item): array
    {
        $labels = array_filter(array_column(
            $this->parseVariations($item->getAttribute('variations')),
            'type'
        ));

        foreach ($this->parseVariations($item->getAttribute('food_variations')) as $group) {
            foreach (($group['values'] ?? []) as $val) {
                if (!empty($val['label'])) {
                    $labels[] = (string) $val['label'];
                }
            }
        }

        return array_values(array_unique(array_map('strval', $labels)));
    }
}
