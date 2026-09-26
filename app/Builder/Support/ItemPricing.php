<?php

namespace App\Builder\Support;

use App\CentralLogics\Helpers;
use App\Models\Item;

/**
 * Single source of truth for storefront prices and discounts.
 *
 * Wraps Helpers::product_discount_calculate (which already considers flash
 * sales, store-wide discounts, and product-level discounts and picks the
 * highest applicable one) so every Builder resource — card list, detail
 * page, food modal, quick-view modal, per-variation combination — emits
 * the same numbers. Without this, lists computed discount differently
 * from details/modal/cart and prices visibly drifted between views.
 *
 * Pass `$basePrice` to compute pricing for a specific variation row;
 * otherwise the item's base price is used.
 */
final class ItemPricing
{
    public static function compute(Item $item, ?float $basePrice = null): array
    {
        $base = $basePrice ?? (float) ($item->price ?? 0);
        $store = $item->store ?? null;
        $calc = Helpers::product_discount_calculate($item, $base, $store, true);

        $discountAmount = (float) ($calc['discount_amount'] ?? 0);
        $final = max(0.0, $base - $discountAmount);
        $percentOff = $base > 0
            ? (int) round((1 - ($final / $base)) * 100)
            : 0;

        $source = $calc['discount_type'] ?? null;

        return [
            'price'           => $final,
            'oldPrice'        => $base,
            'discountAmount'  => $discountAmount,
            'discountPercent' => $percentOff,
            'discountType'    => $calc['original_discount_type'] ?? 'percent',
            'discountSource'  => $source, // 'flash_sale' | 'store_discount' | 'product_discount' | null
            'isFlashSale'     => $source === 'flash_sale',
            'isStoreDiscount' => $source === 'store_discount',
        ];
    }
}
