<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Builder\Contracts\CartProvider as CartProviderContract;
use Modules\Builder\Services\StorefrontContext;

class CartProvider implements CartProviderContract
{
    private const MODEL_MAP = [
        'Item'         => Item::class,
        'ItemCampaign' => ItemCampaign::class,
    ];

    public function __construct(private StorefrontContext $context) {}

    public function list(): array
    {
        $this->healMissingStoreId();
        $rows = $this->baseQuery()->get()->map(fn ($row) => $this->formatRow($row))->values()->all();
        return $this->withTotals($rows);
    }

    /**
     * Backfill `store_id` on this shopper's rows that predate the column
     * being persisted (or were written by an older build). The host tax and
     * coupon pipelines filter the cart by `store_id`, so a NULL there makes
     * them read an empty cart and silently return zero tax. We only touch
     * rows whose item actually belongs to the active store, so a multi-store
     * shopper never gets another store's rows reassigned. Idempotent and a
     * cheap no-op once healed.
     */
    private function healMissingStoreId(): void
    {
        [$shopperId, $isGuest, $moduleId, $storeId] = $this->shopperContext();
        if ($shopperId === null || $moduleId === null || $storeId === null) {
            return;
        }

        $ids = Cart::query()
            ->where('user_id', $shopperId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereNull('store_id')
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Cart::query()->whereIn('id', $ids)->update(['store_id' => (int) $storeId]);
        }
    }

    /**
     * Add (upsert) an item to the cart. Price comes from the client — same
     * convention as the existing API CartController. When a line with the
     * same combo (item_id, item_type, variation, add_on_ids, add_on_qtys)
     * already exists, we sum its quantity and price with the incoming line.
     */
    public function add(array $payload): array
    {
        [$shopperId, $isGuest, $moduleId, $storeId] = $this->shopperContext();
        $modelClass = $this->resolveModel($payload['model']);
        $itemId     = (int) $payload['item_id'];
        $item       = $modelClass::find($itemId);

        if (!$item) {
            throw ValidationException::withMessages(['item_id' => __('Item not found')]);
        }
        if ((int) ($item->store_id ?? 0) !== (int) $storeId) {
            throw ValidationException::withMessages(['item_id' => __('Item does not belong to this store')]);
        }

        $variation = $this->arrayPayload($payload['variation'] ?? []);
        $addOnIds = \array_values($this->arrayPayload($payload['add_on_ids'] ?? []));
        $addOnQtys = \array_values($this->arrayPayload($payload['add_on_qtys'] ?? []));
        $qtyDelta = \max(1, (int) ($payload['quantity'] ?? 1));
        $priceDelta = (float) ($payload['price'] ?? 0);

        $existing = $this->findMatchingLine($shopperId, $isGuest, $moduleId, $itemId, $modelClass, $variation, $addOnIds, $addOnQtys);

        if ($existing) {
            $newQty = (int) $existing->quantity + $qtyDelta;
            $this->assertQtyAllowed($item, $newQty);
            $existing->quantity = $newQty;
            $existing->price    = (float) $existing->price + $priceDelta;
            $existing->save();
        } else {
            $this->assertQtyAllowed($item, $qtyDelta);
            $cart = new Cart;
            $cart->user_id = $shopperId;
            $cart->is_guest = $isGuest;
            $cart->module_id = $moduleId;
            // Persist store_id on the row (mirrors the legacy API
            // CartController). The host tax/coupon pipelines filter the cart
            // by `store_id`; without it `getCalculatedTax` reads an empty
            // cart and returns tax_amount=0.
            $cart->store_id = (int) $storeId;
            $cart->item_id = $itemId;
            $cart->item_type = $modelClass;
            $cart->quantity = $qtyDelta;
            $cart->price = $priceDelta;
            // Match the existing 6amMart API CartController convention
            // (json_encode at the call site even though the Cart model has
            // an 'array' cast on these columns). The cast then encodes a
            // second time, which is exactly the storage shape the rest of
            // the platform reads — see cart_product_data_formatting et al.
            $cart->variation   = \json_encode($variation);
            $cart->add_on_ids  = \json_encode($addOnIds);
            $cart->add_on_qtys = \json_encode($addOnQtys);
            $cart->save();
            $item->carts()->save($cart);
        }

        return $this->list();
    }

