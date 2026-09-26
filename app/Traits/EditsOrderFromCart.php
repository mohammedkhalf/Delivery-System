<?php

namespace App\Traits;

use App\CentralLogics\CouponLogic;
use App\CentralLogics\DeliveryFeeLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Item;
use App\Models\OrderDetail;
use App\Scopes\StoreScope;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait EditsOrderFromCart
{
    public function updateOrderFromCartRequest(Request $request, $order, string $editedBy)
    {
        if (!$order) {
            return $this->orderEditResult($request, ['status' => 404, 'code' => 'order_id', 'message' => translate('messages.order_not_found')]);
        }

        if (!$order->is_editable) {
            return $this->orderEditResult($request, ['status' => 403, 'code' => 'status', 'message' => translate('messages.order_can_not_be_edited')]);
        }

        $result = $this->commitEditedOrderFromCart($request, $order, $editedBy);

        return $this->orderEditResult($request, $result);
    }

    private function orderEditResult(Request $request, array $result)
    {
        $status = $result['status'] ?? 403;

        if ($request->expectsJson()) {
            if ($status === 200) {
                return response()->json(['message' => $result['message']], 200);
            }
            return response()->json(['errors' => [['code' => $result['code'] ?? 'order', 'message' => $result['message']]]], $status);
        }

        if ($status === 200) {
            Toastr::success($result['message']);
        } else {
            Toastr::error($result['message']);
        }
        return back();
    }

    public function commitEditedOrderFromCart(Request $request, $order, string $editedBy): array
    {
        $carts = $request->input('carts');
        if (!is_array($carts) || count($carts) === 0) {
            return ['status' => 403, 'code' => 'cart', 'message' => translate('messages.cart_is_empty')];
        }

        $store = $order->store;
        $originalDetailQtys = $order->details->pluck('quantity', 'id')->all();
        $originalIds = array_map('intval', array_keys($originalDetailQtys));

        $cart = collect([]);
        $keptIds = [];
        $preservedIds = [];

        foreach ($carts as $line) {
            $detailId = isset($line['order_details_id']) ? (int) $line['order_details_id'] : null;
            $isKept = $detailId && in_array($detailId, $originalIds, true);
            if ($isKept) {
                $keptIds[] = $detailId;
            }
            if ($isKept && !empty($line['unavailable'])) {
                $preservedIds[] = $detailId;
                continue;
            }

            $detail = new OrderDetail();
            if ($isKept) {
                $detail->id = $detailId;
            }
            $detail->item_id = $line['item_id'] ?? null;
            $detail->item_campaign_id = null;
            $detail->item_type = 'App\Models\Item';
            $detail->order_id = $order->id;
            $detail->quantity = max(1, (int) ($line['quantity'] ?? 1));
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
            return ['status' => 403, 'code' => data_get($computed, 'code', 'cart'), 'message' => data_get($computed, 'message')];
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

            foreach ($order->details->whereIn('id', $preservedIds) as $pd) {
                $order_details[] = $this->buildPreservedOrderRow($pd);
                $product_price += (float) $pd->price * (int) $pd->quantity;
                $total_addon_price += (float) ($pd->total_add_on_price ?? 0);
                if (($pd->discount_type ?? null) != 'flash_sale') {
                    $store_discount_amount += (float) ($pd->discount_on_item ?? 0) * (int) $pd->quantity;
                }
            }

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

            $isProCustomer = $order->user_id && \App\Models\User::where('id', $order->user_id)->where('pro_status', 1)->exists();
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
                $total_price = (float) $proRecompute['total_price'];
                $order->delivery_charge = (float) $proRecompute['delivery_charge'];
                if ($proRecompute['delivery_savings'] > 0) {
                    $order->free_delivery_by = $proRecompute['free_delivery_by'];
                }
                $totalDiscount += (float) $proRecompute['discount'];
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
                return ['status' => 403, 'code' => 'minimum_order', 'message' => translate('messages.you_need_to_order_at_least', ['amount' => $store->minimum_order . ' ' . Helpers::currency_code()])];
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
                $this->makeEditOrderLogs($order->id, $log, $editedBy);
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

                $this->adjustEditedStockFromCart($cart, $originalDetailQtys);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            info($th->getMessage());
            return ['status' => 403, 'code' => 'order_update', 'message' => translate('messages.order_update_failed')];
        }

        session()->forget(['order_cart', 'edit_tax_amount', 'edit_tax_included', 'discount_on_product_by_session', 'open_edit_offcanvas']);

        return ['status' => 200, 'message' => translate('messages.order_updated_successfully')];
    }

    private function buildPreservedOrderRow($pd): array
    {
        return [
            'cart_id' => $pd->id,
            'item_id' => $pd->item_id,
            'item_campaign_id' => $pd->item_campaign_id,
            'item_details' => is_string($pd->item_details) ? $pd->item_details : json_encode($pd->item_details),
            'quantity' => $pd->quantity,
            'price' => $pd->price,
            'category_id' => $pd->category_id ?? null,
            'tax_amount' => $pd->tax_amount ?? 0,
            'tax_status' => $pd->tax_status ?? null,
            'discount_on_product_by' => $pd->discount_on_product_by ?? null,
            'discount_type' => $pd->discount_type ?? null,
            'discount_on_item' => $pd->discount_on_item ?? 0,
            'discount_percentage' => $pd->discount_percentage ?? 0,
            'variant' => is_string($pd->variant) ? $pd->variant : json_encode($pd->variant ?? []),
            'variation' => is_string($pd->variation) ? $pd->variation : json_encode($pd->variation ?? []),
            'add_ons' => is_string($pd->add_ons) ? $pd->add_ons : json_encode($pd->add_ons ?? []),
            'total_add_on_price' => $pd->total_add_on_price ?? 0,
            'addon_discount' => $pd->addon_discount ?? 0,
            'created_at' => $pd->created_at ?? now(),
            'updated_at' => now(),
        ];
    }

    private function adjustEditedStockFromCart($cart, array $originalDetailQtys): void
    {
        foreach ($cart as $c) {
            if (empty($c['item_id'])) {
                continue;
            }

            $stockProduct = Item::withoutGlobalScope(StoreScope::class)->with('module')->find($c['item_id']);
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
