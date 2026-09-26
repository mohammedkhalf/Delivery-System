<?php

namespace Modules\ReelsModule\Support;

use App\Models\Item;
use App\Models\Store;
use Closure;

class ReelProductableResolver
{
    /**
     * Resolve the polymorphic productable (order/book target) for a reel based on
     * the store's module type. Supports three item types:
     *  - service  => Modules\Service\Entities\Service
     *  - rental   => Modules\Rental\Entities\Vehicle
     *  - product  => App\Models\Item (default / any other module)
     *
     * The item is only accepted when it actually belongs to the given store, so a
     * mismatched product_id resolves to an empty (null/null) productable.
     *
     * @param  Store|int|null  $store  Store model or store id the reel belongs to
     * @param  mixed  $productId
     * @return array{type: class-string|null, id: int|null}
     */
    public static function resolve($store, $productId): array
    {
        $productId = (int) $productId;
        $empty = ['type' => null, 'id' => null];

        if (!$productId) {
            return $empty;
        }

        $store = $store instanceof Store
            ? $store
            : Store::withoutGlobalScopes()->with('module:id,module_type')->find($store);

        if (!$store) {
            return $empty;
        }

        $moduleType = $store->module?->module_type;

        if ($moduleType === 'rental') {
            return self::match(
                \Modules\Rental\Entities\Vehicle::class,
                $productId,
                fn ($query) => $query->where('provider_id', $store->id)
            );
        }

        if ($moduleType === 'service') {
            return self::match(
                \Modules\Service\Entities\Service::class,
                $productId,
                fn ($query) => $query
                    ->where('store_id', $store->id)
                    ->when(ReelModuleConfig::isMultiModule(), fn ($q) => $q->where('module_id', (int) $store->module_id))
            );
        }

        return self::match(
            Item::class,
            $productId,
            fn ($query) => $query
                ->where('store_id', $store->id)
                ->when(ReelModuleConfig::isMultiModule(), fn ($q) => $q->where('module_id', (int) $store->module_id))
        );
    }

    /**
     * Confirm the product exists (and belongs to the store) for the given class,
     * returning the productable descriptor or an empty one.
     *
     * @return array{type: class-string|null, id: int|null}
     */
    private static function match(string $class, int $productId, Closure $constraint): array
    {
        if (!class_exists($class)) {
            return ['type' => null, 'id' => null];
        }

        $query = $class::withoutGlobalScopes()->where('id', $productId);
        $constraint($query);

        return $query->exists()
            ? ['type' => $class, 'id' => $productId]
            : ['type' => null, 'id' => null];
    }
}
