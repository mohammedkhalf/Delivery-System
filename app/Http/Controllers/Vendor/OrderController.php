<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Order;
use App\Models\OrderCancelReason;
use App\Models\OrderDetail;
use App\Models\OrderEditLog;
use App\Models\Store;
use App\Models\StoreConfig;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Coupon;
use App\Models\Category;
use App\Exports\OrderExport;
use App\Scopes\StoreScope;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\CouponLogic;
use App\CentralLogics\DeliveryFeeLogic;
use App\CentralLogics\ProductLogic;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderPayment;
use App\Traits\PlaceNewOrder;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;


class OrderController extends Controller
{
    use PlaceNewOrder;
    use \App\Traits\EditsOrderFromCart;
    public function list($status)
    {
        $key = explode(' ', request()?->search);
        Order::where(['checked' => 0])->where('store_id',Helpers::get_store_id())->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store.storeConfig', 'payments', 'details'])
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'confirmed', function($query){
            return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
        })
        ->when($status == 'pending', function($query){
            if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
            {
                return $query->where('order_status','pending');
            }
            else
            {
                return $query->where('order_status','pending')->where('order_type', 'take_away');
            }
        })
        ->when($status == 'cooking', function($query){
            return $query->where('order_status','processing');
        })
        ->when($status == 'item_on_the_way', function($query){
            return $query->where('order_status','picked_up');
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'ready_for_delivery', function($query){
            return $query->where('order_status','handover');
        })
        ->when($status == 'refund_requested', function($query){
            return $query->RefundRequest();
        })
        ->when($status == 'refunded', function($query){
            return $query->Refunded();
        })
        ->when($status == 'scheduled', function($query){
            return $query->Scheduled()->where(function($q){
                if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
                {
                    $q->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded']);
                }
                else
                {
                    $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                        $query->where('order_status','pending')->where('order_type', 'take_away');
                    });
                }

            });
        })
        ->when($status == 'all', function($query){
            return $query->where(function($query){
                $query->whereNotIn('order_status',(config('order_confirmation_model') == 'store'|| Helpers::get_store_data()->sub_self_delivery)?['failed','canceled', 'refund_requested', 'refunded']:[ 'accepted' ,'pending','failed','canceled', 'refund_requested', 'refunded'])
                ->orWhere(function($query){
                    return $query->where('order_status','pending')->where('order_type', 'take_away');
                });
            });
        })
        ->when(in_array($status, ['pending','confirmed']), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->when(request()?->search, function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
        })
        ->StoreOrder()->NotDigitalOrder()
        ->where('store_id',\App\CentralLogics\Helpers::get_store_id())
        ->orderBy('schedule_at', 'desc')
        ->paginate(config('default_pagination'));
        $status = $status;
        $can_vendor_edit_order = BusinessSetting::where('key', 'can_vendor_edit_order')->first()?->value ?? 0;
        $canEditOrder = (bool) $can_vendor_edit_order && (Helpers::get_store_data()?->storeConfig?->can_edit_order ?? false);
        return view('vendor-views.order.list', compact('orders', 'status', 'canEditOrder'));
    }


    public function export_orders($file_type, $status, $type, Request $request)
    {
        $key = explode(' ', request()?->search);
        Order::where(['checked' => 0])->where('store_id',Helpers::get_store_id())->update(['checked' => 1]);
        $orders = Order::with(['customer'])
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'confirmed', function($query){
            return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
        })
        ->when($status == 'pending', function($query){
            if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
            {
                return $query->where('order_status','pending');
            }
            else
            {
                return $query->where('order_status','pending')->where('order_type', 'take_away');
            }
        })
        ->when($status == 'cooking', function($query){
            return $query->where('order_status','processing');
        })
        ->when($status == 'item_on_the_way', function($query){
            return $query->where('order_status','picked_up');
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'ready_for_delivery', function($query){
            return $query->where('order_status','handover');
        })
        ->when($status == 'refund_requested', function($query){
            return $query->RefundRequest();
        })
        ->when($status == 'refunded', function($query){
            return $query->Refunded();
        })
        ->when($status == 'scheduled', function($query){
            return $query->Scheduled()->where(function($q){
                if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
                {
                    $q->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded']);
                }
                else
                {
                    $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                        $query->where('order_status','pending')->where('order_type', 'take_away');
                    });
                }

            });
        })
        ->when($status == 'all', function($query){
            return $query->where(function($query){
                $query->whereNotIn('order_status',(config('order_confirmation_model') == 'store'|| Helpers::get_store_data()->sub_self_delivery)?['failed','canceled', 'refund_requested', 'refunded']:['pending','failed','canceled', 'refund_requested', 'refunded'])
                ->orWhere(function($query){
                    return $query->where('order_status','pending')->where('order_type', 'take_away');
                });
            });
        })
        ->when(in_array($status, ['pending','confirmed']), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->when(request()?->search, function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
        })
        ->StoreOrder()->NotDigitalOrder()
        ->where('store_id',\App\CentralLogics\Helpers::get_store_id())
        ->orderBy('schedule_at', 'desc')
        ->get();

        $data = [
            'orders'=>$orders,
            'type'=>$type,
            'status'=>$status,
            'order_status'=>isset($request->orderStatus)?implode(', ', $request->orderStatus):null,
            'search'=>$request->search??null,
            'from'=>$request->from_date??null,
            'to'=>$request->to_date??null,
            'zones'=>isset($request->zone)?Helpers::get_zones_name($request->zone):null,
            'stores'=>isset($request->vendor)?Helpers::get_stores_name(Helpers::get_store_id()):null,
        ];

    if ($file_type == 'excel') {
        return Excel::download(new OrderExport($data), 'Orders.xlsx');
    } else if ($file_type == 'csv') {
        return Excel::download(new OrderExport($data), 'Orders.csv');
    }

    }



    public function details(Request $request,$id)
    {
        $order = Order::with(['details','offline_payments','orderEditLogs','customer'=>function($query){
            return $query->withCount('orders');
        },'delivery_man'=>function($query){
            return $query->withCount('orders');
        },'store' => function ($query) {
            return $query->with('storeConfig');
        },'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        },'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id, 'store_id' => Helpers::get_store_id()])->first();
        if (isset($order)) {
            $reasons=OrderCancelReason::where('status', 1)->where('user_type' ,'store' )->get();

            $editing = false;
            $cart = collect([]);
            if ($request->session()->has('order_cart')) {
                $sessionCart = session()->get('order_cart');
                if (count($sessionCart) > 0 && $sessionCart[0]->order_id == $order->id) {
                    $editing = true;
                    $cart = $sessionCart;
                } else {
                    session()->forget('order_cart');
                }
            }

            $can_vendor_edit_order = BusinessSetting::where('key', 'can_vendor_edit_order')->first()?->value ?? 0;
            $canEditOrder = (bool) $can_vendor_edit_order && ($order->store?->storeConfig?->can_edit_order ?? false);

            return view('vendor-views.order.order-view', compact('order' ,'reasons', 'editing', 'cart', 'canEditOrder'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }

    public function status(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'order_status' => 'required|in:confirmed,processing,handover,delivered,canceled',
            'reason' =>'required_if:order_status,canceled',
        ],[
            'id.required' => 'Order id is required!'
        ]);

        $order = Order::where(['id' => $request->id, 'store_id' => Helpers::get_store_id()])->first();

        if($order->delivered != null)
        {
            Toastr::warning(translate('messages.cannot_change_status_after_delivered'));
            return back();
        }

        if($request['order_status']=='canceled' && !config('canceled_by_store'))
        {
            Toastr::warning(translate('messages.you_can_not_cancel_a_order'));
            return back();
        }

        if($request['order_status']=='canceled' && $order->confirmed)
        {
            Toastr::warning(translate('messages.you_can_not_cancel_after_confirm'));
            return back();
        }



        if($request['order_status']=='delivered' && $order->order_type != 'take_away' && !$order->is_pos && !Helpers::get_store_data()->sub_self_delivery)
        {
            Toastr::warning(translate('messages.you_can_not_delivered_delivery_order'));
            return back();
        }

        if($request['order_status'] =="confirmed")
        {
            if(!Helpers::get_store_data()->sub_self_delivery && config('order_confirmation_model') == 'deliveryman' && $order->order_type != 'take_away' && !$order->is_pos)
            {
                Toastr::warning(translate('messages.order_confirmation_warning'));
                return back();
            }
        }

        if ($request->order_status == 'delivered') {
            $order_delivery_verification = (boolean)\App\Models\BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value;
            if($order_delivery_verification)
            {
                if($request->otp)
                {
                    if($request->otp != $order->otp)
                    {
                        Toastr::warning(translate('messages.order_varification_code_not_matched'));
                        return back();
                    }
                }
                else
                {
                    Toastr::warning(translate('messages.order_varification_code_is_required'));
                    return back();
                }
            }

            if($order->transaction  == null)
            {
                $unpaid_payment = OrderPayment::where('payment_status','unpaid')->where('order_id',$order->id)->first()?->payment_method;
                $unpaid_pay_method = 'digital_payment';
                if($unpaid_payment){
                    $unpaid_pay_method = $unpaid_payment;
                }
                if($order->payment_method == 'cash_on_delivery' || $unpaid_pay_method == 'cash_on_delivery')
                {
                    $ol = OrderLogic::create_transaction($order,'store', null);
                }
                else{
                    $ol = OrderLogic::create_transaction($order,'admin', null);
                }
                if(!$ol)
                {
                    Toastr::warning(translate('messages.faield_to_create_order_transaction'));
                    return back();
                }
                if($order->delivery_man_id){
                    Helpers::deliverymanLoyaltyPointHistory(deliveryManId:$order->delivery_man_id, amount: $order->order_amount, transactionType:'earn_on_order_completion' ,pointConversionType :'credit', reference: $order->id);
                }

            }

            $order->payment_status = 'paid';

            OrderLogic::update_unpaid_order_payment(order_id:$order->id, payment_method:$order->payment_method);

            $order->details->each(function($item, $key){
                if($item->item)
                {
                    $item->item->increment('order_count');
                }
            });
            if($order->is_guest == 0) {
            $order?->customer?->increment('order_count');
            }
        }
        if($request->order_status == 'canceled' || $request->order_status == 'delivered')
        {
            if($order->delivery_man)
            {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders>1?$dm->current_orders-1:0;
                $dm->save();
            }
            if($request->order_status == 'canceled'){

                $order->cancellation_reason = $request->reason;
                $order->canceled_by = 'store';

                $order?->store ?   Helpers::increment_order_count($order?->store) : '';

                if($order->is_guest == 0){

                    OrderLogic::refund_before_delivered($order);
                }

            $hasStock = config('module.' . $order->module->module_type)['stock'];
            $hasFlashDiscount = $order->flash_admin_discount_amount > 0 && $order->flash_store_discount_amount > 0;

            if ($hasStock || $hasFlashDiscount) {
                foreach ($order->details as $detail) {

                    $item = $detail->campaign ?? $detail->item;

                    if ($hasStock) {
                        $variant = json_decode($detail->variation, true);
                        $variantType = !empty($variant) ? $variant[0]['type'] : null;
                        ProductLogic::update_stock($item, -$detail->quantity, $variantType)?->save();
                    }

                    if ($hasFlashDiscount) {
                        ProductLogic::update_flash_stock($detail->item, $detail->quantity, true)?->save();
                    }
                }
            }

            }


        }

        if($request->order_status == 'delivered')
        {
            $order->store->increment('order_count');
            if($order->delivery_man)
            {
                $order->delivery_man->increment('order_count');
            }

        }

        $order->order_status = $request->order_status;
        if($request->order_status == 'processing') {
            $order->processing_time = ($request?->processing_time) ? $request->processing_time : explode('-', $order['store']['delivery_time'])[0];
        }
        else if ($order->order_type != 'parcel' && in_array($request->order_status, ['picked_up']) ) {
            Helpers::sendOrderDeliveryVerificationOtp($order);
        }

        $order[$request['order_status']] = now();
        $order->save();
        if(!Helpers::send_order_notification($order))
        {
            Toastr::warning(translate('messages.push_notification_faild'));
        }

        Toastr::success(translate('messages.order_status_updated'));
        return back();
    }

    public function update_shipping(Request $request, $id)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required'
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success('Delivery address updated!');
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::where(['id' => $id, 'store_id' => Helpers::get_store_id()])->first();
        return view('vendor-views.order.invoice', compact('order'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id, 'store_id' => Helpers::get_store_id()])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
    }

    public function edit_order_amount(Request $request)
    {

        $request->validate([
            'order_amount' => 'required',

        ]);

        $order = Order::find($request->order_id);
        if(!$order){
            Toastr::error(translate('messages.Order_not_found'));
            return back();
        }
        if(!in_array($order->order_status, ['pending','confirmed','processing','picked_up','handover','accepted']) ){
            Toastr::error(translate('messages.Order_can_not_edit_a_completed_order'));
            return back();
        }
        $store = Store::find($order->store_id);
        $coupon = null;
        $free_delivery_by = null;
        if ($order->coupon_code) {
            $coupon = Coupon::active()->where(['code' => $order->coupon_code])->first();
            if (isset($coupon)) {
                $staus = CouponLogic::is_valide($coupon, $order->user_id, $order->store_id);
                if ($staus == 407) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_expire')]
                        ]
                    ], 407);
                } else if ($staus == 406) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_usage_limit_over')]
                        ]
                    ], 406);
                } else if ($staus == 409) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_not_valid_for_this_zone')]
                        ]
                    ], 403);
                } else if ($staus == 404) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.not_found')]
                        ]
                    ], 404);
                }
            } else {
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.not_found')]
                    ]
                ], 404);
            }
        }

        $product_price = $request->order_amount;
        $total_addon_price = 0;
        $store_discount_amount = $order->store_discount_amount;

        $discount_on_product_by = $order->discount_on_product_by ?? 'vendor' ;

        $store_discount = Helpers::get_store_discount($store);
        $store_discount =  $store_discount ? $store_discount : ['discount' => 0, 'max_discount' => 0, 'min_purchase' => 0];
        $admin_discount = Helpers::checkAdminDiscount(price: $product_price + $total_addon_price, discount: $store_discount['discount'], max_discount: $store_discount['max_discount'], min_purchase: $store_discount['min_purchase']);

        $discount = $admin_discount;

        if($admin_discount > 0 && $discount == $admin_discount ){
                $discount_on_product_by =  'admin' ;
            }


        $order->discount_on_product_by= $discount_on_product_by;
        $store_discount_amount=$discount;
        $additionalCharges=[];


        $coupon_discount_amount = $coupon ? CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
        $total_price = $product_price + $total_addon_price - $store_discount_amount - $coupon_discount_amount;
        $total_price = max($total_price, 0);

        $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
        if (isset($free_delivery_over)) {
            if ($free_delivery_over <= $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount) {
                $order->delivery_charge = 0;
                $free_delivery_by = 'admin';
            }
        }

        if ($store->free_delivery) {
            $order->delivery_charge = 0;
            $free_delivery_by = 'vendor';
        }

        if ($coupon) {
            if ($coupon->coupon_type == 'free_delivery') {
                if ($coupon->min_purchase <= $product_price + $total_addon_price - $store_discount_amount) {
                    $order->delivery_charge = 0;
                    $free_delivery_by = 'admin';
                }
            }
        }

        $proRecompute = $this->recomputeOrderProDiscountOnEdit(
            order: $order,
            subtotal: $product_price + $total_addon_price,
            totalPrice: $total_price,
            moduleType: $store?->module?->module_type,
            deliveryCharge: (float) $order->delivery_charge,
        );
        $pro_discount_amount = (float) $proRecompute['discount'];
        $total_price         = (float) $proRecompute['total_price'];
        $order->delivery_charge = (float) $proRecompute['delivery_charge'];
        if ($proRecompute['delivery_savings'] > 0) {
            $free_delivery_by = $proRecompute['free_delivery_by'];
        }


   $settings = BusinessSetting::whereIn('key', [
                'dm_tips_status',
                'additional_charge_status',
                'additional_charge',
                'extra_packaging_data',
            ])->pluck('value', 'key');

            $dm_tips_manage_status     = $settings['dm_tips_status'] ?? null;
            $additional_charge_status  = $settings['additional_charge_status'] ?? null;
            $additional_charge         = $settings['additional_charge'] ?? null;

            $extra_packaging_data_raw  = $settings['extra_packaging_data'] ?? '';
            $extra_packaging_data      = json_decode($extra_packaging_data_raw, true) ?? [];


            if ($dm_tips_manage_status == 1) {
                $order->dm_tips =$order->dm_tips ?? $request->dm_tips ?? 0;
            } else{
                $order->dm_tips = 0;
            }

            $order->additional_charge =$order->additional_charge;

            if ($additional_charge_status == 1) {
                $order->additional_charge = $additional_charge ?? 0;
            }




            $taxData =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                    amount: $total_price,
                    productIds: [],
                    taxPayer: 'prescription',
                    storeData: true,
                    additionalCharges: $additionalCharges,
                    addonIds: [],
                    orderId: null,
                    storeId:  $store->id
                );

                $tax_amount = $taxData['totalTaxamount'];
                $tax_included = $taxData['include'];
                $orderTaxIds = $taxData['orderTaxIds'] ?? [];
                $tax_status = $tax_included ?  'included' : 'excluded';

                $order->total_tax_amount = round($tax_amount, config('round_up_to_digit'));
                $order->tax_status = $tax_status;

        $order->coupon_discount_amount = round($coupon_discount_amount, config('round_up_to_digit'));
        $order->coupon_discount_title = $coupon ? $coupon->title : '';

        $order->store_discount_amount = round($store_discount_amount, config('round_up_to_digit'));
        $order->order_amount = round($total_price + $order->total_tax_amount + $order->additional_charge + $order->delivery_charge, config('round_up_to_digit'));
        $order->free_delivery_by = $free_delivery_by;
        $order->order_amount = DeliveryFeeLogic::applyDeliveryTypeToAmount($order, (float) $order->order_amount);
        $order->order_amount = $order->order_amount + $order->dm_tips;
        $order->save();
            $order?->orderTaxes()?->delete();
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $order->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
        Toastr::success(translate('messages.order_amount_updated'));
        return back();
    }
    public function edit_discount_amount(Request $request)
    {
        $request->validate([
            'discount_amount' => 'required',

        ]);

        $order = Order::find($request->order_id);
        if(!$order){
            Toastr::error(translate('messages.Order_not_found'));
            return back();
        }

        if(!in_array($order->order_status, ['pending','confirmed','processing','picked_up','handover','accepted']) ){
            Toastr::error(translate('messages.Order_can_not_edit_a_completed_order'));
            return back();
        }
        $product_price = $order['order_amount']-$order['delivery_charge']-$order['total_tax_amount']-$order['dm_tips'] - $order->additional_charge  +$order->store_discount_amount;
        $existing_pro_saved = (float) ($order->orderProDiscount?->amount_saved ?? 0);
        $product_price += $existing_pro_saved;


        if($request->discount_amount > $product_price)
        {
            Toastr::error(translate('messages.discount_amount_is_greater_then_product_amount'));
            return back();
        }
        $order->store_discount_amount = round($request->discount_amount, config('round_up_to_digit'));

        $order->discount_on_product_by= 'vendor';

        $proStore  = Store::with('module')->find($order->store_id);
        $afterDisc = max((float) ($product_price - $request->discount_amount), 0);
        $proRecompute = $this->recomputeOrderProDiscountOnEdit(
            order: $order,
            subtotal: (float) $product_price,
            totalPrice: $afterDisc,
            moduleType: $proStore?->module?->module_type,
            deliveryCharge: (float) $order->delivery_charge,
        );
        $pro_discount_amount     = (float) $proRecompute['discount'];
        $pro_delivery_savings    = (float) $proRecompute['delivery_savings'];
        $order->delivery_charge  = (float) $proRecompute['delivery_charge'];
        if ($pro_delivery_savings > 0) {
            $order->free_delivery_by = $proRecompute['free_delivery_by'];
        }

        $settings = BusinessSetting::whereIn('key', [
                'dm_tips_status',
                'additional_charge_status',
                'additional_charge',
                'extra_packaging_data',
            ])->pluck('value', 'key');

            $dm_tips_manage_status     = $settings['dm_tips_status'] ?? null;
            $additional_charge_status  = $settings['additional_charge_status'] ?? null;
            $additional_charge         = $settings['additional_charge'] ?? null;

            $extra_packaging_data_raw  = $settings['extra_packaging_data'] ?? '';
            $extra_packaging_data      = json_decode($extra_packaging_data_raw, true) ?? [];


            if ($dm_tips_manage_status == 1) {
                $order->dm_tips =$order->dm_tips ?? $request->dm_tips ?? 0;
            } else{
                $order->dm_tips = 0;
            }

            $order->additional_charge =$order->additional_charge;

            if ($additional_charge_status == 1) {
                $order->additional_charge = $additional_charge ?? 0;
            }






         $taxData =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                    amount: $product_price - $request->discount_amount - $pro_discount_amount,
                    productIds: [],
                    taxPayer: 'prescription',
                    storeData: true,
                    additionalCharges: [],
                    addonIds: [],
                    orderId: null,
                    storeId:  $order->store_id
                );

                $tax_amount = $taxData['totalTaxamount'];
                $tax_included = $taxData['include'];
                $orderTaxIds = $taxData['orderTaxIds'] ?? [];
                $tax_status = $tax_included ?  'included' : 'excluded';

                $order->total_tax_amount = round($tax_amount, config('round_up_to_digit'));
                $order->tax_status = $tax_status;



        $order->order_amount = $product_price+$order['delivery_charge']+ $order->total_tax_amount +$order['dm_tips'] + $order->additional_charge  -$order->store_discount_amount - $pro_discount_amount;
        $order->save();
        $order?->orderTaxes()?->delete();
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $order->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
        Toastr::success(translate('messages.discount_amount_updated'));
        return back();
    }

    public function add_order_proof(Request $request, $id)
    {
        $order = Order::find($id);
        $img_names = $order->order_proof?json_decode($order->order_proof):[];
        $images = [];
        $total_file = count($request->order_proof) + count($img_names);
        if(!$img_names){
            $request->validate([
                'order_proof' => 'required|array|max:5',
            ]);
        }

        if ($total_file>5) {
            Toastr::error(translate('messages.order_proof_must_not_have_more_than_5_item'));
            return back();
        }

        if (!empty($request->file('order_proof'))) {
            foreach ($request->order_proof as $img) {
                $image_name = Helpers::upload('order/', 'png', $img);
                array_push($img_names, ['img'=>$image_name, 'storage'=> Helpers::getDisk()]);
            }
            $images = $img_names;
        }

        if(count($images)>0){
            $order->order_proof = json_encode($images);
        }
        $order->save();

        Toastr::success(translate('messages.order_proof_added'));
        return back();
    }


    public function remove_proof_image(Request $request)
    {
        $order = Order::find($request['id']);
        $array = [];
        $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : [];
        if (count($proof) < 2) {
            Toastr::warning(translate('all_image_delete_warning'));
            return back();
        }

        Helpers::check_and_delete('order/' , $request['name']);

        foreach ($proof as $image) {
            if ($image != $request['name']) {
                array_push($array, $image);
            }
        }
        Order::where('id', $request['id'])->update([
            'order_proof' => json_encode($array),
        ]);
        Toastr::success(translate('order_proof_image_removed_successfully'));
        return back();
    }

    public function add_to_cart(Request $request)
    {
        if ($request->item_type == 'item') {
            $product = Item::withoutGlobalScope(StoreScope::class)->find($request->id);
        } else {
            $product = ItemCampaign::find($request->id);
        }

        if (!$product) {
            return response()->json([
                'data' => 'variation_error',
                'message' => translate('messages.item_not_found'),
            ]);
        }

        $moduleType     = $product->module?->module_type;
        $moduleHasStock = (bool) data_get(config('module.' . $moduleType), 'stock', false);
        $requestedQty   = (int) ($request['quantity'] ?? 1);

        if (isset($product->module_id) && $product->module->module_type == 'food' && $product->food_variations) {
            $data = new OrderDetail();
            if ($request->order_details_id) {
                $data['id'] = $request->order_details_id;
            }

            $data['item_id'] = $request->item_type == 'item' ? $product->id : null;
            $data['item_campaign_id'] = $request->item_type == 'campaign' ? $product->id : null;
            $data['item'] = $request->item_type == 'item' ? $product : null;
            $data['item_campaign'] = $request->item_type == 'campaign' ? $product : null;
            $data['order_id'] = $request->order_id;
            $variations = [];
            $price = 0;
            $addon_price = 0;
            $variation_price = 0;

            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && $product_variations && count($product_variations)) {
                foreach ($request->variations  as $key => $value) {

                    if ($value['required'] == 'on' &&  isset($value['values']) == false) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select items from') . ' ' . $value['name'],
                        ]);
                    }
                    if (isset($value['values'])  && $value['min'] != 0 && $value['min'] > count($value['values']['label'])) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select minimum ') . $value['min'] . translate('For') . $value['name'] . '.',
                        ]);
                    }
                    if (isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select maximum ') . $value['max'] . translate('For') . $value['name'] . '.',
                        ]);
                    }
                }
                $variation_data = Helpers::get_varient($product_variations, $request->variations);
                $variation_price = $variation_data['price'];
                $variations = $variation_data['variations'];
            }
            $price = $product->price + $variation_price;
            $data['variation'] = json_encode($variations);
            $data['variant'] = '';
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['status'] = true;
            $data['discount_on_item'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data["discount_type"] = "discount_on_product";
            $data["tax_amount"] = Helpers::tax_calculate($product, $price);
            $add_ons = [];
            $add_on_qtys = [];

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $add_on_qtys[] = $request['addon-quantity' . $id];
                }
                $add_ons = $request['addon_id'];
            }

            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withOutGlobalScope(StoreScope::class)->whereIn('id', $add_ons)->get(), $add_on_qtys);
            $data['add_ons'] = json_encode($addon_data['addons']);
            $data['total_add_on_price'] = $addon_data['total_add_on_price'];
            $cart = $request->session()->get('order_cart', collect([]));

            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;

                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);

                return response()->json([
                    'data' => 2
                ]);
            } else {
                $newVariationKey = $data['variation'];
                $newAddonsKey    = $data['add_ons'];
                $duplicateKey    = null;
                foreach ($cart as $existingKey => $existingItem) {
                    if (!$existingItem || !$existingItem['status']) {
                        continue;
                    }
                    if ($existingItem['item_id'] != $product->id) {
                        continue;
                    }
                    if ((string) $existingItem['variation'] === (string) $newVariationKey
                        && (string) $existingItem['add_ons'] === (string) $newAddonsKey) {
                        $duplicateKey = $existingKey;
                        break;
                    }
                }
                if ($duplicateKey !== null) {
                    $cart[$duplicateKey]['quantity'] += $requestedQty;
                    $request->session()->put('order_cart', $cart);
                    $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                    return response()->json(['data' => 0, 'cart_key' => $duplicateKey]);
                }
                $cart->push($data);
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);

            }

        } else {

            $data = new OrderDetail();
            if ($request->order_details_id) {
                $data['id'] = $request->order_details_id;
            }

            $data['item_id'] = $request->item_type == 'item' ? $product->id : null;
            $data['item_campaign_id'] = $request->item_type == 'campaign' ? $product->id : null;
            $data['order_id'] = $request->order_id;
            $str = '';
            $price = 0;
            $addon_price = 0;

            $choiceOptions = $product->choice_options ? (json_decode($product->choice_options) ?: []) : [];
            foreach ($choiceOptions as $key => $choice) {
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name] ?? '');
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name] ?? '');
                }
            }
            $data['variant'] = json_encode([]);
            $data['variation'] = json_encode([]);

            $resolvedStock = $moduleHasStock ? ($product->stock ?? null) : null;
            if ($str != null) {
                $productVariations = $product->variations ? (json_decode($product->variations) ?: []) : [];
                $matched = false;
                foreach ($productVariations as $vr) {
                    if (isset($vr->type) && $vr->type == $str) {
                        $price = $vr->price;
                        $resolvedStock = isset($vr->stock) ? (int) $vr->stock : 0;
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    $data['variation'] = json_encode([["type" => $str, "price" => $price, "stock" => $resolvedStock]]);
                }
            } else {
                $price = $product->price;
            }

            if ($moduleHasStock && $resolvedStock !== null && $resolvedStock <= 0) {
                return response()->json([
                    'data' => 'stock_error',
                    'message' => translate('messages.out_of_stock'),
                ]);
            }

            $cart = $request->session()->get('order_cart', collect([]));
            if (!isset($request->cart_item_key) && count($cart) > 0) {
                foreach ($cart as $existingKey => $cartItem) {
                    if (!$cartItem || !$cartItem['status']) {
                        continue;
                    }
                    if ($cartItem['item_id'] != $request['id']) {
                        continue;
                    }
                    $existingVariation = json_decode($cartItem['variation'], true) ?: [];
                    $variationMatches = false;
                    if (count($existingVariation) > 0) {
                        if (($existingVariation[0]['type'] ?? null) == $str) {
                            $variationMatches = true;
                        }
                    } else if ($str == '') {
                        $variationMatches = true;
                    }
                    if (!$variationMatches) {
                        continue;
                    }
                    $newQty = $cartItem['quantity'] + $requestedQty;
                    if ($moduleHasStock && $resolvedStock !== null && $newQty > $resolvedStock) {
                        return response()->json([
                            'data' => 'stock_error',
                            'message' => translate('messages.requested_quantity_exceeds_stock'),
                        ]);
                    }
                    $cart[$existingKey]['quantity'] = $newQty;
                    $request->session()->put('order_cart', $cart);
                    $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                    return response()->json(['data' => 0, 'cart_key' => $existingKey]);
                }
            }

            if ($moduleHasStock && $resolvedStock !== null && $requestedQty > $resolvedStock) {
                return response()->json([
                    'data' => 'stock_error',
                    'message' => translate('messages.requested_quantity_exceeds_stock'),
                ]);
            }

            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['status'] = true;
            $data['discount_on_item'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data["discount_type"] = "discount_on_product";
            $data["tax_amount"] = Helpers::tax_calculate($product, $price);
            $add_ons = [];
            $add_on_qtys = [];

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $add_on_qtys[] = $request['addon-quantity' . $id];
                }
                $add_ons = $request['addon_id'];
            }

            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withoutGlobalScope(StoreScope::class)->whereIn('id', $add_ons)->get(), $add_on_qtys);
            $data['add_ons'] = json_encode($addon_data['addons']);
            $data['total_add_on_price'] = $addon_data['total_add_on_price'];


            $cart = $request->session()->get('order_cart', collect([]));
            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                return response()->json([
                    'data' => 2
                ]);
            } else {
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                $cart->push($data);
            }
        }

        $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);

        return response()->json([
            'data' => 0
        ]);
    }

    public function update_cart_quantity(Request $request)
    {
        $cart = $request->session()->get('order_cart', collect([]));
        $key = $request->key;

        if (!isset($cart[$key])) {
            return response()->json([
                'data' => 'not_found',
                'message' => translate('messages.cart_item_not_found'),
            ]);
        }

        $newQty = max(1, (int) $request->quantity);
        $cartItem = $cart[$key];
        $itemId = $cartItem['item_id'] ?? null;
        $isPreexisting = isset($cartItem->id);

        if ($isPreexisting) {
            $cart[$key]['quantity'] = $newQty;
            $request->session()->put('order_cart', $cart);
            $product = $itemId ? Item::withoutGlobalScope(StoreScope::class)->with('store')->find($itemId) : null;
            if ($product && $product->store) {
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $cartItem['order_id'] ?? null);
            }
            return response()->json(['data' => 0, 'quantity' => $newQty]);
        }

        if (!$itemId) {
            return response()->json([
                'data' => 'not_found',
                'message' => translate('messages.cart_item_not_found'),
            ]);
        }

        $product = Item::withoutGlobalScope(StoreScope::class)->with(['module', 'store'])->find($itemId);
        if (!$product || !$product->module) {
            return response()->json([
                'data' => 'not_found',
                'message' => translate('messages.product_not_found'),
            ]);
        }

        $moduleType = $product->module->module_type;
        $moduleHasStock = (bool) data_get(config('module.' . $moduleType), 'stock', false);

        if ($moduleHasStock) {
            $variation = is_string($cartItem['variation']) ? (json_decode($cartItem['variation'], true) ?: []) : (is_array($cartItem['variation'] ?? null) ? $cartItem['variation'] : []);
            $variantType = $variation[0]['type'] ?? null;
            $availableStock = (int) ($product->stock ?? 0);
            if ($variantType) {
                $matrix = $product->variations ? (json_decode($product->variations, true) ?: []) : [];
                foreach ($matrix as $v) {
                    if (isset($v['type']) && $v['type'] === $variantType) {
                        $availableStock = (int) ($v['stock'] ?? 0);
                        break;
                    }
                }
            }

            if ($newQty > $availableStock) {
                return response()->json([
                    'data' => 'stock_error',
                    'message' => translate('messages.requested_quantity_exceeds_stock'),
                ]);
            }
        }

        if ($product->maximum_cart_quantity && $newQty > $product->maximum_cart_quantity) {
            return response()->json([
                'data' => 'stock_error',
                'message' => translate('messages.maximum_cart_quantity_limit_over'),
            ]);
        }

        $cart[$key]['quantity'] = $newQty;
        $request->session()->put('order_cart', $cart);

        if ($product->store) {
            $this->setOrderEditCalculatedTax(store: $product->store, order_id: $cartItem['order_id'] ?? null);
        }

        return response()->json(['data' => 0, 'quantity' => $newQty]);
    }

    public function remove_from_cart(Request $request)
    {
        $cart = $request->session()->get('order_cart', collect([]));
        $item_id = $cart[$request->key]['item_id'];
        $cart[$request->key]->status = false;
        $request->session()->put('order_cart', $cart);

        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($item_id);
        if ($product && $product->store) {
            $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
        }
        return response()->json([], 200);
    }

    public function edit(Request $request, Order $order)
    {
        $order = Order::with(['details', 'store', 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $order->id, 'store_id' => Helpers::get_store_id()])->StoreOrder()->first();

        if (!$order) {
            Toastr::error(translate('messages.order_not_found'));
            return back();
        }

        if ($request->cancle) {
            if ($request->session()->has(['order_cart'])) {
                session()->forget(['order_cart']);
            }
            return back();
        }

        $cart = collect([]);
        foreach ($order->details as $details) {
            unset($details['item_details']);
            $details['status'] = true;
            $cart->push($details);
        }

        if ($cart->isEmpty()) {
            Toastr::error(translate('messages.cart_is_empty'));
            return back();
        }

        if ($request->session()->has('order_cart')) {
            $existing = session()->get('order_cart');
            if (count($existing) > 0 && $existing[0]->order_id == $order->id) {
                session()->put('open_edit_offcanvas', true);
            } else {
                session()->forget('order_cart');
                $request->session()->put('order_cart', $cart);
                $request->session()->put('open_edit_offcanvas', true);
                $this->setOrderEditCalculatedTax(store: $order->store, order_id: $order->id);
            }
        } else {
            $request->session()->put('order_cart', $cart);
            $request->session()->put('open_edit_offcanvas', true);
            $this->setOrderEditCalculatedTax(store: $order->store, order_id: $order->id);
        }

        return back();
    }

    public function update(Request $request, Order $order)
    {
        $order = Order::with(['details', 'store.module', 'payments'])
            ->where(['id' => $order->id, 'store_id' => Helpers::get_store_id()])
            ->StoreOrder()
            ->first();

        return $this->updateOrderFromCartRequest($request, $order, 'vendor');
    }

    public function quick_view(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)
            ->where('store_id', Helpers::get_store_id())
            ->findOrFail($request->product_id);
        $item_type = 'item';
        $order_id = $request->order_id;
        $panel = 'vendor';

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.order.partials._quick-view', compact('product', 'order_id', 'item_type', 'panel'))->render(),
        ]);
    }

    public function quick_view_cart_item(Request $request)
    {
        $cart_item = session('order_cart')[$request->key];
        $order_id = $request->order_id;
        $item_key = $request->key;
        $product = $cart_item->item ? $cart_item->item : $cart_item->campaign;
        $item_type = $cart_item->item ? 'item' : 'campaign';
        $panel = 'vendor';

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.order.partials._quick-view-cart-item', compact('order_id', 'product', 'cart_item', 'item_key', 'item_type', 'panel'))->render(),
        ]);
    }

    public function cart_list(Request $request)
    {
        $order = Order::with(['details', 'store.module'])
            ->where('store_id', Helpers::get_store_id())
            ->find($request->order_id);
        if (!$order) {
            return response()->json(['view' => '', 'count' => 0]);
        }

        $editing = false;
        $cart = collect([]);
        if ($request->session()->has('order_cart')) {
            $sessionCart = session()->get('order_cart');
            if (count($sessionCart) > 0 && $sessionCart[0]->order_id == $order->id) {
                $editing = true;
                $cart = $sessionCart;
            }
        }

        return response()->json([
            'view' => view('admin-views.order.partials._edit_cart_list', compact('order', 'editing', 'cart'))->render(),
            'count' => $editing ? $cart->count() : $order->details->count(),
        ]);
    }

    public function search_items(Request $request)
    {
        $storeId = Helpers::get_store_id();
        if ($request->store_id && (int) $request->store_id !== (int) $storeId) {
            return response()->json(['items' => []]);
        }

        $products = Item::withoutGlobalScope(StoreScope::class)
            ->with(['module', 'store.storeConfig'])
            ->where('store_id', $storeId)
            ->when($request->keyword, function ($query) use ($request) {
                $keywords = array_filter(array_map('trim', explode(' ', $request->keyword)));
                return $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $word) {
                        $q->where(function ($sub) use ($word) {
                            $sub->where('name', 'like', "%{$word}%")
                                ->orWhereHas('translations', function ($t) use ($word) {
                                    $t->where('key', 'name')
                                        ->where('locale', app()->getLocale())
                                        ->where('value', 'like', "%{$word}%");
                                });
                        });
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
            $availableTime = ($p->available_time_starts && $p->available_time_ends)
                ? date(config('timeformat'), strtotime($p->available_time_starts)) . ' - ' . date(config('timeformat'), strtotime($p->available_time_ends))
                : null;
            $isAvailable = $p->is_available_now;
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
                'veg'             => $showVeg ? (int) $p->veg : null,
                'is_halal'        => $showHalal,
                'tracks_stock'    => $tracksStock,
                'stock'           => $stock,
            ];
        });

        return response()->json(['items' => $items]);
    }

    public function checkValidity($carts, $store, $request, $originalDetailQtys = [])
    {
        if ($carts->isEmpty()) {
            return [
                'status_code' => 403,
                'code' => 'not_found',
                'message' => translate('Cart is empty'),
            ];
        }
        foreach ($carts as $c) {
            if (!isset($c['status']) || $c['status'] !== false) {
                $cartId = $c['id'] ?? null;
                if ($cartId && isset($originalDetailQtys[$cartId])) {
                    continue;
                }
                if (isset($c['item_type']) && ($c['item_type'] === 'App\Models\ItemCampaign' || $c['item_type'] === 'AppModelsItemCampaign')) {
                    $product = ItemCampaign::with('module')->active()->find($c['item_id']);
                } else {
                    $product = Item::with('module')->active()->find($c['item_id'] ?? $c['id']);
                }

                if ($product) {
                    if ($product->store_id != $store->id) {
                        return [
                            'status_code' => 403,
                            'code' => 'different_stores',
                            'message' => translate('messages.Please_select_items_from_the_same_store'),
                        ];
                    }

                    if ($product?->pharmacy_item_details?->is_prescription_required == '1' && empty($request->file('order_attachment'))) {
                        return [
                            'status_code' => 403,
                            'code' => 'prescription',
                            'message' => translate('messages.prescription_is_required_for_this_order'),
                        ];
                    }

                    if ($product?->maximum_cart_quantity && $c['quantity'] > $product?->maximum_cart_quantity) {
                        return [
                            'status_code' => 403,
                            'code' => 'quantity',
                            'message' => translate('messages.maximum_cart_quantity_limit_over'),
                        ];
                    }

                    if ($product?->module?->module_type != 'food') {
                        if (
                            is_array(json_decode($product['variations'], true)) && count(json_decode($product['variations'], true)) > 0 &&
                            is_array($c['variation']) && count($c['variation']) > 0
                        ) {
                            $variant_data = Helpers::variation_price($product, json_encode($c['variation']));
                            $stock = $variant_data['stock'];
                        } else {
                            $stock = $product?->stock;
                        }

                        if (config('module.' . $product->module->module_type)['stock']) {
                            $cartId = $c['id'] ?? null;
                            $alreadyReserved = ($cartId && isset($originalDetailQtys[$cartId])) ? (int) $originalDetailQtys[$cartId] : 0;
                            if ($c['quantity'] > ($stock + $alreadyReserved)) {
                                return [
                                    'status_code' => 403,
                                    'code' => 'stock',
                                    'message' => $product?->name . ' ' . translate('messages.is_out_of_stock')
                                ];
                            }
                        }
                    }
                } else {
                    return[
                        'status_code' => 403,
                        'code' => 'not_found',
                        'message' => translate('messages.product_not_found'),
                    ];
                }
            }
        }
        return [
            'status_code' => 200,
            'code' => 'success',
            'message' => translate('messages.order_updated_successfully'),
        ];
    }
}
