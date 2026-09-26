<?php

namespace App\Traits;

use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Store;

trait HandlesCartValidation
{
    public function addItemToCartValidated(array $spec, int $userId, int $isGuest, int $moduleId): array
    {
        $itemId    = (int) ($spec['item_id'] ?? 0);
        $modelKey  = $spec['model'] ?? 'Item';
        $modelCls  = $modelKey === 'Item' ? Item::class : ItemCampaign::class;
        $quantity  = max(1, (int) ($spec['quantity'] ?? 0));
        $price     = (float) ($spec['price'] ?? 0);
        $variation = is_array($spec['variation'] ?? null) ? $spec['variation'] : [];
        $addOnIds  = is_array($spec['add_on_ids'] ?? null) ? $spec['add_on_ids'] : [];
        $addOnQtys = is_array($spec['add_on_qtys'] ?? null) ? $spec['add_on_qtys'] : [];

        $item = $modelKey === 'Item' ? Item::find($itemId) : ItemCampaign::find($itemId);

        if (!$item) {
            return $this->cartValidationResult(
                status: 'failed',
                itemId: $itemId,
                itemName: null,
                code: 'item_not_found',
                message: translate('messages.item_not_found'),
            );
        }

        $itemName = $item->name ?? null;

        if ((int) ($item->status ?? 0) !== 1) {
            return $this->cartValidationResult(
                status: 'failed',
                itemId: $itemId,
                itemName: $itemName,
                code: 'item_unavailable',
                message: trim(($itemName ?: translate('messages.item')) . ' ' . translate('messages.is_not_available')),
            );
        }

        if (isset($item->module_id) && (int) $item->module_id !== (int) $moduleId) {
            return $this->cartValidationResult(
                status: 'failed',
                itemId: $itemId,
                itemName: $itemName,
                code: 'module_mismatch',
                message: translate('messages.item_not_in_current_module'),
            );
        }

        $storeId = $item->store_id ?? $item->store?->id ?? null;
        $store   = $storeId ? Store::find($storeId) : null;

        if ($storeId && !$store) {
            return $this->cartValidationResult(
                status: 'failed',
                itemId: $itemId,
                itemName: $itemName,
                code: 'store_unavailable',
                message: translate('messages.store_is_not_available'),
            );
        }

        if ($store && ((int) $store->status !== 1 || (isset($store->active) && (int) $store->active !== 1))) {
            return $this->cartValidationResult(
                status: 'failed',
                itemId: $itemId,
                itemName: $itemName,
                storeName: $store->name ?? null,
                code: 'store_unavailable',
                message: translate('messages.store_is_not_available'),
            );
        }

        $moduleType  = $store?->module?->module_type ?? null;
        $hasStockCap = $moduleType ? (bool) (config('module.' . $moduleType)['stock'] ?? false) : false;

        $available = null;
        if ($hasStockCap) {
            $itemVariations = json_decode($item['variations'] ?? '[]', true) ?: [];
            if (count($itemVariations) > 0 && count($variation) > 0) {
                $available = (int) (\App\CentralLogics\Helpers::variation_price($item, json_encode(array_values($variation)))['stock'] ?? 0);
            } else {
                $available = isset($item->stock) ? (int) $item->stock : null;
            }
            if ($available !== null && $available < $quantity) {
                return $this->cartValidationResult(
                    status: 'failed',
                    itemId: $itemId,
                    itemName: $itemName,
                    storeName: $store?->name,
                    code: 'out_of_stock',
                    message: translate('messages.item_is_out_of_stock'),
                    extras: ['available' => $available, 'requested' => $quantity],
                );
            }
        }

        if (!empty($item->maximum_cart_quantity) && $quantity > (int) $item->maximum_cart_quantity) {
            return $this->cartValidationResult(
                status: 'failed',
                itemId: $itemId,
                itemName: $itemName,
                storeName: $store?->name,
                code: 'cart_item_limit',
                message: translate('messages.maximum_cart_quantity_exceeded'),
                extras: ['limit' => (int) $item->maximum_cart_quantity, 'requested' => $quantity],
            );
        }

        $variationJson = json_encode($variation);
        $addOnIdsJson  = json_encode($addOnIds);
        $addOnQtysJson = json_encode($addOnQtys);

        $wantedVariation = $this->canonicalVariation($variation);
        $existing = Cart::where('item_id', $itemId)
            ->where('item_type', $modelCls)
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->get()
            ->first(fn ($c) => $this->canonicalVariation($c->variation) === $wantedVariation);

        if ($existing) {
            $newQuantity = (int) $existing->quantity + $quantity;

            if ($available !== null && $available < $newQuantity) {
                return $this->cartValidationResult(
                    status: 'failed',
                    itemId: $itemId,
                    itemName: $itemName,
                    storeName: $store?->name,
                    code: 'out_of_stock',
                    message: translate('messages.item_is_out_of_stock'),
                    extras: ['available' => $available, 'requested' => $newQuantity],
                );
            }

            if (!empty($item->maximum_cart_quantity) && $newQuantity > (int) $item->maximum_cart_quantity) {
                return $this->cartValidationResult(
                    status: 'failed',
                    itemId: $itemId,
                    itemName: $itemName,
                    storeName: $store?->name,
                    code: 'cart_item_limit',
                    message: translate('messages.maximum_cart_quantity_exceeded'),
                    extras: ['limit' => (int) $item->maximum_cart_quantity, 'requested' => $newQuantity],
                );
            }

            $existing->quantity = $newQuantity;
            $existing->save();

            return $this->cartValidationResult(
                status: 'added',
                itemId: $itemId,
                itemName: $itemName,
                storeName: $store?->name,
                code: null,
                message: null,
                extras: ['cart_id' => (int) $existing->id, 'quantity' => $newQuantity, 'merged' => true],
            );
        }

        $cart              = new Cart();
        $cart->user_id     = $userId;
        $cart->module_id   = $moduleId;
        $cart->store_id    = $storeId;
        $cart->item_id     = $itemId;
        $cart->is_guest    = $isGuest;
        $cart->add_on_ids  = $addOnIdsJson;
        $cart->add_on_qtys = $addOnQtysJson;
        $cart->item_type   = $modelCls;
        $cart->price       = $price;
        $cart->quantity    = $quantity;
        $cart->variation   = $variationJson;
        $cart->save();

        $item->carts()->save($cart);

        return $this->cartValidationResult(
            status: 'added',
            itemId: $itemId,
            itemName: $itemName,
            storeName: $store?->name,
            code: null,
            message: null,
            extras: ['cart_id' => (int) $cart->id],
        );
    }

