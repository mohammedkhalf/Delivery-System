<?php

namespace App\Builder\Resources;

use App\Builder\Support\ItemPricing;
use App\Models\Item;
use Modules\Builder\ValueObjects\Storefront\ItemCardDTO;

/**
 * Canonical transformer that maps an App\Models\Item into the exact shape
 * Modules/Builder/resources/js/Components/shared/ItemCard.jsx consumes.
 *
 * Covers the union of all 8 CARD_TYPE variants:
 *   id, name, slug, image, price (final), oldPrice (original), discountPercent,
 *   rating, rating_count, currency, isVeg, isNonVeg, inCart, cartQty, isWishlist.
 *
 * Accepts a $context array so callers can inject:
 *   - 'currency'         => string symbol (default '$')
 *   - 'cart_lookup'      => callable(int $itemId): ?array  // e.g. fn($id) => $cart[$id] ?? null
 *   - 'wishlist_lookup'  => callable(int $itemId): bool
 */
class ItemCardResource
{
    public static function fromCollection(iterable $items, array $context = []): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items);

        // Show the LIVE review average on the card instead of the denormalized
        // `avg_rating` column. That column is maintained incrementally from the
        // `rating` JSON distribution and drifts out of sync when a review is
        // deleted/edited (the removed review lingers in the JSON), so it can
        // disagree with the real reviews the admin panel counts. One aggregate
        // query for the whole collection keeps this O(1), not N+1.
        if (! isset($context['rating_lookup'])) {
            $context['rating_lookup'] = self::liveRatingLookup(
                array_values(array_filter(array_map(
                    static fn ($it) => isset($it->id) ? (int) $it->id : null,
                    $items
                )))
            );
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = self::fromOne($item, $context);
        }
        return $result;
    }

    /**
     * Batch-load AVG(rating)/COUNT(*) from the reviews table for the given item
     * ids and return a lookup closure: fn(int $id): ?array{rating,count}. Uses
     * the Review model so any global scopes match what the admin panel counts.
     */
    private static function liveRatingLookup(array $itemIds): callable
    {
        $ratings = [];
        if ($itemIds !== []) {
            $rows = \App\Models\Review::query()
                ->whereIn('item_id', $itemIds)
                ->groupBy('item_id')
                ->selectRaw('item_id, AVG(rating) as avg_rating, COUNT(*) as rating_count')
                ->get();
            foreach ($rows as $row) {
                $ratings[(int) $row->item_id] = [
                    'rating' => round((float) $row->avg_rating, 1),
                    'count'  => (int) $row->rating_count,
                ];
            }
        }

        return static fn (int $id): ?array => $ratings[$id] ?? null;
    }

    public static function fromOne(Item $item, array $context = []): array
    {
        $pricing = ItemPricing::compute($item);

        // The card needs one boolean to route Add-to-Cart: does this item
        // require user choices before it can land in the cart?
        //   - food: only when at least one food_variation has required='on'.
        //     Optional add-ons / optional variations don't force the modal.
        //   - non-food: when the item has any catalog variations rows.
        // Required variations always force the modal, regardless of module type
        // or whether the `module` relation resolved — a food item whose module_id
        // didn't hydrate must still be detected. Non-food items additionally force
        // it when they carry any catalog variations.
        $moduleType = $item->module?->module_type;
        $isFood = $moduleType === 'food';
        $needsConfig = self::hasRequiredVariation($item)
            || (!$isFood && self::hasNonFoodVariations($item));

        // Veg/non-veg badge visibility is gated by THREE things, not just the
        // item's own flag:
        //   1. The module supports veg_non_veg (only `food` in config/module.php).
        //   2. The store toggles `veg` / `non_veg` ON in vendor business settings.
        //      Vendors who flip "veg" off should not see veg badges on their items.
        //   3. The item itself has the veg flag set (1 = veg, 0 = non-veg).
        // Raw attribute read (not cast) so a null `veg` doesn't coerce to 0 and
        // falsely flag every grocery/pharmacy item as non-veg.
        $vegRaw            = $item->getAttributes()['veg'] ?? null;
        $moduleAllowsVeg   = (bool) config("module.{$moduleType}.veg_non_veg", false);
        $storeAllowsVeg    = (int) ($item->store?->veg ?? 0) === 1;
        $storeAllowsNonVeg = (int) ($item->store?->non_veg ?? 0) === 1;
        $isVeg    = $moduleAllowsVeg && $storeAllowsVeg    && $vegRaw !== null && (int) $vegRaw === 1;
        $isNonVeg = $moduleAllowsVeg && $storeAllowsNonVeg && $vegRaw !== null && (int) $vegRaw === 0;

        // Inventory cap for the card's +/- stepper. Only modules with the
        // `stock` capability track it (config/module.php — `food` does not, so
        // its rows sit at 0 without being depleted); when untracked the card
        // must not cap anything.
        $tracksStock = (bool) config("module.{$moduleType}.stock", false);
        $stock       = $tracksStock ? (int) ($item->getAttributes()['stock'] ?? 0) : 0;

        $currency       = $context['currency']        ?? '$';
        $cartLookup     = $context['cart_lookup']     ?? null;
        $wishlistLookup = $context['wishlist_lookup'] ?? null;
        $ratingLookup   = $context['rating_lookup']   ?? null;

        $cartEntry = is_callable($cartLookup) ? $cartLookup($item->id) : null;

        // Prefer the live review aggregate (batched in fromCollection); fall back
        // to the stored column for an item with no reviews or a single-item call.
        $liveRating  = is_callable($ratingLookup) ? $ratingLookup($item->id) : null;
        $rating      = $liveRating['rating'] ?? round((float) ($item->avg_rating ?? 0), 1);
        $ratingCount = $liveRating['count']  ?? (int) ($item->rating_count ?? 0);


        $data = [
            'id'              => $item->id,
            'name'            => $item->name,
            'slug'            => $item->slug ?? null,
            'image'           => $item->image_full_url ?? null,
            'price'           => $pricing['price'],
            'oldPrice'        => $pricing['oldPrice'],
            'discountPercent' => $pricing['discountPercent'],
            // Flat/amount discounts: the storefront's useDiscountLabel() reads
            // discountType + discountAmount to show "$X off" instead of a %.
            'discountAmount'  => $pricing['discountAmount'],
            'discountType'    => $pricing['discountType'],
            'discountSource'  => $pricing['discountSource'],
            'rating'          => $rating,
            'ratingCount'     => $ratingCount,
            'currency'        => $currency,
            'isVeg'           => $isVeg,
            'isNonVeg'        => $isNonVeg,
            'inCart'          => $cartEntry !== null,
            'cartQty'         => (int) ($cartEntry['qty'] ?? 0),
            'isWishlist'      => is_callable($wishlistLookup) ? (bool) $wishlistLookup($item->id) : false,
            'moduleType'      => $moduleType,
            'needsConfig'     => $needsConfig,
            'stock'           => $stock,
            'tracksStock'     => $tracksStock,
        ];

        return ItemCardDTO::fromArray($data)->toArray();
    }

    private static function hasRequiredVariation(Item $item): bool
    {
        // Check every known variation column so requiredness never depends on
        // which column an install stores it in (`food_variations` for food,
        // `variations` for the legacy/non-food shape).
        foreach (['food_variations', 'variations'] as $column) {
            foreach (self::decodeJsonField($item->getAttributes()[$column] ?? null) as $variation) {
                $required = $variation['required'] ?? 'off';
                if ($required === 'on' || $required === '1' || $required === 1 || $required === true) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function hasNonFoodVariations(Item $item): bool
    {
        $variations = self::decodeJsonField($item->getAttributes()['variations'] ?? null);
        return is_array($variations) && count($variations) > 0;
    }

    private static function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