    /**
     * Update an existing line. Variation/addons fall back to stored values
     * when omitted; price is taken from the payload.
     */
    public function update(int $cartId, array $payload): array
    {
        [$shopperId, $isGuest, $moduleId, $storeId] = $this->shopperContext();
        $cart = $this->ownedRow($cartId, $shopperId, $isGuest, $moduleId, $storeId);

        $modelClass = $this->normalizeStoredItemType($cart->item_type);
        $item       = $modelClass::find($cart->item_id);
        if (!$item) {
            throw ValidationException::withMessages(['cart_id' => __('Cart item not found')]);
        }

        $variation = \array_key_exists('variation', $payload)
            ? $this->arrayPayload($payload['variation'] ?? [])
            : ($this->decodeJson($cart->variation));
        $addOnIds = \array_key_exists('add_on_ids', $payload)
            ? \array_values($this->arrayPayload($payload['add_on_ids'] ?? []))
            : \array_values($this->decodeJson($cart->add_on_ids));
        $addOnQtys = \array_key_exists('add_on_qtys', $payload)
            ? \array_values($this->arrayPayload($payload['add_on_qtys'] ?? []))
            : \array_values($this->decodeJson($cart->add_on_qtys));

        $newQty = \max(1, (int) ($payload['quantity'] ?? $cart->quantity));
        $this->assertQtyAllowed($item, $newQty);

        $cart->quantity    = $newQty;
        $cart->variation   = \json_encode($variation);
        $cart->add_on_ids  = \json_encode($addOnIds);
        $cart->add_on_qtys = \json_encode($addOnQtys);
        if (\array_key_exists('price', $payload)) {
            $cart->price = (float) $payload['price'];
        }
        $cart->save();

        return $this->list();
    }

    public function remove(int $cartId): array
    {
        [$shopperId, $isGuest, $moduleId, $storeId] = $this->shopperContext();
        $this->ownedRow($cartId, $shopperId, $isGuest, $moduleId, $storeId)->delete();
        return $this->list();
    }

    public function clear(): array
    {
        $this->baseQuery()->get()->each->delete();
        return $this->list();
    }

    public function count(): int
    {
        return $this->baseQuery()->count();
    }

    /* ─── query helpers ───────────────────────────────────── */

    private function baseQuery()
    {
        [$shopperId, $isGuest, $moduleId, $storeId] = $this->shopperContext();

        if ($shopperId === null || $moduleId === null || $storeId === null) {
            return Cart::query()->whereRaw('1 = 0');
        }

        return Cart::query()
            ->where('user_id', $shopperId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            );
    }

