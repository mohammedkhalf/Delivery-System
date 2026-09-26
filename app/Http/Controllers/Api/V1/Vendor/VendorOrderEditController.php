<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\CouponLogic;
use App\CentralLogics\DeliveryFeeLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Traits\PlaceNewOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendorOrderEditController extends Controller
{
    use PlaceNewOrder;

    public function get_searched_foods(Request $request)
    {
        $storeId = $request['vendor']->stores[0]->id;
        if ($request->store_id && (int) $request->store_id !== (int) $storeId) {
            return response()->json(['items' => []]);
        }

        $key = explode(' ', $request->keyword ?? '');
        $products = Item::withoutGlobalScope(\App\Scopes\StoreScope::class)
            ->with(['module', 'store.storeConfig'])
            ->where('store_id', $storeId)
            ->when($request->keyword, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->active()->take(10)->get();

        $items = $products->map(function ($p) {
            $hasVariations = false;
            if ($p->module && $p->module->module_type == 'food') {
                $foodVars = $p->food_variations ? json_decode($p->food_variations, true) : [];
                $hasVariations = is_array($foodVars) && count($foodVars) > 0;
            } else {
                $choiceOpts = $p->choice_options ? json_decode($p->choice_options, true) : [];
                $hasVariations = is_array($choiceOpts) && count($choiceOpts) > 0;
            }
            $addons = $p->add_ons ? json_decode($p->add_ons, true) : [];
            $hasAddons = is_array($addons) && count($addons) > 0 && !empty($addons[0]);

            $isFood = $p->module && $p->module->module_type == 'food';
            $moduleType = $p->module?->module_type;
            $tracksStock = $moduleType ? (bool) data_get(config('module.' . $moduleType), 'stock', false) : false;
            $stock = $tracksStock ? (int) $p->stock : null;
            if ($tracksStock) {
                $variationStocks = $p->variations ? json_decode($p->variations, true) : [];
                if (is_array($variationStocks) && count($variationStocks) > 0) {
                    $stock = (int) array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $variationStocks));
                }
            }
            $availableTime = ($isFood && $p->available_time_starts && $p->available_time_ends)
                ? date(config('timeformat'), strtotime($p->available_time_starts)) . ' - ' . date(config('timeformat'), strtotime($p->available_time_ends))
                : null;
            $isAvailable = $isFood ? $p->is_available_now : true;
            if ($tracksStock && $stock <= 0) {
                $isAvailable = false;
            }
            $showVeg = $isFood && (bool) config('toggle_veg_non_veg') && (bool) data_get(config('module.' . $moduleType), 'veg_non_veg', false);
            $showHalal = $p->is_halal == 1
                && (bool) data_get(config('module.' . $moduleType), 'halal', false)
                && (bool) ($p->store?->storeConfig?->halal_tag_status ?? 0);

            return [
                'id'              => $p->id,
                'name'            => $p->name,
                'image'           => $p->image_full_url,
                'formatted_price' => Helpers::format_currency($p->price - Helpers::product_discount_calculate($p, $p->price, $p->store)['discount_amount']),
                'original_price'  => $p->discount > 0 ? Helpers::format_currency($p->price) : null,
                'has_variations'  => $hasVariations,
                'has_addons'      => $hasAddons,
                'is_available'    => $isAvailable,
                'available_time'  => $availableTime,
                'tracks_stock'    => $tracksStock,
                'stock'           => $stock,
                'veg'             => $showVeg ? (int) $p->veg : null,
                'is_halal'        => $showHalal,
            ];
        });

        return response()->json(['items' => $items]);
    }

    public function get_edit_logs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];

        $order = Order::whereHas('store.vendor', function ($query) use ($vendor) {
            $query->where('id', $vendor->id);
        })
            ->where('id', $request['order_id'])
            ->Notpos()
            ->first();

        if (!$order) {
            return response()->json(['errors' => [['code' => 'order_id', 'message' => translate('messages.order_data_not_found')]]], 404);
        }

        $limit = $request->limit ? $request->limit : 25;
        $offset = $request->offset ? $request->offset : 1;

        $remarkLabels = [
            'edited_item_quantity' => translate('messages.edited_item_quantity'),
            'add_new_item'         => translate('messages.added_new_item'),
            'delete_item'          => translate('messages.removed_item'),
        ];

        $paginator = $order->orderEditLogs()->paginate($limit, ['*'], 'page', $offset);

        $edit_logs = collect($paginator->items())->map(function ($editLog) use ($remarkLabels) {
            $editedBy = $editLog->edited_by ?? 'admin';

            return [
                'id'              => $editLog->id,
                'log'             => $editLog->log,
                'remark'          => $remarkLabels[$editLog->log] ?? translate(str_replace('_', ' ', $editLog->log ?? 'edited')),
                'edited_by'       => $editedBy,
                'edited_by_label' => translate('messages.' . $editedBy),
                'created_at'      => $editLog->created_at,
            ];
        });

        return response()->json([
            'order_id'   => (int) $order->id,
            'total_size' => $paginator->total(),
            'limit'      => $limit,
            'offset'     => $offset,
            'edit_logs'  => $edit_logs,
        ], 200);
    }

    public function update_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'carts' => 'required|array|min:1',
            'carts.*.item_id' => 'required',
            'carts.*.quantity' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];

        $order = Order::whereHas('store.vendor', function ($query) use ($vendor) {
            $query->where('id', $vendor->id);
        })
            ->with(['details', 'store.module', 'store.vendor', 'payments'])
            ->where('id', $request['order_id'])
            ->Notpos()
            ->first();

        if (!$order) {
            return response()->json(['errors' => [['code' => 'order_id', 'message' => translate('messages.order_data_not_found')]]], 404);
        }

        if (!$order->is_editable) {
            return response()->json(['errors' => [['code' => 'status', 'message' => translate('messages.order_can_not_be_edited')]]], 403);
        }

        $store = $order->store;
        $originalDetailQtys = $order->details->pluck('quantity', 'id')->all();
        $originalIds = array_map('intval', array_keys($originalDetailQtys));

        $cart = collect([]);
        $keptIds = [];

        foreach ($request['carts'] as $line) {
            $detail = new OrderDetail();
            $detailId = isset($line['order_details_id']) ? (int) $line['order_details_id'] : null;
            if ($detailId && in_array($detailId, $originalIds, true)) {
                $detail->id = $detailId;
                $keptIds[] = $detailId;
            }

            $detail->item_id = $line['item_id'];
            $detail->item_campaign_id = null;
            $detail->item_type = 'App\Models\Item';
            $detail->order_id = $order->id;
            $detail->quantity = (int) $line['quantity'];
            $detail->variation = json_encode($line['variation'] ?? []);
            $detail->variant = json_encode($line['variant'] ?? []);
            $detail->add_ons = json_encode($line['add_on_ids'] ?? []);
            $detail->add_on_qtys = $line['add_on_qtys'] ?? [];
            $detail->status = true;
            $cart->push($detail);
        }

        $deletedIds = array_values(array_diff($originalIds, $keptIds));

        foreach ($order->details as $existing) {
            if (in_array((int) $existing->id, $keptIds, true)) {
                continue;
            }
            $removed = new OrderDetail();
            $removed->id = $existing->id;
            $removed->item_id = $existing->item_id;
            $removed->item_campaign_id = $existing->item_campaign_id;
            $removed->variation = $existing->variation;
            $removed->quantity = $existing->quantity;
            $removed->status = false;
            $cart->push($removed);
        }

        $editLogs = $this->collectEditLogs($cart, $originalDetailQtys);

        $computed = $this->makeEditOrderDetails($cart, $request, $store, $originalDetailQtys);
        if (data_get($computed, 'status_code') === 403) {
            return response()->json([
                'errors' => [['code' => data_get($computed, 'code', 'cart'), 'message' => data_get($computed, 'message')]],
            ], 403);
        }

        DB::beginTransaction();
        try {
            $coupon = $order->coupon_code ? Coupon::where('code', $order->coupon_code)->first() : null;

            $order_details = $computed['order_details'];
            $total_addon_price = $computed['total_addon_price'];
            $product_price = $computed['product_price'];
            $store_discount_amount = $computed['store_discount_amount'];
            $flash_sale_admin_discount_amount = $computed['flash_sale_admin_discount_amount'];
            $flash_sale_vendor_discount_amount = $computed['flash_sale_vendor_discount_amount'];

            $store_discount = Helpers::get_store_discount($store);
            if (isset($store_discount)) {
                if ($product_price + $total_addon_price < $store_discount['min_purchase']) {
                    $store_discount_amount = 0;
                }
                if ($store_discount_amount > $store_discount['max_discount']) {
                    $store_discount_amount = $store_discount['max_discount'];
                }
            }

            $order->delivery_charge = $order->original_delivery_charge;
            if ($coupon && $coupon->coupon_type == 'free_delivery') {
                $order->delivery_charge = 0;
                $coupon = null;
            }
            if ($order->store->free_delivery || $order->order_type == 'take_away') {
                $order->delivery_charge = 0;
            }

            $additionalCharges = [];
            $settings = BusinessSetting::whereIn('key', ['additional_charge_status', 'additional_charge'])->pluck('value', 'key');
            $order->additional_charge = 0;
            if (($settings['additional_charge_status'] ?? null) == 1) {
                $order->additional_charge = $settings['additional_charge'] ?? 0;
            }

            $coupon_discount_amount = $coupon ? CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
            $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount - $coupon_discount_amount;
            $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $coupon_discount_amount + $order->ref_bonus_amount;

            $isProCustomer = $order->user_id && User::where('id', $order->user_id)->where('pro_status', 1)->exists();
            $pro_discount_amount = 0.0;
            if ($isProCustomer) {
                $free_delivery_over_pro = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
                if (isset($free_delivery_over_pro) && $free_delivery_over_pro <= $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount) {
                    $order->delivery_charge = 0;
                }

                $proRecompute = $this->recomputeOrderProDiscountOnEdit(
                    order: $order,
                    subtotal: $product_price + $total_addon_price,
                    totalPrice: $total_price,
                    moduleType: $store?->module?->module_type,
                    deliveryCharge: (float) $order->delivery_charge,
                );
                $pro_discount_amount = (float) $proRecompute['discount'];
                $total_price = (float) $proRecompute['total_price'];
                $order->delivery_charge = (float) $proRecompute['delivery_charge'];
                if ($proRecompute['delivery_savings'] > 0) {
                    $order->free_delivery_by = $proRecompute['free_delivery_by'];
                }
                $totalDiscount += $pro_discount_amount;
            }

            $finalCalculatedTax = Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $store->id);
            $tax_amount = $finalCalculatedTax['tax_amount'];
            $tax_status = $finalCalculatedTax['tax_status'];
            $taxMap = $finalCalculatedTax['taxMap'];
            $orderTaxIds = data_get($finalCalculatedTax, 'taxData.orderTaxIds', []);
            $taxType = data_get($finalCalculatedTax, 'taxType');
            $order->tax_type = $taxType;
            $order->tax_status = $tax_status;
            $total_tax_amount = $order->tax_status == 'included' ? 0 : $tax_amount;

            if ($store->minimum_order > $product_price + $total_addon_price) {
                DB::rollBack();
                return response()->json([
                    'errors' => [['code' => 'minimum_order', 'message' => translate('messages.you_need_to_order_at_least', ['amount' => $store->minimum_order . ' ' . Helpers::currency_code()])]],
                ], 403);
            }

            if (!$isProCustomer) {
                $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
                if (isset($free_delivery_over) && $free_delivery_over <= $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount) {
                    $order->delivery_charge = 0;
                }
            }

            $total_order_ammount = $total_price + $total_tax_amount + $order->delivery_charge + $order->additional_charge;
            $total_order_ammount = DeliveryFeeLogic::applyDeliveryTypeToAmount($order, (float) $total_order_ammount);
            $adjustment = $order->order_amount - $total_order_ammount;

            $order->coupon_discount_amount = $coupon_discount_amount;
            $order->store_discount_amount = $store_discount_amount;
            $order->total_tax_amount = $total_tax_amount;
            $order->order_amount = $total_order_ammount;
            $order->adjusment = $adjustment;
            $order->edited = true;
            $order->save();

            foreach ($editLogs as $log) {
                $this->makeEditOrderLogs($order->id, $log, 'vendor');
            }

            if (!empty($deletedIds)) {
                OrderDetail::whereIn('id', $deletedIds)->where('order_id', $order->id)->delete();
            }

            if ($order->order_type !== 'parcel') {
                $taxMapCollection = collect($taxMap);
                foreach ($order_details as $key => $item) {
                    $item_id = $item['item_id'] ?: $item['item_campaign_id'];
                    $index = $taxMapCollection->search(fn ($tax) => $tax['product_id'] == $item_id);
                    if ($index !== false) {
                        $matchedTax = $taxMapCollection->pull($index);
                        $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                        $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                    }
                }

                foreach ($order_details as $detail) {
                    $cartId = $detail['cart_id'] ?? null;
                    unset($detail['cart_id']);
                    $detail['order_id'] = $order->id;

                    if ($cartId && isset($originalDetailQtys[$cartId])) {
                        unset($detail['created_at']);
                        OrderDetail::where('id', $cartId)->where('order_id', $order->id)->update($detail);
                    } else {
                        OrderDetail::insert($detail);
                    }
                }

                $order?->orderTaxes()?->delete();
                if (count($orderTaxIds)) {
                    \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                        orderId: $order->id,
                        orderTaxIds: $orderTaxIds,
                    );
                }

                $this->adjustEditedStock($cart, $originalDetailQtys);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            info($th->getMessage());
            return response()->json(['errors' => [['code' => 'order_update', 'message' => translate('messages.order_update_failed')]]], 403);
        }

        $order = Order::with(['customer', 'details', 'delivery_man', 'payments', 'orderProDiscount'])->find($order->id);

        return response()->json([
            'message' => translate('messages.order_updated_successfully'),
            'order' => Helpers::order_data_formatting($order),
        ], 200);
    }

    private function adjustEditedStock($cart, array $originalDetailQtys): void
    {
        foreach ($cart as $c) {
            if (empty($c['item_id'])) {
                continue;
            }

            $stockProduct = Item::withoutGlobalScope(\App\Scopes\StoreScope::class)->with('module')->find($c['item_id']);
            if (!$stockProduct || !$stockProduct->module) {
                continue;
            }
            if (!data_get(config('module.' . $stockProduct->module->module_type), 'stock', false)) {
                continue;
            }

            $variationDecoded = is_string($c['variation']) ? (json_decode($c['variation'], true) ?: []) : (is_array($c['variation']) ? $c['variation'] : []);
            $variantType = (isset($variationDecoded[0]['type']) && $variationDecoded[0]['type'] !== '') ? $variationDecoded[0]['type'] : null;

            $wasKept = !empty($c['status']);
            $originalQty = (isset($c->id) && isset($originalDetailQtys[$c->id])) ? (int) $originalDetailQtys[$c->id] : 0;

            $delta = $wasKept ? (int) $c['quantity'] - $originalQty : -$originalQty;
            if ($delta === 0) {
                continue;
            }

            ProductLogic::update_stock($stockProduct, $delta, $variantType)?->save();
            if ($delta > 0) {
                ProductLogic::update_flash_stock($stockProduct, $delta)?->save();
            } else {
                ProductLogic::update_flash_stock($stockProduct, abs($delta), true)?->save();
            }
        }
    }
}