    public function processOrderDetailsToCart($details, int $userId, int $isGuest, int $moduleId): array
    {
        $added       = [];
        $skipped     = [];
        $unavailable = [];

        foreach ($details as $detail) {
            $isCampaign = !empty($detail->item_campaign_id);
            $spec = [
                'item_id'     => $isCampaign ? (int) $detail->item_campaign_id : (int) $detail->item_id,
                'model'       => $isCampaign ? 'ItemCampaign' : 'Item',
                'quantity'    => (int) $detail->quantity,
                'price'       => (float) $detail->price,
                'variation'   => json_decode($detail->variation, true) ?? [],
                'add_on_ids'  => json_decode($detail->add_on_ids, true) ?? [],
                'add_on_qtys' => json_decode($detail->add_on_qtys, true) ?? [],
            ];

            $result = $this->addItemToCartValidated($spec, $userId, $isGuest, $moduleId);

            match ($result['status']) {
                'added'   => $added[]       = $result,
                'skipped' => $skipped[]     = $result,
                'failed'  => $unavailable[] = $result,
            };
        }

        return [
            'added'       => $added,
            'skipped'     => $skipped,
            'unavailable' => $unavailable,
        ];
    }

    private function cartValidationResult(
        string $status,
        int $itemId,
        ?string $itemName,
        ?string $storeName = null,
        ?string $code = null,
        ?string $message = null,
        array $extras = [],
    ): array {
        return array_merge([
            'status'     => $status,
            'item_id'    => $itemId,
            'item_name'  => $itemName,
            'store_name' => $storeName,
            'code'       => $code,
            'message'    => $message,
        ], $extras);
    }

    private function canonicalVariation($variation): string
    {
        $arr = is_array($variation) ? $variation : (json_decode((string) $variation, true) ?: []);
        if (!is_array($arr)) {
            $arr = [];
        }

        $canon = function ($value) use (&$canon) {
            if (is_array($value)) {
                $isList = array_keys($value) === range(0, count($value) - 1);
                $value  = array_map($canon, $value);
                $isList ? sort($value) : ksort($value);
            }
            return $value;
        };

        return json_encode($canon($arr));
    }
}