    private function ownedRow(int $cartId, int $shopperId, int $isGuest, int $moduleId, int $storeId): Cart
    {
        $cart = Cart::query()
            ->where('id', $cartId)
            ->where('user_id', $shopperId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->first();

        if (!$cart) {
            throw ValidationException::withMessages(['cart_id' => __('Cart item not found')]);
        }
        return $cart;
    }

    private function findMatchingLine(int $shopperId, int $isGuest, int $moduleId, int $itemId, string $modelClass, array $variation, array $addOnIds, array $addOnQtys): ?Cart
    {
        $needle = $this->variationMatchKey($variation);

        return Cart::query()
            ->where('user_id', $shopperId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->where('item_id', $itemId)
            ->whereIn('item_type', $this->itemTypeAliases($modelClass))
            ->get()
            ->first(function ($row) use ($needle, $addOnIds, $addOnQtys) {
                return $this->variationMatchKey($this->decodeJson($row->variation)) === $needle
                    && $this->decodeJson($row->add_on_ids)  == $addOnIds
                    && $this->decodeJson($row->add_on_qtys) == $addOnQtys;
            });
    }

    /**
     * Build a stable identity for a variation array that ignores the
     * volatile fields (`price`, `stock`) — non-food cart variations carry
     * `stock` as the per-add quantity, which mutates between adds, and
     * `price` can drift with discounts. The combination's `type` (and for
     * food, `name` + `values.label`) uniquely identifies the choice.
     */
    private function variationMatchKey(array $variation): string
    {
        $normalized = \array_map(static function ($entry) {
            if (!\is_array($entry)) return $entry;
            $copy = $entry;
            unset($copy['price'], $copy['stock'], $copy['oldPrice'], $copy['discountPercent'], $copy['inStock']);
            return $copy;
        }, $variation);

        return \json_encode($normalized) ?: '';
    }

    /**
     * Whether the row's module tracks inventory at all (config/module.php
     * `stock`). `food` does not, so its rows sit at stock = 0 without being
     * depleted and must never cap the stepper.
     */
    private function tracksStock($itemModel): bool
    {
        $moduleType = $itemModel?->module?->module_type;

        return $moduleType ? (bool) config("module.{$moduleType}.stock", false) : false;
    }

    /**
     * Stock left for this exact cart line: the matched variation combination's
     * when the line carries one, else the item's own. Zero when the module
     * doesn't track stock — callers gate on `tracksStock` first.
     */
    private function remainingStock($itemModel, array $variation): int
    {
        if (!$itemModel || !$this->tracksStock($itemModel)) {
            return 0;
        }

        $type = $variation[0]['type'] ?? null;
        if ($type !== null) {
            foreach ($this->decodeJson($itemModel->getAttributes()['variations'] ?? null) as $combo) {
                if (\is_array($combo) && (string) ($combo['type'] ?? '') === (string) $type) {
                    return (int) ($combo['stock'] ?? 0);
                }
            }
        }

        return (int) ($itemModel->getAttributes()['stock'] ?? 0);
    }

    /* ─── formatting ──────────────────────────────────────── */

    private function formatRow(Cart $row): array
    {
        $variation = $this->decodeJson($row->variation);
        $addOnIds  = $this->decodeJson($row->add_on_ids);
        $addOnQtys = $this->decodeJson($row->add_on_qtys);

        $itemModel = $row->item;
        $formatted = $itemModel
            ? Helpers::cart_product_data_formatting($itemModel, $variation, $addOnIds, $addOnQtys, false, \app()->getLocale())
            : null;

        return [
            'id'          => $row->id,
            'item_id'     => $row->item_id,
            'item_type'   => $row->item_type === ItemCampaign::class ? 'ItemCampaign' : 'Item',
            'price'       => (float) $row->price,
            'quantity'    => (int) $row->quantity,
            'variation'   => $variation,
            'add_on_ids'  => $addOnIds,
            'add_on_qtys' => $addOnQtys,
            'item'        => $formatted,
            // Inventory cap for the drawer's +/- stepper. Not read from
            // `item.stock`: for a variation line the binding limit is the
            // chosen combination's stock, and the row's own `variation.stock`
            // is the per-add quantity, not what is left on the shelf.
            'tracksStock' => $this->tracksStock($itemModel),
            'stock'       => $this->remainingStock($itemModel, $variation),
        ];
    }

    /**
     * `price` on each row is the line total, so subtotal is a straight sum.
     */
    private function withTotals(array $rows): array
    {
        $count    = \count($rows);
        $quantity = 0;
        $subtotal = 0.0;
        foreach ($rows as $r) {
            $quantity += (int) $r['quantity'];
            $subtotal += (float) $r['price'];
        }
        return [
            'items'  => $rows,
            'totals' => [
                'count'    => $count,
                'quantity' => $quantity,
                'subtotal' => \round($subtotal, $this->context->getDigitAfterDecimalPoint()),
            ],
        ];
    }

    /* ─── small helpers ───────────────────────────────────── */

    private function shopperContext(): array
    {
        return [
            (int) $this->context->getShopperId(),
            $this->context->shopperIsGuestFlag(),
            $this->context->getModuleId(),
            $this->context->getStoreId(),
        ];
    }

    private function resolveModel(string $alias): string
    {
        if (!isset(self::MODEL_MAP[$alias])) {
            throw ValidationException::withMessages(['model' => __('Invalid item model')]);
        }
        return self::MODEL_MAP[$alias];
    }

    private function normalizeStoredItemType(?string $stored): string
    {
        if ($stored === ItemCampaign::class || $stored === 'ItemCampaign') {
            return ItemCampaign::class;
        }
        return Item::class;
    }

    private function itemTypeAliases(string $modelClass): array
    {
        return $modelClass === ItemCampaign::class
            ? [ItemCampaign::class, 'ItemCampaign']
            : [Item::class, 'Item'];
    }

    private function assertQtyAllowed(Model $item, int $qty): void
    {
        $max = (int) ($item->maximum_cart_quantity ?? 0);
        if ($max > 0 && $qty > $max) {
            throw ValidationException::withMessages([
                'quantity' => __('messages.maximum_cart_quantity_exceeded'),
            ]);
        }
    }

    private function arrayPayload($value): array
    {
        return \is_array($value) ? $value : [];
    }

    private function decodeJson($value): array
    {
        if (\is_array($value)) {
            return $value;
        }
        if (\is_string($value) && $value !== '') {
            $decoded = \json_decode($value, true);
            return \is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
