<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\PersonalizationService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Modules\Service\Lib\BookingCartService;

class CartController extends Controller
{
    /**
     * The Service module reuses the shared `carts` table but with service semantics (item_type =
     * Service, provider/service-named fields, no add-ons/stock). When the active module is an
     * enabled service-type module, every cart endpoint delegates to the module's booking cart so
     * one URL (api/v1/customer/cart/*) serves both item and service carts. Other modules keep the
     * existing item-cart logic below.
     */
    private function isServiceModule(): bool
    {
        $module = config('module.current_module_data');

        return service_addon_active()
            && $module
            && ($module['module_type'] ?? null) === 'service'
            && (int) ($module['status'] ?? 0) === 1;
    }

    public function get_carts(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::getCarts($request);
        }

        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $storeId = $this->resolveStoreIdFromRequest($request->store_id);

        return response()->json($this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: getModuleId($request->header('moduleId')),
            storeId: $storeId
        ), 200);
    }

    public function get_all_carts(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::getAllCarts($request);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $moduleId = getModuleId($request->header('moduleId'));
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');

        $grouped = $this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: $moduleId,
            returnRaw: true
        )->groupBy(fn ($cart) => $cart->store_id ?? data_get($cart, 'item.store_id') ?? 'unknown')
            ->sortByDesc(fn (Collection $storeCarts) => $storeCarts->max('id'))
            ->map(function (Collection $storeCarts, $storeId) use ($longitude, $latitude) {
                $store = (is_numeric($storeId)
                    ? Store::WithOpenWithDeliveryTime($longitude ?? 0, $latitude ?? 0)->with('module:id,module_type')->find($storeId)
                    : null)
                    ?? $storeCarts->first()?->store;

                $delivery_time = $store?->delivery_time;
                $delivery_parts = $delivery_time ? explode('-', $delivery_time) : [];

                return [
                    'store' => [
                        'id' => $store?->id ?? (is_numeric($storeId) ? (int) $storeId : null),
                        'name' => $store?->name,
                        'slug' => $store?->slug,
                        'module_type' => $store?->module_type,
                        'logo' => $store?->logo,
                        'logo_full_url' => $store?->logo_full_url,
                        'item_count' => $storeCarts->count(),
                        'delivery_time' => $delivery_time,
                        'min_delivery_time' => isset($delivery_parts[0]) ? (int) $delivery_parts[0] : 0,
                        'max_delivery_time' => isset($delivery_parts[1]) ? (int) preg_replace('/[^0-9]/', '', $delivery_parts[1]) : 0,
                        'distance' => (float) ($store?->distance ?? 0),
                        'distance_km' => isset($store->distance) ? round(((float) $store->distance) / 1000, 2) : 0,
                    ],
                    'carts' => $storeCarts->values()->all(),
                ];
            })->values();

        return response()->json($grouped, 200);
    }

    public function add_to_cart(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::addToCart($request);
        }

        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
            'item_id' => 'required|integer',
            'model' => 'required|string|in:Item,ItemCampaign',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'reel_id' => 'nullable|integer',
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $moduleId = getModuleId($request->header('moduleId'));
        $storeId = $this->resolveStoreIdFromRequest($request->store_id);
        $model = $this->resolveItemType($request->model);
        $item = $this->resolveItem($request->model, $request->item_id);

        if (! $item) {
            return response()->json([
                'errors' => [['code' => 'cart_item', 'message' => translate('messages.item_not_found')]],
            ], 403);
        }

        if ($stockError = $this->outOfStockError($item, $request->variation, (int) $request->quantity)) {
            return response()->json(['errors' => [$stockError]], 403);
        }

        $exists = Cart::where('item_id', $request->item_id)
            ->where('item_type', $model)
            ->where('variation', json_encode($request->variation))
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [['code' => 'cart_item', 'message' => translate('messages.Item_already_exists')]],
            ], 403);
        }

        if ($item->maximum_cart_quantity && ($request->quantity > $item->maximum_cart_quantity)) {
            return response()->json([
                'errors' => [['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]],
            ], 403);
        }

        $cart = new Cart;
        $cart->user_id = $userId;
        $cart->module_id = $moduleId;
        $cart->store_id = $this->resolveStoreId($item);
        $cart->item_id = $request->item_id;
        $cart->is_guest = $isGuest;
        $cart->add_on_ids = json_encode($request->add_on_ids ?? []);
        $cart->add_on_qtys = json_encode($request->add_on_qtys ?? []);
        $cart->item_type = $model;
        $cart->reel_id = Helpers::resolve_reel_id($request->filled('reel_id') ? (int) $request->reel_id : null, (int) $request->item_id);
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = json_encode($request->variation ?? []);
        $cart->save();

        if (! $isGuest && $model === Item::class) {
            PersonalizationService::recordItemAction((int) $userId, (int) $request->item_id, 'cart');
        }

        $item->carts()->save($cart);

        return response()->json($this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: $moduleId,
            storeId: $storeId
        ), 200);
    }

    public function add_to_cart_multiple(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::addToCartMultiple($request);
        }

        $validator = Validator::make($request->all(), [
            'item_list' => 'required|array',
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $moduleId = getModuleId($request->header('moduleId'));
        $storeId = $this->resolveStoreIdFromRequest($request->store_id);

        foreach ($request->item_list as $singleItem) {
            $model = $this->resolveItemType($singleItem['model']);
            $item = $this->resolveItem($singleItem['model'], $singleItem['item_id']);

            if (! $item) {
                return response()->json([
                    'errors' => [['code' => 'cart_item', 'message' => translate('messages.item_not_found')]],
                ], 403);
            }

            if ($stockError = $this->outOfStockError($item, $singleItem['variation'] ?? [], (int) $singleItem['quantity'])) {
                return response()->json(['errors' => [$stockError]], 403);
            }

            $exists = Cart::where('item_id', $singleItem['item_id'])
                ->where('item_type', $model)
                ->where('variation', json_encode($singleItem['variation'] ?? []))
                ->where('user_id', $userId)
                ->where('is_guest', $isGuest)
                ->where('module_id', $moduleId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'errors' => [['code' => 'cart_item', 'message' => translate('messages.Item_already_exists')]],
                ], 403);
            }

            if ($item->maximum_cart_quantity && ($singleItem['quantity'] > $item->maximum_cart_quantity)) {
                return response()->json([
                    'errors' => [['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]],
                ], 403);
            }

            $cart = new Cart;
            $cart->user_id = $userId;
            $cart->module_id = $moduleId;
            $cart->store_id = $this->resolveStoreId($item);
            $cart->item_id = $singleItem['item_id'];
            $cart->is_guest = $isGuest;
            $cart->add_on_ids = json_encode($singleItem['add_on_ids'] ?? []);
            $cart->add_on_qtys = json_encode($singleItem['add_on_qtys'] ?? []);
            $cart->item_type = $model;
            $cart->reel_id = Helpers::resolve_reel_id(isset($singleItem['reel_id']) ? (int) $singleItem['reel_id'] : null, (int) $singleItem['item_id']);
            $cart->price = $singleItem['price'];
            $cart->quantity = $singleItem['quantity'];
            $cart->variation = json_encode($singleItem['variation'] ?? []);
            $cart->save();

            $item->carts()->save($cart);
        }

        return response()->json($this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: $moduleId,
            storeId: $storeId
        ), 200);
    }

    public function update_cart(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::updateCart($request);
        }

        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $moduleId = getModuleId($request->header('moduleId'));
        $storeId = $this->resolveStoreIdFromRequest($request->store_id);
        $cart = Cart::find($request->cart_id);

        if (! $cart || (int) $cart->user_id !== (int) $userId || (int) $cart->is_guest !== (int) $isGuest) {
            return response()->json([
                'errors' => [['code' => 'cart', 'message' => translate('messages.cart_not_found')]],
            ], 404);
        }

        $item = $cart->item_type === Item::class
            ? Item::find($cart->item_id)
            : ItemCampaign::find($cart->item_id);

        if (! $item) {
            return response()->json([
                'errors' => [['code' => 'cart_item', 'message' => translate('messages.item_not_found')]],
            ], 404);
        }

        $effectiveVariation = $request->variation ?: (json_decode($cart->variation ?? '[]', true) ?: []);
        if ($stockError = $this->outOfStockError($item, $effectiveVariation, (int) $request->quantity)) {
            return response()->json(['errors' => [$stockError]], 403);
        }

        if ($item->maximum_cart_quantity && ($request->quantity > $item->maximum_cart_quantity)) {
            return response()->json([
                'errors' => [['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]],
            ], 403);
        }

        $cart->user_id = $userId;
        $cart->module_id = $moduleId;
        $cart->store_id = $this->resolveStoreId($item);
        $cart->is_guest = $isGuest;
        $cart->add_on_ids = $request->has('add_on_ids') ? json_encode($request->add_on_ids ?? []) : $cart->add_on_ids;
        $cart->add_on_qtys = $request->has('add_on_qtys') ? json_encode($request->add_on_qtys ?? []) : $cart->add_on_qtys;
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = $request->variation ? json_encode($request->variation) : $cart->variation;
        $cart->save();

        return response()->json($this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: $moduleId,
            storeId: $storeId
        ), 200);
    }

    public function remove_cart_item(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::removeCartItem($request);
        }

        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $storeId = $this->resolveStoreIdFromRequest($request->store_id);
        $cart = Cart::find($request->cart_id);

        if (! $cart || (int) $cart->user_id !== (int) $userId || (int) $cart->is_guest !== (int) $isGuest) {
            return response()->json([
                'errors' => [['code' => 'cart', 'message' => translate('messages.cart_not_found')]],
            ], 404);
        }

        $cart->delete();

        return response()->json($this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: getModuleId($request->header('moduleId')),
            storeId: $storeId
        ), 200);
    }

    public function remove_cart(Request $request): JsonResponse
    {
        if ($this->isServiceModule()) {
            return BookingCartService::removeCart($request);
        }

        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        [$userId, $isGuest] = $this->resolveCartOwner($request);
        $moduleId = getModuleId($request->header('moduleId'));
        $storeId = $this->resolveStoreIdFromRequest($request->store_id);

        Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->delete();

        return response()->json($this->getFormattedCartResponse(
            userId: $userId,
            isGuest: $isGuest,
            moduleId: $moduleId,
            storeId: $storeId
        ), 200);
    }

    private function getFormattedCartResponse(
        $userId,
        int $isGuest,
        int $moduleId,
        bool $returnRaw = false,
        ?int $storeId = null
    ): array|Collection {
        $carts = Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get()
            ->map(function ($data) {
                $data->add_on_ids = json_decode($data->add_on_ids, true);
                $data->add_on_qtys = json_decode($data->add_on_qtys, true);
                $data->variation = json_decode($data->variation, true);
                $data->item = Helpers::cart_product_data_formatting(
                    $data->item,
                    $data->variation,
                    $data->add_on_ids,
                    $data->add_on_qtys,
                    false,
                    app()->getLocale()
                );

                return $data;
            })
            ->filter(fn ($cart) => $cart->item);

        return $returnRaw ? $carts : $carts->values()->all();
    }

    private function resolveCartOwner(Request $request): array
    {
        return [$request->user ? $request->user->id : $request['guest_id'], $request->user ? 0 : 1];
    }

    private function resolveItemType(string $model): string
    {
        return $model === 'Item' ? Item::class : ItemCampaign::class;
    }

    private function resolveItem(string $model, int $itemId): Item|ItemCampaign|null
    {
        return $model === 'Item' ? Item::find($itemId) : ItemCampaign::find($itemId);
    }

    private function outOfStockError($item, $variation, int $quantity): ?array
    {
        $moduleType = $item->module?->module_type ?? null;
        if (! $moduleType || ! config('module.'.$moduleType.'.stock')) {
            return null;
        }

        $variationInput = is_array($variation) ? array_values($variation) : [];
        $itemVariations = json_decode($item['variations'] ?? '[]', true) ?: [];

        if (count($itemVariations) > 0 && count($variationInput) > 0) {
            $stock = (int) (Helpers::variation_price($item, json_encode($variationInput))['stock'] ?? 0);
        } else {
            $stock = (int) ($item->stock ?? 0);
        }

        if ($quantity > $stock) {
            return ['code' => 'stock', 'message' => trim(($item->name ?? '').' '.translate('messages.is_out_of_stock'))];
        }

        return null;
    }

    private function resolveStoreId($item): ?int
    {
        return $item?->store_id ?? $item?->store?->id;
    }

    private function resolveStoreIdFromRequest($storeId): ?int
    {
        if ($storeId === null || $storeId === '') {
            return null;
        }

        if (is_numeric($storeId)) {
            return (int) $storeId;
        }

        return Store::where('slug', $storeId)->value('id');
    }
}
