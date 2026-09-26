<?php

namespace App\Http\Controllers\Admin;

use App\Mail\OrderVerificationMail;
use App\Mail\PlaceOrder;
use App\Mail\UserOfflinePaymentMail;
use App\Models\Item;
use App\Models\Zone;
use App\Models\Order;
use App\Models\Store;
use App\Models\Coupon;
use App\Models\Refund;
use App\Models\Category;
use App\Scopes\ZoneScope;
use App\Scopes\StoreScope;
use App\Models\DeliveryMan;
use App\Models\OrderDetail;
use App\Models\Translation;
use App\Exports\OrderExport;
use App\Mail\RefundRejected;
use App\Models\ItemCampaign;
use App\Models\RefundReason;
use App\Traits\PlaceNewOrder;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\CouponLogic;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\CustomerLogic;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Exports\StoreOrderlistExport;
use App\Models\OrderPayment;
use App\Models\ParcelCancellationReason;
use Illuminate\Support\Facades\Config;
use MatanYadaev\EloquentSpatial\Objects\Point;

class OrderController extends Controller
{
    use PlaceNewOrder;
    use \App\Traits\EditsOrderFromCart;
    public function list($status, Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        if (session()->has('zone_filter') == false) {
            session()->put('zone_filter', 0);
        }
        $module_id = $request->query('module_id', null);
        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
        }
        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($q) use ($request) {
                    return $q->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->whereRaw('created_at <> schedule_at');
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'pending', function ($query) {
                return $query->Pending();
            })
            ->when($status == 'accepted', function ($query) {
                return $query->AccepteByDeliveryman();
            })
            ->when($status == 'processing', function ($query) {
                return $query->Preparing();
            })
            ->when($status == 'item_on_the_way', function ($query) {
                return $query->ItemOnTheWay();
            })
            ->when($status == 'delivered', function ($query) {
                return $query->Delivered();
            })
            ->when($status == 'canceled', function ($query) {
                return $query->Canceled();
            })
            ->when($status == 'failed', function ($query) {
                return $query->failed();
            })
            ->when($status == 'refunded', function ($query) {
                return $query->Refunded();
            })
            ->when($status == 'requested', function ($query) {
                return $query->Refund_requested();
            })
            ->when($status == 'rejected', function ($query) {
                return $query->Refund_request_canceled();
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->Scheduled();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(($status != 'all' && $status != 'scheduled' && $status != 'canceled' && $status != 'rejected' && $status != 'requested' && $status != 'refunded' && $status != 'delivered' && $status != 'failed'), function ($query) {
                return $query->OrderScheduledIn(30);
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->orderStatus) && $status == 'all', function ($query) use ($request) {
                return $query->whereIn('order_status', $request->orderStatus);
            })
            ->when(isset($request->order_type), function ($query) use ($request) {
                return $query->where('order_type', $request->order_type);
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->StoreOrder()
            ->module(Config::get('module.current_module_id'))
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        $orderstatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $order_type = isset($request->order_type) ? $request->order_type : null;
        $total = $orders->total();


        return view('admin-views.order.list', compact('orders', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total', 'order_type'));
    }

    public function dispatch_list($module,$status, Request $request)
    {
        $module_id = $request->query('module_id', null);
        $key = isset($request->search) ?explode(' ', $request->search): ($request['amp;search'] ? explode(' ', $request['amp;search']) : null) ;
        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
            $zone_ids = isset($request->zone) ? $request->zone : 0;
        }

        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->whereHas('module', function($query) use($module){
                $query->where('id', $module);
            })
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->StoreOrder()
            ->OrderScheduledIn(30)
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        $orderstatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $total = $orders->total();

        return view('admin-views.order.distaptch_list', compact('orders','module', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total'));
    }

    public function details(Request $request, $id)
    {
        $order = Order::with(['details','offline_payments','refund','orderEditLogs', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id])->first();
        if (isset($order)) {
            $isUnpaid = false;

            if (
                in_array($order->order_status, ['pending','failed']) &&
                !in_array($order->payment_method, ['cash_on_delivery', 'wallet'])
            ) {
                // CASE 1: partial payment
                if ($order->payment_method == 'partial_payment') {
                    if ($order->payment_method === 'partial_payment') {
                        $isUnpaid = $order->payments()
                            ->where('payment_status', 'unpaid')
                            ->whereNotIn('payment_method', ['cash_on_delivery', 'wallet'])
                            ->exists();
                    }

                }

                // CASE 2: offline payment
                elseif ($order->payment_method == 'offline_payment') {
                    if ($order?->offline_payments?->count() == 0) {
                        $isUnpaid = true;
                    }
                }

                // CASE 3: other online payments
                else {
                    $isUnpaid = true;
                }
            }

            $order->is_unpaid_order = $isUnpaid ? true : false;

            if($order->order_type == 'parcel'){
                return to_route('admin.parcel.order.details', $id);
            }
            $excludeDm = $order->delivery_man_id;

            if ($order->store) {
                $deliveryMen = $order->store->sub_self_delivery == 1
                    ? []
                    : DeliveryMan::where('zone_id', $order->store->zone_id)
                        ->when($order->dm_vehicle_id != null, function ($query) use ($order) {
                            $query->where(function ($query) use ($order) {
                                $query->where('vehicle_id', $order->dm_vehicle_id)
                                    ->orWhereNull('vehicle_id');
                            });
                        }, function ($query) {
                            $query->whereNull('store_id');
                        })
                        ->where('id', '!=', $excludeDm)
                        ->available()
                        ->active()
                        ->get();
            } else {
                $deliveryMen = DeliveryMan::whereNull('zone_id')
                    ->where('id', '!=', $excludeDm)
                    ->where('vehicle_id', $order->dm_vehicle_id)
                    ->active()
                    ->get();
            }


            $category = $request->query('category_id', 0);
            $categories = Category::active()->get();
            $keyword = $request->query('keyword', false);
            $key = explode(' ', $keyword);
            $products = Item::withoutGlobalScope(StoreScope::class)->where('store_id', $order->store_id)
                ->when($category, function ($query) use ($category) {
                    $query->whereHas('category', function ($q) use ($category) {
                        return $q->whereId($category)->orWhere('parent_id', $category);
                    });
                })
                ->when($keyword, function ($query) use ($key) {
                    return $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->active()->paginate(10);
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

            $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);
            return view($order->order_type == 'parcel' ? 'admin-views.order.parcel-order-view' : 'admin-views.order.order-view', compact('order', 'deliveryMen', 'categories', 'products', 'category', 'keyword', 'editing', 'cart'));
        } else {
            Toastr::info(translate('messages.no_more_orders'));
            return back();
        }
    }
    public function switch_to_cod($id){
        $order = Order::where('id', $id)->first();
        if($order){
            if($order->payment_method == 'cash_on_delivery'){
                Toastr::error(translate('messages.order_already_switched_to_cod'));
                return back();
            }

            $order?->offline_payments()->delete();

            if($order?->payment_method == 'partial_payment'){
                $order?->payments()->where('payment_status','unpaid')->update([
                    'payment_method'=> 'cash_on_delivery',
                ]);
            }

            if($order?->store?->is_valid_subscription == 1 && $order?->store?->store_sub?->max_order != "unlimited" &&
                $order?->store?->store_sub?->max_order > 0) {
                $order?->store?->store_sub?->decrement('max_order' , 1);
            }

            Helpers::send_order_notification($order);

            if($order->order_status != 'pending'){
                $order->order_status = 'pending';
            }

            $order->payment_method = 'cash_on_delivery';
            $order->save();

            if($order?->is_guest == 0){
                $this->sent_mail_on_offline_payment(status:'COD', name:$order?->customer?->f_name .' '.$order?->customer?->l_name,
                    email:  $order?->customer?->email ,order_id: $order->id);
            }

            Toastr::success(translate('messages.order_switched_to_cod'));
            return back();
        }

        Toastr::error(translate('messages.order_not_found'));
        return back();
    }

    public function all_details(Request $request, $id)
    {
        $order = Order::with(['details','offline_payments' ,'refund', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id])->first();
        if (isset($order)) {
            if (isset($order->store)) {
                $deliveryMen = DeliveryMan::where('zone_id', $order->store->zone_id)->available()->active()->get();
            } else {
                $deliveryMen = isset($order->zone_id) ? DeliveryMan::where('zone_id', $order->zone_id)->zonewise()->available()->active()->get() : [];
            }
            $category = $request->query('category_id', 0);
            // $sub_category = $request->query('sub_category', 0);
            $categories = Category::active()->get();
            $keyword = $request->query('keyword', false);
            $key = explode(' ', $keyword);
            $products = Item::withoutGlobalScope(StoreScope::class)->where('store_id', $order->store_id)
                ->when($category, function ($query) use ($category) {
                    $query->whereHas('category', function ($q) use ($category) {
                        return $q->whereId($category)->orWhere('parent_id', $category);
                    });
                })
                ->when($keyword, function ($query) use ($key) {
                    return $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(10);
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

            $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);
            return view($order->order_type == 'parcel' ? 'admin-views.order.parcel-order-view' : 'admin-views.order.order-view', compact('order', 'deliveryMen', 'categories', 'products', 'category', 'keyword', 'editing', 'cart'));
        } else {
            Toastr::info(translate('messages.no_more_orders'));
            return back();
        }
    }

    public function search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $parcel_order = $request->parcel_order ?? false;
        $module_section_type = $request->module_section_type ?? false;
        $orders = Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('order_status', 'like', "%{$value}%")
                    ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->module(Config::get('module.current_module_id'));
        if ($module_section_type) {
            $orders = $orders->module($module_section_type);
        }
        if ($parcel_order) {
            $orders = $orders->withOutGlobalScope(ZoneScope::class)->ParcelOrder();
        } else {
            $orders = $orders->StoreOrder();
        }
        $orders = $orders->limit(50)->get();

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders', 'parcel_order'))->render()
        ]);
    }

    public function status(Request $request)
    {
        $request->validate([
            'reason'=>'required_if:order_status,canceled'
        ]);

        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->withOutGlobalScope(ZoneScope::class)->find($request->id);

        if(!$order || (!$order->store && $order->order_type !='parcel') ){
            Toastr::warning(translate('messages.you_can_not_change_the_status_of_this_order'));
            return back();
        }

        if (in_array($order->order_status, ['refunded'])) {
            Toastr::warning(translate('messages.you_can_not_change_the_status_of_a_completed_order'));
            return back();
        }
        if (in_array($order->order_status, ['refund_requested']) && BusinessSetting::where(['key' => 'refund_active_status'])->first()->value == false) {
            Toastr::warning(translate('Refund Option is not active. Please active it from Refund Settings'));
            return back();
        }

        if ($order['delivery_man_id'] == null && $request->order_status == 'out_for_delivery') {
            Toastr::warning(translate('messages.please_assign_deliveryman_first'));
            return back();
        }

        if ($request->order_status == 'delivered' && $order['transaction_reference'] == null && !in_array($order['payment_method'], ['cash_on_delivery', 'wallet'])) {
            Toastr::warning(translate('messages.add_your_paymen_ref_first'));
            return back();
        }
        if ($request->order_status == 'delivered') {
            if ($order->transaction  == null) {
                $unpaid_payment = OrderPayment::where('payment_status','unpaid')->where('order_id',$order->id)->first()?->payment_method;
                $unpaid_pay_method = 'digital_payment';
                if($unpaid_payment){
                    $unpaid_pay_method = $unpaid_payment;
                }
                if ($order->payment_method == "cash_on_delivery" || $unpaid_pay_method == 'cash_on_delivery') {
                    if ($order->order_type == 'take_away') {
                        $ol = OrderLogic::create_transaction($order, 'store', null);
                    } else if ($order->delivery_man_id) {
                        $ol =  OrderLogic::create_transaction($order, 'deliveryman', null);
                    } else if ($order->user_id) {
                        $ol =  OrderLogic::create_transaction($order, false, null);
                    }
                } else {
                    $ol = OrderLogic::create_transaction($order, 'admin', null);
                }
                if (!$ol) {
                    Toastr::warning(translate('messages.faield_to_create_order_transaction'));
                    return back();
                } else {
                    if($order->delivery_man_id){
                        Helpers::deliverymanLoyaltyPointHistory(deliveryManId:$order->delivery_man_id, amount: $order->order_amount, transactionType:'earn_on_order_completion' ,pointConversionType :'credit', reference: $order->id);
                    }

                }
            } else if ($order->delivery_man_id) {
                $order->transaction->update(['delivery_man_id' => $order->delivery_man_id]);
            }

            $order->payment_status = 'paid';
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->increment('order_count');
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }
            $order->details->each(function ($item, $key) {
                if ($item->item) {
                    $item->item->increment('order_count');
                }
            });
            $order?->customer?->increment('order_count');
            if ($order->store) {
                $order->store->increment('order_count');
            }
            if ($order->parcel_category) {
                $order->parcel_category->increment('orders_count');
            }

            OrderLogic::update_unpaid_order_payment(order_id:$order->id, payment_method:$order->payment_method);

        }
        else if ($request->order_status == 'refunded' && BusinessSetting::where('key', 'refund_active_status')->first()->value == 1) {
            if ($order->payment_status == "unpaid") {
                Toastr::warning(translate('messages.you_can_not_refund_a_cod_order'));
                return back();
            }
            if (isset($order->delivered)) {
                $rt = OrderLogic::refund_order($order);
                if (!$rt) {
                    Toastr::warning(translate('messages.faield_to_create_order_transaction'));
                    return back();
                }
            }
            $refund_method = $request->refund_method  ?? 'manual';
            $wallet_status = BusinessSetting::where('key', 'wallet_status')->first()->value;
            $refund_to_wallet = BusinessSetting::where('key', 'wallet_add_refund')->first()->value;
            if ($order->payment_status == "paid" && $wallet_status == 1 && $refund_to_wallet == 1) {
                $refund_amount = round($order->order_amount - $order->delivery_charge - $order->dm_tips, config('round_up_to_digit'));
                CustomerLogic::create_wallet_transaction($order->user_id, $refund_amount, 'order_refund', $order->id);
                Toastr::info(translate('Refunded amount added to customer wallet'));
                $refund_method = 'wallet';
            } else {
                Toastr::warning(translate('Customer Wallet Refund is not active.Plase Manage the Refund Amount Manually'));
                $refund_method = $request->refund_method  ?? 'manual';
            }
            Refund::where('order_id', $order->id)->update([
                'order_status' => 'refunded',
                'admin_note' => $request->admin_note ?? null,
                'refund_status' => 'approved',
                'refund_method' => $refund_method,
            ]);
            $order?->store ?   Helpers::increment_order_count($order?->store) : '';

            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }

            try {


                if(Helpers::getNotificationStatusData('customer','customer_refund_request_approval','push_notification_status') && $order?->customer?->cm_firebase_token){
                    $data = [
                        'title' => translate('messages.order_refunded'),
                        'description' => translate('messages.Your_refund_request_has_been_approved'),
                        'order_id' => $order->id,
                        'image' => '',
                        'type' => 'order_status',
                        'order_status' => $order->order_status,
                    ];
                    Helpers::send_push_notif_to_device($order?->customer?->cm_firebase_token, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $order->user_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }



                if(config('mail.status') && $order?->customer?->email && Helpers::get_mail_status('refund_order_mail_status_user') == '1'  &&  Helpers::getNotificationStatusData('customer','customer_refund_request_approval','mail_status') ){
                    Mail::to($order->customer?->getRawOriginal('email'))->send(new \App\Mail\RefundedOrderMail($order->id));
                }
            } catch (\Throwable $th) {
                info($th->getMessage());
                Toastr::error(translate('messages.Failed_to_send_mail'));
            }
        }
        else if ($request->order_status == 'canceled') {
            if (in_array($order->order_status, ['delivered', 'canceled', 'refund_requested', 'refunded'])) {
                Toastr::warning(translate('messages.you_can_not_cancel_a_completed_order'));
                return back();
            }

            $order->cancellation_reason = $request->reason;
            $order->canceled_by = 'admin';

            $order?->store ?   Helpers::increment_order_count($order?->store) : '';


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



            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }
            if($order->is_guest == 0){

                OrderLogic::refund_before_delivered($order);
            }
        }
        else if ( $order->order_type != 'parcel' && in_array($request->order_status, ['picked_up']) ) {
            Helpers::sendOrderDeliveryVerificationOtp($order);
        }
        $order->order_status = $request->order_status;
        if($request->order_status == 'processing') {
            $order->processing_time = ($request?->processing_time) ? $request->processing_time : explode('-', $order['store']['delivery_time'])[0];
        }
        $order[$request->order_status] = now();
        $order->save();

        if (!Helpers::send_order_notification($order)) {
            Toastr::warning(translate('messages.push_notification_faild'));
        }

        Toastr::success(translate('messages.order_status_updated'));
        return back();
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {
        if ($delivery_man_id == 0) {
            return response()->json(['message'=> translate('messages.deliveryman_not_found')  ], 400);
        }
        $order = Order::withOutGlobalScope(ZoneScope::class)->find($order_id);

        $deliveryman = DeliveryMan::where('id', $delivery_man_id)->available()->active()->first();
        if ($order->delivery_man_id == $delivery_man_id) {
            return response()->json(['message'=> translate('messages.order_already_assign_to_this_deliveryman')  ], 400);
        }
        if ($deliveryman) {
            if ($deliveryman->current_orders >= config('dm_maximum_orders')) {
                return response()->json(['message'=> translate('messages.dm_maximum_order_exceed_warning')  ], 400);
            }

            $payments = $order->payments()->where('payment_method','cash_on_delivery')->exists();
            $cash_in_hand = $deliveryman?->wallet?->collected_cash ?? 0;
            $cash_in_hand_overflow_status = BusinessSetting::where('key', 'cash_in_hand_overflow_delivery_man')->first()?->value;
            $dm_max_cash=BusinessSetting::where('key','dm_max_cash_in_hand')->first();
            $value=  $dm_max_cash?->value ?? 0;

            if($cash_in_hand_overflow_status && ($order->payment_method == "cash_on_delivery" || $payments) && $cash_in_hand+$order->order_amount >= $value){
                return response()->json(['message'=> \App\CentralLogics\Helpers::format_currency($value) ." ".translate('max_cash_in_hand_exceeds')  ], 400);
            }

            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
                if (Helpers::getNotificationStatusData('deliveryman','deliveryman_order_assign_unassign','push_notification_status')) {
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => translate('messages.you_are_unassigned_from_a_order'),
                        'order_id' => '',
                        'image' => '',
                        'type' => 'unassign'
                    ];
                    Helpers::send_push_notif_to_device($dm->fcm_token, $data);

                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'delivery_man_id' => $dm->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

            }
            $order->delivery_man_id = $delivery_man_id;
            $order->order_status = in_array($order->order_status, ['pending', 'confirmed']) ? 'accepted' : $order->order_status;
            $order->accepted = now();
            $order->save();

            $deliveryman->current_orders = $deliveryman->current_orders + 1;
            $deliveryman->save();
            $deliveryman->increment('assigned_order_count');

            $fcm_token= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;
            $value = Helpers::order_status_update_message('accepted',$order->module->module_type,$order->customer?
            $order?->customer?->current_language_key:'en');
            $value = Helpers::text_variable_data_format(value:$value,store_name:$order->store?->name,order_id:$order->id,user_name:"{$order?->customer?->f_name} {$order?->customer?->l_name}",delivery_man_name:"{$order->delivery_man?->f_name} {$order->delivery_man?->l_name}");
            try {
                if ($value  && Helpers::getNotificationStatusData('customer','customer_order_notification','push_notification_status') && $fcm_token ) {
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order_status'
                    ];
                        Helpers::send_push_notif_to_device($fcm_token, $data);
                        DB::table('user_notifications')->insert([
                            'data' => json_encode($data),
                            'user_id' => $order?->customer?->id ,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                }

                if(Helpers::getNotificationStatusData('deliveryman','deliveryman_order_assign_unassign','push_notification_status')){
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => translate('messages.you_are_assigned_to_a_order'),
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order_status'
                    ];
                    Helpers::send_push_notif_to_device($deliveryman->fcm_token, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'delivery_man_id' => $deliveryman->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

            } catch (\Exception $e) {
                info($e->getMessage());
                Toastr::warning(translate('messages.push_notification_faild'));
            }
            return response()->json([], 200);
        }
        return response()->json(['message' => 'Deliveryman not available!'], 400);
    }

    public function update_shipping(Request $request, Order $order)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
        ]);
        if ($request->latitude && $request->longitude) {
            $zone = Zone::where('id', $order->store->zone_id)->whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))->first();
            if (!$zone) {
                Toastr::error(translate('messages.out_of_coverage'));
                return back();
            }
        }
        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude
        ];

        $order->delivery_address = json_encode($address);
        $order->save();
        Toastr::success(translate('messages.delivery_address_updated'));
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::withOutGlobalScope(ZoneScope::class)->with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where('id', $id)->first();
        return view('admin-views.order.invoice', compact('order'));
    }

    public function print_invoice($id)
    {
        $order = Order::withOutGlobalScope(ZoneScope::class)->with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where('id', $id)->first();
        return view('admin-views.order.invoice-print', compact('order'))->render();
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        $request->validate([
            'transaction_reference' => 'max:30'
        ]);
        Order::where(['id' => $id])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success(translate('messages.payment_reference_code_is_added'));
        return back();
    }

    public function add_order_proof(Request $request, $id)
    {
        if($request->order_proof == null ){
            Toastr::error(translate('messages.Must_select_an_Image'));
            return back();
        }

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

    public function restaurnt_filter($id)
    {
        session()->put('restaurnt_filter', $id);
        return back();
    }

    public function filter(Request $request)
    {
        $request->validate([
            'from_date' => 'required_if:to_date,true',
            'to_date' => 'required_if:from_date,true',
        ]);
        session()->put('order_filter', json_encode($request->all()));
        return back();
    }
    public function filter_reset(Request $request)
    {
        session()->forget('order_filter');
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
            // $data['variation_price'] = $variation_price;
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

               $this->setOrderEditCalculatedTax(store:$product->store, order_id:$request->order_id);

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
              $this->setOrderEditCalculatedTax(store:$product->store, order_id:$request->order_id);

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
            //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
            foreach ($choiceOptions as $key => $choice) {
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name] ?? '');
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name] ?? '');
                }
            }
            $data['variant'] = json_encode([]);
            $data['variation'] = json_encode([]);

            //Check the string and resolve variation price + stock
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
            $this->setOrderEditCalculatedTax(store:$product->store,order_id: $request->order_id);
        }
        return response()->json([], 200);
    }

    public function edit(Request $request, Order $order)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $order->id])->StoreOrder()->first();
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
        if($cart->isEmpty()){
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
                $this->setOrderEditCalculatedTax(store:$order->store, order_id: $order->id);
            }
        } else {
            $request->session()->put('order_cart', $cart);
            $request->session()->put('open_edit_offcanvas', true);
            $this->setOrderEditCalculatedTax(store:$order->store, order_id: $order->id);
        }
        return back();
    }

    public function update(Request $request, Order $order)
    {
        $order = Order::with(['details', 'store.module', 'payments'])
            ->where(['id' => $order->id])
            ->StoreOrder()
            ->first();

        return $this->updateOrderFromCartRequest($request, $order, 'admin');
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

    public function quick_view(Request $request)
    {

        $product =  Item::findOrFail($request->product_id);
        $item_type = 'item';
        $order_id = $request->order_id;
        $panel = 'admin';

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
        $panel = 'admin';

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.order.partials._quick-view-cart-item', compact('order_id', 'product', 'cart_item', 'item_key', 'item_type', 'panel'))->render(),
        ]);
    }

    public function cart_list(Request $request)
    {
        $order = Order::with(['details', 'store.module'])->find($request->order_id);
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
        $products = Item::withoutGlobalScope(StoreScope::class)
            ->with(['module', 'store.storeConfig'])
            ->where('store_id', $request->store_id)
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
            if ($tracksStock) {
                $variationStocks = $p->variations ? json_decode($p->variations, true) : [];
                if (is_array($variationStocks) && count($variationStocks) > 0) {
                    $stock = (int) array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $variationStocks));
                }
            }
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
                'tracks_stock'    => $tracksStock,
                'stock'           => $stock,
                'veg'             => $showVeg ? (int) $p->veg : null,
                'is_halal'        => $showHalal,
            ];
        });

        return response()->json(['items' => $items]);
    }

    public function export_orders($file_type, $status, $type, Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        if (session()->has('zone_filter') == false) {
            session()->put('zone_filter', 0);
        }

        $module_id = $request->query('module_id', null);

        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
        }

        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store', 'orderProDiscount'])
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($q) use ($request) {
                    return $q->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->whereRaw('created_at <> schedule_at');
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'pending', function ($query) {
                return $query->Pending();
            })
            ->when($status == 'accepted', function ($query) {
                return $query->AccepteByDeliveryman();
            })
            ->when($status == 'processing', function ($query) {
                return $query->Preparing();
            })
            ->when($status == 'item_on_the_way', function ($query) {
                return $query->ItemOnTheWay();
            })
            ->when($status == 'delivered', function ($query) {
                return $query->Delivered();
            })
            ->when($status == 'canceled', function ($query) {
                return $query->Canceled();
            })
            ->when($status == 'failed', function ($query) {
                return $query->failed();
            })
            ->when($status == 'refunded', function ($query) {
                return $query->Refunded();
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->Scheduled();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(($status != 'all' && $status != 'scheduled' && $status != 'canceled' && $status != 'refund_requested' && $status != 'refunded' && $status != 'delivered' && $status != 'failed'), function ($query) {
                return $query->OrderScheduledIn(30);
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->orderStatus) && $status == 'all', function ($query) use ($request) {
                return $query->whereIn('order_status', $request->orderStatus);
            })
            ->when(isset($request->scheduled) && $status == 'all', function ($query) {
                return $query->scheduled();
            })
            ->when(isset($request->order_type) && $type == 'order', function ($query) use ($request) {
                return $query->where('order_type', $request->order_type);
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->when($type == 'order', function ($query) {
                $query->StoreOrder();
            })
            ->when($type == 'parcel', function ($query) {
                $query->ParcelOrder();
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->module(Config::get('module.current_module_id'))
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
                'stores'=>isset($request->vendor)?Helpers::get_stores_name($request->vendor):null,
            ];

        if ($file_type == 'excel') {
            return Excel::download(new OrderExport($data), 'Orders.xlsx');
        } else if ($file_type == 'csv') {
            return Excel::download(new OrderExport($data), 'Orders.csv');
        }
    }

    public function store_order_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $orders = Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%");
            }
        })->limit(50)->get();

        return response()->json([
            'view' => view('admin-views.vendor.view.partials._order', compact('orders'))->render()
        ]);
    }
    public function store_order_export(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
         $filter = $request?->filter;
        $orders = Order::where('store_id', $request->store_id)->Notpos()
            ->when(isset($key ), function ($q) use ($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($filter) && $filter == 'scheduled_orders', function ($q) {
                    $q->Scheduled();
                })
                ->when(isset($filter) && $filter == 'pending_orders', function ($q) {
                    $q->where(['order_status' => 'pending'])->OrderScheduledIn(30);
                })
                ->when(isset($filter) && $filter == 'delivered_orders', function ($q) {
                    $q->where(['order_status' => 'delivered']);
                })
                ->when(isset($filter) && $filter == 'canceled_orders', function ($q) {
                    $q->where(['order_status' => 'canceled']);
                })
                ->StoreOrder()
                ->Notpos()
            ->get();
        $store= Store::where('id', $request->store_id)->select(['id','zone_id'])->first();
        $data = [
            'data'=>$orders,
            'search'=>request()->search ?? null,
            'zone'=>Helpers::get_zones_name($store->zone_id) ,
            'store'=>  Helpers::get_stores_name($store->id),
            'filter'=>$filter = $filter == 'all_orders' ? null : $filter
        ];

        if($request->type == 'csv'){
            return Excel::download(new StoreOrderlistExport($data), 'OrderList.csv');
        }
        return Excel::download(new StoreOrderlistExport($data), 'OrderList.xlsx');
    }


    public function refund_reason(Request $request)
    {
        $request->validate([
            'reason' => 'required|max:191',
            'reason.0' => 'required',
        ],[
            'reason.0.required'=>translate('default_reason_is_required'),
        ]);

        $reason = new RefundReason();
        $reason->reason = $request->reason[array_search('default', $request->lang)];
        $reason->save();
         Helpers::add_or_update_translations(request: $request, key_data:'reason' , name_field:'reason' , model_name: 'RefundReason' ,data_id: $reason->id,data_value: $reason->reason);
        Toastr::success(translate('Refund Reason Added Successfully'));
        return back();
    }

    public function reasonEdit($id)
    {
        $reason =RefundReason::withoutGlobalScope('translate')->with('translations')->find($id);
        $language = getWebConfig('language');
        return response()->json([
            'view' => view('admin-views.business-settings.settings.partials._refund_reason_edit', compact('reason','language'))->render(),
        ]);
    }


    public function reasonUpdate(Request $request)
    {
        $request->validate([
            'reason' => 'required|max:191',
            'reason.0' => 'required',
        ],[
            'reason.0.required'=>translate('default_reason_is_required'),
        ]);
        $reason = RefundReason::findOrFail($request->reason_id);
        $reason->reason = $request->reason[array_search('default', $request->lang)];
        $reason->save();

        Helpers::add_or_update_translations(request: $request, key_data:'reason' , name_field:'reason' , model_name: 'RefundReason' ,data_id: $reason->id,data_value: $reason->reason);

        Toastr::success(translate('Refund Reason Updated Successfully'));
        return back();
    }
    public function reason_delete(Request $request)
    {
        $refund_reason = RefundReason::findOrFail($request->id);
        $refund_reason?->translations()?->delete();
        $refund_reason->delete();
        Toastr::success(translate('Refund Reason Deleted Successfully'));
        return back();
    }
    public function reason_status(Request $request)
    {
        $refund_reason = RefundReason::findOrFail($request->id);
        $refund_reason->status = $request->status;
        $refund_reason->save();
        Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function order_refund_rejection(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'admin_note' => 'nullable|string|max:65535',
        ]);
        Refund::where('order_id', $request->order_id)->update([
            'order_status' => 'refund_request_canceled',
            'admin_note' => $request->admin_note ?? null,
            'refund_status' => 'rejected',
            'refund_method' => 'canceled',
        ]);

        $order = Order::Notpos()->find($request->order_id);
        $order->order_status = 'refund_request_canceled';
        $order->refund_request_canceled = now();
        $order->save();
        try {


            if(Helpers::getNotificationStatusData('customer','customer_refund_request_rejaction','push_notification_status')  && isset($order?->customer?->cm_firebase_token))
            {
                $data = [
                    'title' => translate('messages.Refund Canceled'),
                    'description' => translate('Your Refund request has been Rejected'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type'=> 'order_status',
                    'order_status' => $order->order_status,
                ];
                Helpers::send_push_notif_to_device($order?->customer?->cm_firebase_token, $data);

                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'user_id'=>$order?->customer?->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }

            if(config('mail.status') && $order?->customer?->email && Helpers::get_mail_status('refund_request_deny_mail_status_user') == '1' &&  Helpers::getNotificationStatusData('customer','customer_refund_request_rejaction','mail_status')){
                Mail::to($order->customer?->getRawOriginal('email'))->send(new RefundRejected($order->id));
            }
        } catch (\Throwable $th) {
            info($th->getMessage());
            Toastr::error(translate('messages.Failed_to_send_mail'));
        }
        Toastr::success(translate('Refund Rejection Successfully'));
        Helpers::send_order_notification($order);
        return back();
    }


    public function refund_mode()
    {
        $refund_mode = BusinessSetting::where('key', 'refund_active_status')->first();
        if (isset($refund_mode) == false) {
            Helpers::businessInsert([
                'key' => 'refund_active_status',
                'value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            Helpers::businessUpdateOrInsert(['key' => 'refund_active_status'], [
                'value' => $refund_mode->value == 1 ? 0 : 1
            ]);
        }

        if (isset($refund_mode) && $refund_mode->value) {
            return response()->json(['message' => 'Order Refund Request Mode is off.']);
        }
        return response()->json(['message' => 'Order Refund Request Mode is on.']);
    }

    public function offline_payment(Request $request){
            $order=  Order::findOrFail($request->id);
            if($request->verify == 'yes'){

                $order->payment_status = 'paid';
                $order->confirmed = now();
                $order->order_status = 'confirmed';
                $order->save();
                Helpers::send_order_notification($order);
                $order->offline_payments()->update([
                    'status'=> 'verified'
                ]);

                if( $order?->store?->is_valid_subscription == 1 && $order?->store?->store_sub?->max_order != "unlimited" && $order?->store?->store_sub?->max_order > 0){
                    $order?->store?->store_sub?->decrement('max_order' , 1);
                }

                $payment_method_name = json_decode($order->offline_payments->payment_info, true)['method_name'];
                if($order->payment_method == 'partial_payment'){
                    $order->payments()->where('payment_status','unpaid')->update([
                        'payment_method'=>  $payment_method_name,
                        'payment_status'=> 'paid',
                    ]);
                }
                $value = Helpers::text_variable_data_format(value:Helpers::order_status_update_message('offline_verified',$order->module->module_type),store_name:$order->store?->name,order_id:$order->id,user_name:"{$order?->customer?->f_name} {$order?->customer?->l_name}",delivery_man_name:"{$order?->delivery_man?->f_name} {$order?->delivery_man?->l_name}");
                $data = [
                    'title' => translate('messages.Your_Offline_payment_is_approved'),
                    'description' => $value == false  ||  $value == null ? ' ' :  $value ,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];

                $fcm= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;


                if($fcm  && Helpers::getNotificationStatusData('customer','customer_offline_payment_approve','push_notification_status') ){
                    Helpers::send_push_notif_to_device($fcm, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $order->user_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }


                $order->payment_method = $payment_method_name;
                    if($order->is_guest == 0){
                        $this->sent_mail_on_offline_payment(status:'approved', name:$order?->customer?->f_name .' '.$order?->customer?->l_name, email:  $order?->customer?->email , otp: $order->otp);
                    }
                }

            elseif($request->verify == 'switched_to_cod'){

                $order->offline_payments()->delete();

                if($order->payment_method == 'partial_payment'){
                    $order->payments()->where('payment_status','unpaid')->update([
                        'payment_method'=> 'cash_on_delivery',
                    ]);
                }

                if( $order?->store?->is_valid_subscription == 1 && $order?->store?->store_sub?->max_order != "unlimited" && $order?->store?->store_sub?->max_order > 0){
                    $order?->store?->store_sub?->decrement('max_order' , 1);
                }

                Helpers::send_order_notification($order);
                $order->payment_method = 'cash_on_delivery';
                $order->save();

                if($order->is_guest == 0){
                    $this->sent_mail_on_offline_payment(status:'COD', name:$order?->customer?->f_name .' '.$order?->customer?->l_name, email:  $order?->customer?->email ,order_id: $order->id);
                }

            }

            else{
                $order->offline_payments()->update([
                    'status'=> 'denied',
                    'note'=> $request->note ?? null
                ]);


                $value = Helpers::text_variable_data_format(value:Helpers::order_status_update_message('offline_denied',$order->module->module_type),store_name:$order->store?->name,order_id:$order->id,user_name:"{$order?->customer?->f_name} {$order?->customer?->l_name}",delivery_man_name:"{$order?->delivery_man?->f_name} {$order?->delivery_man?->l_name}");

                    $data = [
                        'title' => translate('messages.Your_Offline_payment_was_rejected'),
                        'description' => $value ?? $request->note,
                        'order_id' => $order->id,
                        'image' => '',
                        'type' => 'order_status',
                    ];

                    $fcm= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token ;
                    if($fcm && ( $value || $request->note) &&  Helpers::getNotificationStatusData('customer','customer_offline_payment_deny','push_notification_status')){
                        Helpers::send_push_notif_to_device($fcm, $data);
                        DB::table('user_notifications')->insert([
                            'data' => json_encode($data),
                            'user_id' => $order->user_id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    if($order->is_guest == 0){
                        $this->sent_mail_on_offline_payment(status:'denied', name:$order?->customer?->f_name .' '.$order?->customer?->l_name, email:  $order?->customer?->email);
                    }
            }

            Toastr::success(translate('Payment_status_updated'));
            return back();
    }


    private function sent_mail_on_offline_payment($status, $name ,$email ,$otp=null ,$order_id = null){
        try
        {
            if($status == 'approved' && config('mail.status') ){

                if(Helpers::get_mail_status('offline_payment_approve_mail_status_user') == '1' &&  Helpers::getNotificationStatusData('customer','customer_offline_payment_approve','mail_status')){
                    Mail::to($email)->send(new UserOfflinePaymentMail($name, 'approved'));
                }

                if ( Helpers::get_mail_status('order_verification_mail_status_user') == '1'  && $otp  && Helpers::getNotificationStatusData('customer','customer_delivery_verification','mail_status') ) {
                    Mail::to($email)->send(new OrderVerificationMail($otp, $name));
                }
            }

            if($status == 'COD' && $order_id  && config('mail.status')  && Helpers::getNotificationStatusData('customer','customer_order_notification','mail_status'))
            {
                Mail::to($email)->send(new PlaceOrder($order_id));
            }
            if($status == 'denied' && config('mail.status') && Helpers::get_mail_status('offline_payment_deny_mail_status_user') == '1' &&  Helpers::getNotificationStatusData('customer','customer_offline_payment_deny','mail_status')){
                Mail::to($email)->send(new UserOfflinePaymentMail($name, 'denied'));
            }
        }
        catch(\Exception $e)
        {
            Toastr::error(translate('Failed_to_Send_Email'));
            info($e->getMessage());
            return true;
        }
        return true ;
    }

    public function offline_verification_list(Request $request, $status)
    {
        $key = explode(' ', $request['search'] ?? '');
        $orders = Order::with(['customer', 'store'])
        ->where('payment_method', 'offline_payment')
        ->whereHas('offline_payments')
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->when($status == 'pending' , function ($query) {
                return $query->whereHas('offline_payments', function ($query) {
                    return $query->where('status', 'pending');
                });
            })
            ->when($status == 'denied' , function ($query) {
                return $query->whereHas('offline_payments', function ($query) {
                    return $query->where('status', 'denied');
                });
            })
            ->when($status == 'verified' , function ($query) {
                return $query->whereHas('offline_payments', function ($query) {
                    return $query->where('status', 'verified');
                });
            })

            ->when(Config::get('module.current_module_type') == 'parcel', function ($query) {
                return $query->ParcelOrder();
            } , function ($query) {
                return $query->StoreOrder();
            })

            ->module(Config::get('module.current_module_id'))
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        return view('admin-views.order.offline_verification_list', compact('orders', 'status'));
    }
    public function parcelCancellationReason(Request $request)
    {
     $reasons = ParcelCancellationReason::where('status', 1)
            ->select('id', 'reason', 'user_type', 'cancellation_type')
            ->when($request->user_type, function ($query) use ($request) {
                $query->where('user_type', $request->user_type);
            })
            ->when($request->cancellation_type, function ($query) use ($request) {
                $query->where('cancellation_type', $request->cancellation_type);
            })
            ->limit(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id'                => $item->id,
                    'reason'            => $item->reason,
                    'user_type'         => $item->user_type,
                    'cancellation_type' => $item->cancellation_type,
                ];
            });

        return response()->json([
            'view' => view('admin-views.order.partials._parcel_cancellation_reasons', compact('reasons'))->render()
        ]);

    }

    public function CancelParcel(Request $request)
    {
        if($request->reason == null && $request->note == null){
            Toastr::error(translate('messages.please_select_cancellation_reason_or_add_a_comment'));
            return back();
        }

        $order = Order::findOrFail($request->order_id);
        $cancel_parcel_order = OrderLogic::cancelParcelOrder($order, 'admin_for_'.$request->delivery_cancelled_by, $request);

        if (data_get($cancel_parcel_order, 'status_code') != 200) {
            Toastr::error(data_get($cancel_parcel_order, 'message'));
        } else {
            if (data_get($cancel_parcel_order, 'code')== 'wallet_failed'){
                Toastr::success(translate('Parcel_canceled_successfully'));
            }else{
                Toastr::success(data_get($cancel_parcel_order, 'message'));
            }
        }
        return back();
    }

    public function parcelRefund(Request $request)
    {
        $order = Order::with('parcelCancellation')->findOrFail($request->id);
        $order->parcelCancellation()->update([
            'is_refunded' => 1,
            'refund_amount' => $request->refund_amount ?? $order->order_amount,
        ]);

        // $order->order_status = 'returned';
        // $order->save();
        OrderLogic::parcelRefundNotification($order,false);
        Toastr::success(translate('Parcel_refunded_successfully'));
        return back();
    }
    public function parcelReturn(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'order_status' => 'required|in:returned',
        ]);

        $order = Order::with('parcelCancellation')->findOrFail($request->id);
        if( $order && $order->order_status == 'canceled' && $order->order_type == 'parcel'){
            if( in_array($order->parcelCancellation->cancel_by ,['deliveryman', 'admin_for_deliveryman']  )){
                OrderLogic::deliveryManCancelParcelTransaction($order,'admin');
            } else{
                OrderLogic::create_transaction_parcel_cancel($order, $order->payment_status == 'paid' ? 'admin' : 'deliveryman', );
            }

            Toastr::success(translate('Parcel_returned_successfully'));
            return back();
        }
            Toastr::error(translate('Order_not_found'));
        return back();
    }

    public function getSearchedFoods(Request $request)
    {
        $order = Order::StoreOrder()->find($request->order_id);
        if (!$order) {
            return response()->json(['view' => '']);
        }

        $foods = Item::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $order->store_id)
            ->active()
            ->where('name', 'like', '%' . $request->search . '%')
            ->paginate(10);

        return response()->json([
            'view' => view('admin-views.order.partials._searched_food_list', compact('foods'))->render(),
        ]);
    }

    public function getSingleFoodPrice(Request $request)
    {
        $cart = session()->get('order_cart', collect([]));
        $key  = $request->key;

        if (!isset($cart[$key])) {
            return response()->json(['data' => 'not_found', 'message' => translate('messages.item_not_found')], 404);
        }

        $item = $cart[$key];

        $product = isset($item['item_campaign_id']) && $item['item_campaign_id']
            ? ItemCampaign::withoutGlobalScope(StoreScope::class)->find($item['item_campaign_id'])
            : Item::withoutGlobalScope(StoreScope::class)->find($item['item_id']);

        if (!$product) {
            return response()->json(['data' => 'not_found', 'message' => translate('messages.item_not_found')], 404);
        }

        if ($product->maximum_cart_quantity && $request->quantity > $product->maximum_cart_quantity) {
            return response()->json([
                'data'    => 'maximum_cart_quantity',
                'message' => translate('messages.maximum_cart_quantity_for_this_item_is') . ' ' . $product->maximum_cart_quantity,
            ], 203);
        }

        $cart[$key]->quantity = (int) $request->quantity;
        session()->put('order_cart', $cart);

        return response()->json([
            'data'    => 0,
            'message' => translate('messages.quantity_updated_successfully'),
        ]);
    }

}
