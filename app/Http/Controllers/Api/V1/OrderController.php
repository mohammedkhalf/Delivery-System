<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\CustomerLogic;
use App\Models\Admin;
use App\Models\Module;
use App\Models\ModuleZoneDeliveryOption;
use App\Models\Order;
use App\Models\Store;
use App\Models\Refund;
use App\Models\Cart;
use App\Models\MonthlyOrderReminder;
use App\Mail\PlaceOrder;
use App\Mail\RefundRequest;
use App\Models\OrderPayment;
use App\Models\RefundReason;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\PersonalizationService;
use App\Models\BusinessSetting;
use App\Models\CashBackHistory;
use App\Models\OfflinePayments;
use App\Models\AutomatedMessage;
use App\Models\OrderCancelReason;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Models\OfflinePaymentMethod;
use Illuminate\Support\Facades\Mail;
use App\Models\ParcelDeliveryInstruction;
use App\Models\Review;
use App\Traits\HandlesCartValidation;
use App\Traits\PlaceNewOrder;
use Illuminate\Support\Facades\Validator;
use Modules\Rental\Entities\Trips;
use Modules\RideShare\Entities\TripManagement\RideRequest;

class OrderController extends Controller
{
    use PlaceNewOrder;
    use HandlesCartValidation;
    public function track_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'contact_number' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request?->user?->id;

        if ($request['contact_number'] && (substr($request['contact_number'], 0, 1) !== '+')) {
            $request['contact_number'] = '+' . $request['contact_number'];
        }

        $order = Order::with(['store', 'store.store_sub', 'delivery_man.rating', 'parcel_category', 'refund', 'payments','parcelCancellation','reviews','orderProDiscount'])->withCount('details')
            ->where('id', $request['order_id'])
            ->when($request->user, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id)->where('is_guest', 0);
            })
            ->when(!$request->user, function ($query) use ($request) {
                return $query->whereJsonContains('delivery_address->contact_person_number', $request['contact_number'])->where('is_guest', 1);
            })
            ->NotHiddenForCustomer()
            ->Notpos()->first();
        if ($order) {
            $order['store'] = $order['store'] ? Helpers::store_data_formatting($order['store']) : $order['store'];
            $order['delivery_address'] = $order['delivery_address'] ? json_decode($order['delivery_address']) : $order['delivery_address'];
            $order['delivery_man'] = $order['delivery_man'] ? Helpers::deliverymen_data_formatting([$order['delivery_man']]) : $order['delivery_man'];
            $order['refund_cancellation_note'] = $order['refund'] ? $order['refund']['admin_note'] : null;
            $order['refund_customer_note'] = $order['refund'] ? $order['refund']['customer_note'] : null;
            $order['min_delivery_time'] =  $order->store ? (int) explode('-', $order->store?->delivery_time)[0] ?? 0 : 0;
            $order['max_delivery_time'] =  $order->store ? (int) explode('-', $order->store?->delivery_time)[1] ?? 0 : 0;
            $order['saver_delivery_time'] = $this->get_saver_delivery_time($order);
            $order['offline_payment'] =  isset($order->offline_payments) ? Helpers::offline_payment_formater($order->offline_payments) : null;
            $order['is_reviewed'] =   $order->details_count >  Review::whereOrderId($request->order_id)->count() ? False :True ;
            foreach (Helpers::pro_discount_data($order) as $pro_key => $pro_value) {
                $order[$pro_key] = $pro_value;
            }


            unset($order['offline_payments']);
            unset($order['details']);
            unset($order['orderProDiscount']);
        } else {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404);
        }
        $order = gettype($order) == 'object' ? $order->toArray() : $order;
        return response()->json($order, 200);
    }

    public function get_order_list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request->user ? $request->user->id : $request['guest_id'];

        $type = $request->query('type', 'previous');
        if (! in_array($type, ['previous', 'running', 'all'], true)) {
            $type = 'previous';
        }

        $moduleHeader = $request->header('moduleId');
        $module_id = $moduleHeader ? getModuleId($moduleHeader) : null;

        $module_type = is_numeric($module_id) ? Module::where('id', $module_id)->value('module_type') : null;

        if ($module_type === 'ride-share' && addon_published_status('RideShare')) {
            return $this->get_ride_order_list($request, $user_id, $type, (int) $module_id);
        }
        if ($module_type === 'rental' && addon_published_status('Rental')) {
            return $this->get_trip_order_list($request, $user_id, $type, (int) $module_id);
        }

        $previous_statuses = ['delivered', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded', 'failed', 'returned'];

        $paginator = Order::with([
                'store',
                'delivery_man.rating',
                'parcel_category',
                'refund:order_id,admin_note,customer_note',
                'details:id,order_id,item_id,quantity,item_campaign_id',
                'details.item:id,name,image,store_id',
                'orderProDiscount',
            ])
            ->withCount('details')
            ->where(['user_id' => $user_id])
            ->NotHiddenForCustomer()
            ->when($type === 'previous', fn ($q) => $q->whereIn('order_status', $previous_statuses))
            ->when($type === 'running', fn ($q) => $q->whereNotIn('order_status', $previous_statuses))
            ->when(is_numeric($module_id), fn ($q) => $q->where('module_id', $module_id))
            ->when(isset($request->user), function ($query) {
                $query->where('is_guest', 0);
            })
            ->Notpos()
            ->latest()
            ->paginate($request['limit'], ['*'], 'page', $request['offset']);

        $orders = array_map(function ($data) {
            $card = OrderLogic::format_order_card($data);
            $data['delivery_address'] = $data['delivery_address'] ? json_decode($data['delivery_address']) : $data['delivery_address'];
            $data['store'] = $data['store'] ? Helpers::store_data_formatting($data['store']) : $data['store'];
            $data['delivery_man'] = $data['delivery_man'] ? Helpers::deliverymen_data_formatting([$data['delivery_man']]) : $data['delivery_man'];
            $refund = $data['refund'] ?? null;
            $data['refund_cancellation_note'] = $refund ? $refund['admin_note'] : null;
            $data['refund_customer_note'] = $refund ? $refund['customer_note'] : null;
            $data['items_preview'] = $card['items_preview'];
            $data['extra_items_count'] = $card['extra_items_count'];
            $data['item_count'] = $card['item_count'];
            $data['can_reorder'] = $data->can_reorder;
            foreach (Helpers::pro_discount_data($data) as $pro_key => $pro_value) {
                $data[$pro_key] = $pro_value;
            }
            unset($data['orderProDiscount']);
            return $data;
        }, $paginator->items());

        $baseCounter = fn () => Order::where('user_id', $user_id)
            ->NotHiddenForCustomer()
            ->when(is_numeric($module_id), fn ($q) => $q->where('module_id', $module_id))
            ->when(isset($request->user), fn ($q) => $q->where('is_guest', 0))
            ->Notpos();

        $all_count = (int) $baseCounter()->count();
        $previous_count = (int) $baseCounter()->whereIn('order_status', $previous_statuses)->count();
        $running_count = (int) $baseCounter()->whereNotIn('order_status', $previous_statuses)->count();

        $data = [
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'type' => $type,
            'module_id' => is_numeric($module_id) ? (int) $module_id : null,
            'running_count' => $running_count,
            'previous_count' => $previous_count,
            'all_count' => $all_count,
            'orders' => $orders,
        ];
        return response()->json($data, 200);
    }

    private function get_ride_order_list(Request $request, $user_id, string $type, int $module_id)
    {
        $previous_statuses = ['completed', 'cancelled'];

        $base = RideRequest::where('customer_id', $user_id)
            ->NotHiddenForCustomer()
            ->where('module_id', $module_id);

        $paginator = (clone $base)
            ->with([
                'driver',
                'vehicle.model',
                'vehicleCategory',
                'time',
                'coordinate',
                'fee',
            ])
            ->when($type === 'previous', fn ($q) => $q->whereIn('current_status', $previous_statuses))
            ->when($type === 'running', fn ($q) => $q->whereNotIn('current_status', $previous_statuses))
            ->latest()
            ->paginate($request['limit'], ['*'], 'page', $request['offset']);

        $orders = array_map(function ($ride) {
            $ride->order_type = 'ride';
            return $ride;
        }, $paginator->items());

        $all_count = (int) (clone $base)->count();
        $previous_count = (int) (clone $base)->whereIn('current_status', $previous_statuses)->count();
        $running_count = (int) (clone $base)->whereNotIn('current_status', $previous_statuses)->count();

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'type' => $type,
            'module_id' => $module_id,
            'running_count' => $running_count,
            'previous_count' => $previous_count,
            'all_count' => $all_count,
            'orders' => $orders,
        ], 200);
    }

    private function get_trip_order_list(Request $request, $user_id, string $type, int $module_id)
    {
        $previous_statuses = ['completed', 'canceled'];
        $is_guest = $request->user ? 0 : 1;

        $base = Trips::where('user_id', $user_id)
            ->NotHiddenForCustomer()
            ->where('is_guest', $is_guest)
            ->where('module_id', $module_id);

        $paginator = (clone $base)
            ->with('provider:id,name,logo,cover_photo,phone')
            ->when($type === 'previous', fn ($q) => $q->whereIn('trip_status', $previous_statuses))
            ->when($type === 'running', fn ($q) => $q->whereNotIn('trip_status', $previous_statuses))
            ->latest()
            ->paginate($request['limit'], ['*'], 'page', $request['offset']);

        $orders = array_map(function ($trip) {
            $trip->order_type = 'trip';
            return $trip;
        }, $paginator->items());

        $all_count = (int) (clone $base)->count();
        $previous_count = (int) (clone $base)->whereIn('trip_status', $previous_statuses)->count();
        $running_count = (int) (clone $base)->whereNotIn('trip_status', $previous_statuses)->count();

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'type' => $type,
            'module_id' => $module_id,
            'running_count' => $running_count,
            'previous_count' => $previous_count,
            'all_count' => $all_count,
            'orders' => $orders,
        ], 200);
    }


    public function get_running_orders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request->user ? $request->user->id : $request['guest_id'];

        $paginator = Order::with(['store', 'delivery_man.rating', 'parcel_category', 'orderProDiscount'])
            ->when(isset($request->user), function ($query) {
                $query->where('is_guest', 0);
            })
            ->withCount('details')
            ->where(['user_id' => $user_id])->whereNotIn('order_status', ['delivered', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded', 'failed','returned'])
            ->NotHiddenForCustomer()
            ->Notpos()->latest()->paginate($request['limit'], ['*'], 'page', $request['offset']);

        $orders = array_map(function ($data) {
            $data['delivery_address'] = $data['delivery_address'] ? json_decode($data['delivery_address']) : $data['delivery_address'];
            $data['store'] = $data['store'] ? Helpers::store_data_formatting($data['store']) : $data['store'];
            $data['delivery_man'] = $data['delivery_man'] ? Helpers::deliverymen_data_formatting([$data['delivery_man']]) : $data['delivery_man'];
            foreach (Helpers::pro_discount_data($data) as $pro_key => $pro_value) {
                $data[$pro_key] = $pro_value;
            }
            unset($data['orderProDiscount']);
            return $data;
        }, $paginator->items());
        $data = [
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'orders' => $orders
        ];
        return response()->json($data, 200);
    }

    public function get_order_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request?->user?->id;

        $order = Order::with('details', 'offline_payments', 'parcel_category','parcelCancellation','orderProDiscount')
            ->when(isset($request->user), function ($query) {
                $query->where('is_guest', 0);
            })
            ->when($request->user, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id);
            })->NotHiddenForCustomer()->findOrFail($request->order_id);
            // Get saver delivery time for order
        $saver_delivery_time = $this->get_saver_delivery_time($order);
        $pro_data = Helpers::pro_discount_data($order);

        $details = isset($order->details) ? $order->details : null;
        if ($details != null && $details->count() > 0) {
            $details = Helpers::order_details_data_formatting($details);
            $details[0]['is_guest']               = (int)$order->is_guest;
            $details[0]['saver_delivery_time']    = $saver_delivery_time;
            $details[0]['delivery_type']          = $order->delivery_type;
            $details[0]['delivery_type_charge']   = (float) ($order->delivery_type_charge ?? 0);
            $details[0]['delivery_charge']        = (float) ($order->delivery_charge ?? 0);
            $details[0]['original_delivery_charge'] = (float) ($order->original_delivery_charge ?? 0);
            $details[0]['free_delivery_by']       = $order->free_delivery_by;
            foreach ($pro_data as $pro_key => $pro_value) {
                $details[0][$pro_key] = $pro_value;
            }
            return response()->json($details, 200);
        } else if ($order->order_type == 'parcel' || $order->prescription_order == 1) {
            $order->delivery_address = json_decode($order->delivery_address, true);
            if ($order->prescription_order && $order->order_attachment) {
                $order->order_attachment = is_array($order->order_attachment)? $order->order_attachment : json_decode($order->order_attachment, true);
            }
            $order = gettype($order) == 'object' ? $order->toArray() : $order;
            $order['saver_delivery_time'] = $saver_delivery_time;
            $order = array_merge($order, $pro_data);
            unset($order['order_pro_discount']);
            unset($order['orderProDiscount']);
            return response()->json(($order), 200);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('messages.not_found')]
            ]
        ], 404);
    }

    public function delete_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $order_ids = is_array($request['order_id']) ? $request['order_id'] : [$request['order_id']];

        $previous_statuses = ['delivered', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded', 'failed', 'returned'];

        $matching = Order::whereIn('id', $order_ids)
            ->where('user_id', $user_id)
            ->where('is_guest', $is_guest)
            ->whereIn('order_status', $previous_statuses)
            ->Notpos()
            ->pluck('id')
            ->all();

        if (empty($matching)) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404);
        }

        DB::table('orders')->whereIn('id', $matching)->update(['is_hidden' => 1]);

        return response()->json(['message' => translate('messages.order_removed_successfully')], 200);
    }

    public function cancel_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request->user ? $request->user->id : $request['guest_id'];

        if($request->note == null && $request->reason == null) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('You Must Enter Note Or Reason')]
                ]
            ], 403);
        }

        $order = Order::where(['user_id' => $user_id, 'id' => $request['order_id']])
            ->when(isset($request->user), function ($query) {
                $query->where('is_guest', 0);
            })->Notpos()->first();

        if (!$order) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 403);
        } elseif ($order->order_type == 'parcel') {
            $cancel_parcel_order = OrderLogic::cancelParcelOrder($order, 'customer', $request);
            if (data_get($cancel_parcel_order, 'status_code') != 200) {
                return response()->json([
                    'errors' => [
                        ['code' => data_get($cancel_parcel_order, 'code'), 'message' => data_get($cancel_parcel_order, 'message')]
                    ]
                ], data_get($cancel_parcel_order, 'status_code'));
            } else {
                return response()->json(['message' => data_get($cancel_parcel_order, 'message')], 200);
            }
        } else if ($order->order_status == 'pending' || $order->order_status == 'failed' || $order->order_status == 'canceled') {
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



            if($order->is_guest == 0){

                OrderLogic::refund_before_delivered($order);
            }
            $order->order_status = 'canceled';
            $order->canceled = now();
            $order->cancellation_reason = $request->reason;
            $order->cancellation_note = $request->note;
            $order->canceled_by = 'customer';
            $order->save();
            $order?->store ?
            Helpers::increment_order_count($order?->store) : '';

            Helpers::send_order_notification($order);
            return response()->json(['message' => translate('messages.order_canceled_successfully')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('messages.you_can_not_cancel_after_confirm')]
            ]
        ], 403);
    }

    public function refund_request(Request $request)
    {
        if (BusinessSetting::where(['key' => 'refund_active_status'])->first()->value == false) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('You can not request for a refund')]
                ]
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'customer_reason' => 'required|string|max:254',
            'refund_method' => 'nullable|string|max:100',
            'customer_note' => 'nullable|string|max:65535',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $order = Order::where(['user_id' => $request->user->id, 'id' => $request['order_id']])
            ->when(isset($request->user), function ($query) {
                $query->where('is_guest', 0);
            })
            ->Notpos()->first();
        if (!$order) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404);
        } else if ($order->order_status == 'delivered' && $order->payment_status == 'paid') {

            $id_img_names = [];
            if (!empty($request->file('image'))) {
                foreach ($request->image as $img) {
                    $image = Helpers::upload('refund/', 'png', $img);
                    array_push($id_img_names, ['img' => $image, 'storage' => Helpers::getDisk()]);
                }
                $image = json_encode($id_img_names);
            } else {
                $image = json_encode([]);
            }
            $refund_amount = round($order->order_amount - $order->delivery_charge - $order->dm_tips, config('round_up_to_digit'));
            $refund = new Refund();
            $refund->order_id = $order->id;
            $refund->user_id = $order->user_id;
            $refund->order_status = $order->order_status;
            $refund->refund_status = 'pending';
            $refund->refund_method = $request->refund_method ?? 'wallet';
            $refund->customer_reason = $request->customer_reason;
            $refund->customer_note = $request->customer_note;
            $refund->refund_amount = $refund_amount;
            $refund->image = $image;

            $order->order_status = 'refund_requested';
            $order->refund_requested = now();
            DB::beginTransaction();
            $refund->save();
            $order->save();
            DB::commit();
            $admin = Admin::where('role_id', 1)->first();
            $mail_status = Helpers::get_mail_status('refund_request_mail_status_admin');
            try {
                if (config('mail.status') && $admin['email'] && $mail_status == '1' && Helpers::getNotificationStatusData('admin', 'order_refund_request', 'mail_status')) {
                    Mail::to($admin?->getRawOriginal('email'))->send(new RefundRequest($order->id));
                }
            } catch (\Exception $exception) {
                info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            }
            return response()->json(['message' => translate('messages.refund_request_placed_successfully')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('Something went wrong')]
            ]
        ], 403);
    }

    public function update_payment_method(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $config = Helpers::get_business_settings('cash_on_delivery');
        if ($config['status'] == 0) {
            return response()->json([
                'errors' => [
                    ['code' => 'cod', 'message' => translate('messages.Cash on delivery order not available at this time')]
                ]
            ], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $order = Order::where(['user_id' => $user_id, 'id' => $request['order_id']])->Notpos()->first();
        if ($order) {
            if ($order->payment_method != 'partial_payment') {
                Order::where(['user_id' => $user_id, 'id' => $request['order_id']])->update([
                    'payment_method' => 'cash_on_delivery',
                    'order_status' => 'pending',
                    'pending' => now()
                ]);
            } else {
                Order::where(['user_id' => $user_id, 'id' => $request['order_id']])->update([
                    'order_status' => 'pending',
                    'pending' => now(),
                ]);
                $payment = OrderPayment::where('payment_status', 'unpaid')->where('order_id', $request['order_id'])->first();
                if ($payment) {
                    $payment->payment_method = 'cash_on_delivery';
                }
                $payment->save();
            }

            $order = Order::where(['user_id' => $user_id, 'id' => $request['order_id']])->Notpos()->first();

            $order_mail_status = Helpers::get_mail_status('place_order_mail_status_user');
            $order_verification_mail_status = Helpers::get_mail_status('order_verification_mail_status_user');
            $address = json_decode($order->delivery_address, true);

            try {
                Helpers::send_order_notification($order);

                if ($order->is_guest == 0 && config('mail.status') && $order_mail_status == '1' && $order->customer && Helpers::getNotificationStatusData('customer', 'customer_order_notification', 'mail_status')) {
                    Mail::to($order->customer?->getRawOriginal('email'))->send(new PlaceOrder($order->id));
                }
                if ($order->is_guest == 1 && config('mail.status') && $order_mail_status == '1' && isset($address['contact_person_email']) && Helpers::getNotificationStatusData('customer', 'customer_order_notification', 'mail_status')) {
                    Mail::to($address['contact_person_email'])->send(new PlaceOrder($order->id));
                }
            } catch (\Exception $exception) {
                info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            }
            return response()->json(['message' => translate('messages.payment_method_updated_successfully')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('messages.not_found')]
            ]
        ], 404);
    }

    public function refund_reasons()
    {
        $refund_reasons = RefundReason::where('status', 1)->get();
        return response()->json([
            'refund_reasons' => $refund_reasons
        ], 200);
    }

    public function cancellation_reason(Request $request)
    {
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $reasons = OrderCancelReason::where('status', 1)->when($request->type, function ($query) use ($request) {
            $query->where('user_type', $request->type);
        })->paginate($limit, ['*'], 'page', $offset);

        $data = [
            'total_size' => $reasons->total(),
            'limit' => $limit,
            'offset' => $offset,
            'data' => $reasons->items()
        ];
        return response()->json($data, 200);
    }

    public function parcel_instructions(Request $request)
    {
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $instructions = ParcelDeliveryInstruction::where('status', 1)->paginate($limit, ['*'], 'page', $offset);

        $data = [
            'total_size' => $instructions->total(),
            'limit' => $limit,
            'offset' => $offset,
            'data' => $instructions->items()
        ];
        return response()->json($data, 200);
    }

    public function most_tips()
    {
        $data = Order::whereNot('dm_tips', 0)->get()->mode('dm_tips');
        $data = ($data && (count($data) > 0)) ? $data[0] : null;
        return response()->json([
            'most_tips_amount' => $data
        ], 200);
    }

    public function offline_payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'method_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $config = Helpers::get_mail_status('offline_payment_status');
        if ($config == 0) {
            return response()->json([
                'errors' => [
                    ['code' => 'offline_payment_status', 'message' => translate('messages.offline_payment_for_the_order_not_available_at_this_time')]
                ]
            ], 403);
        }
        $order = Order::findOrFail($request->order_id);

        $offline_payment_info = [];
        $method = OfflinePaymentMethod::where(['id' => $request->method_id, 'status' => 1])->first();
        try {
            if (isset($method)) {
                $fields = array_column($method->method_informations, 'customer_input');
                $values = $request->all();

                $offline_payment_info['method_id'] = $request->method_id;
                $offline_payment_info['method_name'] = $method->method_name;
                foreach ($fields as $field) {
                    if (key_exists($field, $values)) {
                        $offline_payment_info[$field] = $values[$field];
                    }
                }
            }

            $OfflinePayments = OfflinePayments::firstOrNew(['order_id' => $order->id]);

            $OfflinePayments->payment_info = json_encode($offline_payment_info);
            $OfflinePayments->customer_note = $request->customer_note;
            $OfflinePayments->method_fields = json_encode($method?->method_fields);
            DB::beginTransaction();
            $OfflinePayments->save();

            $order->order_status = 'pending';
            $order->payment_method = 'offline_payment';
            $order->save();
            DB::commit();

            $data = [
                'title' => translate('Order_Notification'),
                'description' => translate('New order alert, confirm to proceed'),
                'order_id' => $order->id,
                'image' => '',
                'module_id' => $order->module_id,
                'order_type' => $order->order_type,
                'zone_id' => $order->zone_id,
                'type' => 'new_order',
            ];
            Helpers::send_push_notif_to_topic($data, 'admin_message', 'order_request', url('/') . '/admin/order/list/all');

            return response()->json([
                'payment' => 'success'
            ], 200);
        } catch (\Exception $exception) {
            info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            DB::rollBack();
            return response()->json(['payment' => $exception->getMessage()], 403);
        }
    }


    public function update_offline_payment_info(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $order = Order::where('id', $request->order_id)->firstOrfail();

        $info = OfflinePayments::where('order_id', $request->order_id)->firstOrfail();
        $old_data =   json_decode($info->payment_info, true);
        $method_id = data_get($old_data, 'method_id', null);
        $offline_payment_info = [];
        $method = OfflinePaymentMethod::where('id', $method_id)->first();
        if (isset($method)) {
            $fields = array_column($method->method_informations, 'customer_input');
            $values = $request->all();

            $offline_payment_info['method_id'] = $method->id;
            $offline_payment_info['method_name'] = $method->method_name;
            foreach ($fields as $field) {
                if (key_exists($field, $values)) {
                    $offline_payment_info[$field] = $values[$field];
                }
            }
        }

        $info->customer_note = $request->customer_note ?? $info->customer_note;
        $info->payment_info = json_encode($offline_payment_info);
        $info->status = 'pending';
        $info->save();

        if($request->update_payment_info){

            if($order->is_guest){
                 $user_fcm = $order->guest->fcm_token;
            }else{
                 $user_fcm = $order?->customer?->cm_firebase_token;
            }
            if (Helpers::getNotificationStatusData('customer','customer_order_notification','push_notification_status') && $user_fcm) {
                $data = [
                    'title' => translate('Payment_Info'),
                    'description' => translate('Your_offline_payment_info_updated_successfully'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                Helpers::send_push_notif_to_device($user_fcm, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $order->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }




        } else {
            Helpers::send_order_notification($order);
        }


        return response()->json(['payment' => 'Payment_Info_Updated_successfully'], 200);
    }



    public function order_again(Request $request)
    {
        Helpers::setZoneIds($request);

        $longitude = $request->header('longitude') ?? 0;
        $latitude = $request->header('latitude') ?? 0;

        $zone_id = json_decode($request->header('zoneId'), true);
        $query = Store::withOpen($longitude, $latitude)->wherehas('orders', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)->where('is_guest', 0)->latest();
        })
            ->where('module_id', getModuleId($request->header('moduleId')))
            ->withcount('items')
            ->with(['itemsForReorder'])
            ->Active()
            ->whereIn('zone_id', $zone_id);

        // Rank the user's previously-ordered stores by their personalization
        // score first (most-engaged stores surface to the top), then by open
        // status as the tiebreaker.
        $query = PersonalizationService::applyStorePersonalization($query, $request->user()?->id);

        $data = $query->take(20)
            ->orderBy('open', 'desc')
            ->get()
            ->map(function ($data) {
                $data->items = $data->itemsForReorder->take(5);
                unset($data->itemsForReorder);
                return $data;
            });

        return response()->json(Helpers::store_data_formatting($data, true), 200);
    }

    public function listMonthlySubscriptions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit'       => 'nullable|integer|min:1|max:100',
            'offset'      => 'nullable|integer|min:1',
            'module_type' => 'nullable|string|in:grocery,pharmacy',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId     = $request->user()->id;
        $limit      = (int) ($request->query('limit', 10));
        $offset     = (int) ($request->query('offset', 1));
        $moduleType = $request->query('module_type');

        $query = MonthlyOrderReminder::active()
            ->where('user_id', $userId)
            ->when($moduleType, fn($q) => $q->where('module_type', $moduleType))
            ->with([
                'order:id,store_id,module_id',
                'order.store:id,name,logo,module_id',
                'order.details:id,order_id,item_id,item_campaign_id,item_details,quantity,price,variation,discount_on_item',
            ])
            ->orderBy('remind_at', 'asc');

        $totalSize = (clone $query)->count();

        $reminders = $query->skip(($offset - 1) * $limit)->take($limit)->get();

        $items = $reminders->map(function ($reminder) {
            return $this->formatMonthlySubscriptionRow($reminder, previewLimit: 4);
        })->values();

        return response()->json([
            'total_size' => $totalSize,
            'limit'      => $limit,
            'offset'     => $offset,
            'items'      => $items,
        ], 200);
    }

    public function monthlySubscriptionDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $request->user()->id;

        $reminder = MonthlyOrderReminder::active()
            ->where('id', $request->query('id'))
            ->where('user_id', $userId)
            ->with([
                'order:id,store_id,module_id',
                'order.store:id,name,logo,module_id',
                'order.details',
                'order.details.item:id,name,image,price,discount,discount_type,store_id,status,stock,maximum_cart_quantity',
                'order.details.campaign:id,name,image,price,discount,discount_type,store_id,status',
            ])
            ->first();

        if (!$reminder) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => translate('messages.not_found')]],
            ], 404);
        }

        return response()->json(
            $this->formatMonthlySubscriptionRow($reminder, previewLimit: null, includeFullItems: true),
            200
        );
    }

    public function removeMonthlySubscription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $request->user()->id;

        $reminder = MonthlyOrderReminder::where('id', $request->input('id'))
            ->where('user_id', $userId)
            ->first();

        if (!$reminder) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => translate('messages.not_found')]],
            ], 404);
        }

        if ($reminder->status !== 'cancelled') {
            $reminder->update(['status' => 'cancelled']);
        }

        return response()->json([
            'message' => translate('messages.monthly_subscription_removed'),
        ], 200);
    }

    private function formatMonthlySubscriptionRow(MonthlyOrderReminder $reminder, ?int $previewLimit = 4, bool $includeFullItems = false): array
    {
        $order   = $reminder->order;
        $store   = $order?->store;
        $details = $order?->details ?? collect();

        $itemsPayload = [];
        $totalAmount  = 0.0;

        $detailsToFormat = $previewLimit !== null ? $details->take($previewLimit) : $details;

        foreach ($detailsToFormat as $detail) {
            $isCampaign = !empty($detail->item_campaign_id);
            $catalog    = $isCampaign ? $detail->campaign : $detail->item;

            $snapshot = null;
            if (!empty($detail->item_details)) {
                $snapshot = is_string($detail->item_details)
                    ? json_decode($detail->item_details, true)
                    : $detail->item_details;
            }

            $name  = $catalog?->name ?? ($snapshot['name'] ?? null);
            $image = $catalog?->image_full_url ?? null;
            if (!$image && !empty($snapshot['image_full_url'])) {
                $image = $snapshot['image_full_url'];
            }

            $unitPrice   = (float) ($detail->price ?? 0);
            $catalogPrice = $catalog ? (float) ($catalog->price ?? 0) : null;
            $oldPrice    = ($catalogPrice !== null && $catalogPrice > $unitPrice) ? $catalogPrice : null;

            $quantity = (int) ($detail->quantity ?? 0);
            $lineTotal = $unitPrice * $quantity;
            $totalAmount += $lineTotal;

            $row = [
                'id'              => $isCampaign ? (int) $detail->item_campaign_id : (int) $detail->item_id,
                'item_type'       => $isCampaign ? 'campaign' : 'item',
                'name'            => $name,
                'image'           => $image,
                'price'           => $unitPrice,
                'old_price'       => $oldPrice,
                'quantity'        => $quantity,
                'is_available'    => $this->isCatalogAvailable($catalog),
            ];

            if ($includeFullItems) {
                $row['variation']  = json_decode($detail->variation ?? '[]', true) ?: [];
                $row['line_total'] = $lineTotal;
            }

            $itemsPayload[] = $row;
        }

        $payload = [
            'id'           => (int) $reminder->id,
            'order_id'     => (int) $reminder->order_id,
            'module_id'    => (int) $reminder->module_id,
            'module_type'  => (string) $reminder->module_type,
            'remind_at'    => optional($reminder->remind_at)->toDateString(),
            'status'       => (string) $reminder->status,
            'store'        => $store ? [
                'id'   => (int) $store->id,
                'name' => $store->name,
                'logo' => $store->logo_full_url ?? null,
            ] : null,
            'items_count'  => $details->count(),
        ];

        if ($includeFullItems) {
            $payload['items']        = $itemsPayload;
            $payload['total_amount'] = round($totalAmount, 2);
        } else {
            $payload['items_preview'] = $itemsPayload;
        }

        return $payload;
    }

    private function isCatalogAvailable($catalog): bool
    {
        if (!$catalog) {
            return false;
        }
        if ((int) ($catalog->status ?? 0) !== 1) {
            return false;
        }
        return true;
    }

    public function reorderMonthly(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reminder_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $request->user()->id;

        $reminder = MonthlyOrderReminder::where('id', $request->reminder_id)
            ->where('user_id', $userId)
            ->first();

        if (!$reminder) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => translate('messages.not_found')]],
            ], 404);
        }

        $order = Order::with(['details.item'])->find($reminder->order_id);

        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => translate('messages.not_found')]],
            ], 404);
        }

        return $this->respondReorder($order, $userId);
    }

    public function reorderFromOrder(Request $request)
    {
        if (!Helpers::get_business_settings('repeat_order_option')) {
            return response()->json(
                ['errors' => [['code' => 'feature_disabled', 'message' => translate('Repeat order is currently disabled.')]]],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $request->user()->id;

        $order = Order::with(['details.item'])
            ->where('id', $request->order_id)
            ->where('user_id', $userId)
            ->where('is_guest', 0)
            ->first();

        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => translate('messages.not_found')]],
            ], 404);
        }

        return $this->respondReorder($order, $userId);
    }

    private function respondReorder(Order $order, int $userId)
    {
        $moduleId = (int) $order->module_id;
        $storeId  = (int) $order->store_id;

        Cart::where('user_id', $userId)
            ->where('is_guest', 0)
            ->where('module_id', $moduleId)
            ->where('store_id', $storeId)
            ->delete();

        $results = $this->processOrderDetailsToCart(
            details: $order->details,
            userId: $userId,
            isGuest: 0,
            moduleId: $moduleId,
        );

        $cartCount = Cart::where('user_id', $userId)
            ->where('is_guest', 0)
            ->where('module_id', $moduleId)
            ->count();

        if (count($results['added']) === 0) {
            return response()->json([
                'errors'            => [['code' => 'no_item_added', 'message' => translate('No item could be added to cart')]],
                'cart_count'        => $cartCount,
                'added_count'       => 0,
                'skipped_count'     => count($results['skipped']),
                'unavailable_items' => array_map(function ($r) {
                    return [
                        'id'      => $r['item_id'],
                        'name'    => $r['item_name'],
                        'code'    => $r['code'],
                        'message' => $r['message'],
                    ];
                }, $results['unavailable']),
                'skipped_items'     => array_map(function ($r) {
                    return [
                        'id'      => $r['item_id'],
                        'name'    => $r['item_name'],
                        'code'    => $r['code'],
                        'message' => $r['message'],
                    ];
                }, $results['skipped']),
            ], 403);
        }

        return response()->json([
            'cart_count'        => $cartCount,
            'added_count'       => count($results['added']),
            'skipped_count'     => count($results['skipped']),
            'unavailable_items' => array_map(function ($r) {
                return [
                    'id'      => $r['item_id'],
                    'name'    => $r['item_name'],
                    'code'    => $r['code'],
                    'message' => $r['message'],
                ];
            }, $results['unavailable']),
            'skipped_items'     => array_map(function ($r) {
                return [
                    'id'      => $r['item_id'],
                    'name'    => $r['item_name'],
                    'code'    => $r['code'],
                    'message' => $r['message'],
                ];
            }, $results['skipped']),
            'message'           => translate('Items added to cart'),
        ], 200);
    }

    public function get_recent_ordered_items(Request $request)
    {
        Helpers::setZoneIds($request);

        $zone_id = $request->header('zoneId');
        $module_id = getModuleId($request->header('moduleId'));
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);

        $items = ProductLogic::recent_ordered_items($request->user()->id, $zone_id, $limit, $offset, $type, $module_id);
        $items['items'] = Helpers::productListDataFormatting($items['items']);

        return response()->json($items, 200);
    }


    private function createCashBackHistory($order_amount, $user_id, $order_id)
    {
        $cashBack =  Helpers::getCalculatedCashBackAmount(amount: $order_amount, customer_id: $user_id);
        if (data_get($cashBack, 'calculated_amount') > 0) {
            $CashBackHistory = new CashBackHistory();
            $CashBackHistory->user_id = $user_id;
            $CashBackHistory->order_id = $order_id;
            $CashBackHistory->calculated_amount = data_get($cashBack, 'calculated_amount');
            $CashBackHistory->cashback_amount = data_get($cashBack, 'cashback_amount');
            $CashBackHistory->cash_back_id = data_get($cashBack, 'id');
            $CashBackHistory->cashback_type = data_get($cashBack, 'cashback_type');
            $CashBackHistory->min_purchase = data_get($cashBack, 'min_purchase');
            $CashBackHistory->max_discount = data_get($cashBack, 'max_discount');
            $CashBackHistory->save();

            $CashBackHistory?->order()->update([
                'cash_back_id' => $CashBackHistory->id
            ]);
        }
        return true;
    }


    public function automatedMessage(Request $request)
    {
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);
        $messages = AutomatedMessage::orderBy('id', 'desc')->where('status', 1)->select(['id', 'message'])
            ->paginate($limit, ['*'], 'page', $offset);
        $messages->makeHidden(['translations']);
        $data = [
            'total_size' => $messages->total(),
            'limit' => $limit,
            'offset' => $offset,
            'data' => $messages->items()
        ];

        return response()->json($data, 200);
    }


    public function place_order(Request $request)
    {
        $this->normalizeDeliveryTypeInput($request);
        return $this->new_place_order($request);
    }

    private function normalizeDeliveryTypeInput(Request $request): void
    {
        $value = $request->input('delivery_type');
        if ($value === null || $value === '') {
            return;
        }
        if (is_numeric($value)) {
            $option = ModuleZoneDeliveryOption::query()->find((int) $value);
            if ($option) {
                $request->merge(['delivery_type' => $option->delivery_type]);
            }
        }
    }

    private function get_saver_delivery_time($order): ?string
    {
        $type = $order->delivery_type ?? null;
        if (!\in_array($type, ['express', 'slightly_delay'], true)) {
            return null;
        }
        if ((int) ($order->store?->sub_self_delivery ?? 0) === 1) {
            return null;
        }
        if (!$order->store?->delivery_time || $order->order_type !== 'delivery') {
            return null;
        }

        $option = ModuleZoneDeliveryOption::for((int) $order->module_id, (int) $order->zone_id)
            ->where('delivery_type', $type)
            ->first();
        if (!$option) {
            return null;
        }

        $raw = (string) $order->store->delivery_time;
        $parts = preg_split('/[-\s]+/', trim($raw));
        $numeric = array_values(array_filter($parts, 'is_numeric'));
        if (count($numeric) < 2) {
            return $raw;
        }

        $min = (int) $numeric[0];
        $max = (int) $numeric[1];
        $unitToken = strtolower(end($parts) ?: 'min');
        $isHour = str_contains($unitToken, 'hour');
        if ($isHour) {
            return $raw;
        }

        $pivot = $order->store->module_id && $order->zone_id
            ? \App\Models\ModuleZone::query()->where('module_id', $order->module_id)->where('zone_id', $order->zone_id)->first()
            : null;
        $floorMin = (int) ($pivot->minimum_delivery_time ?? 0);

        if ($type === 'express') {
            $reduce = (int) ($option->getRawOriginal('reduce_delivery_time') ?? 0);
            $min = max($floorMin, $min - $reduce);
            $max = max($floorMin, $max - $reduce);
        } else {
            $add = (int) ($option->getRawOriginal('add_delivery_time') ?? 0);
            $min += $add;
            $max += $add;
        }

        return $min . '-' . $max . ' min';
    }
    public function prescription_place_order(Request $request)
    {
        return $this->new_place_order($request, true);
    }

    public function getTaxFromCart(Request $request)
    {
        return $this->getCalculatedTax($request);
    }

    public function getSurgePriceAmount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required',
            'module_id' => 'required',
            'date_time' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        return $this->getSurgePrice($request->zone_id, $request->module_id, $request->date_time);
    }


    public function parcelReturn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'order_status' => 'required|in:returned',
            'return_otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $order = Order::where(['id' => $request->order_id])->with('parcelCancellation')->first();


        $validationCheck =  OrderLogic::makeValidationForParcelReturn($request,$order);
        if (data_get($validationCheck, 'status_code') === 403) {

            return response()->json([
                'errors' => [
                    ['code' => data_get($validationCheck, 'code'), 'message' => data_get($validationCheck, 'message')]
                ]
            ], data_get($validationCheck, 'status_code'));
        }

        if( in_array($order->parcelCancellation->cancel_by ,['deliveryman', 'admin_for_deliveryman']  )){
            OrderLogic::deliveryManCancelParcelTransaction($order,'customer');
        } else{
            OrderLogic::create_transaction_parcel_cancel($order, $order->payment_status == 'paid' ? 'admin' : 'deliveryman' );
        }

        return response()->json(['message' => translate('messages.Parcel_returned_successfully')], 200);
    }

    public function walletPayment(Request $request){
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $order = Order::where(['id' => $request->order_id])->first();
        if($order->payment_status == 'paid'){
            return response()->json(['message' => translate('messages.Order_payment_successfully')], 200);
        }

        $walletTransaction = CustomerLogic::create_wallet_transaction($order->user_id, $order->order_amount, 'order_place', $order->id);
        if($walletTransaction){
            $order->order_status = 'confirmed';
            $order->payment_status = 'paid';
            $order->payment_method = 'wallet';
            $order->update();

            return response()->json(['message' => translate('messages.Order_payment_successfully')], 200);
        }

        return response()->json(['message' => translate('messages.some_thing_went_wrong')], 400);
    }

    public function get_all_running_orders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];

        $orders = Order::when(isset($request->user), function ($query) {
                $query->where('is_guest', 0);
            })
            ->where('user_id', $user_id)
            ->whereNotIn('order_status', [
                'delivered', 'canceled', 'refund_requested',
                'refund_request_canceled', 'refunded', 'failed'
            ])
            ->NotHiddenForCustomer()
            ->Notpos()
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => (int)$order->id,
                    'order_type' => 'order',
                    'status' => $order->order_status,
                    'is_repeat' => 0,
                    'created_at' => $order->created_at,
                ];
            });

        if(addon_published_status('RideShare')){
            $rides = RideRequest::where('customer_id', $user_id)
                ->where(fn($query) => $query->whereNotIn('current_status', ['completed', 'cancelled'])
                ->orWhere(fn($query) => $query->whereNotNull('driver_id')
                    ->whereHas('fee', function ($query) {
                        $query->where(function ($q) {
                            $q->where('cancelled_by', '!=', 'driver')
                            ->orWhereNull('cancelled_by');
                        });
                    })
                    ->whereIn('current_status', ['completed', 'cancelled'])
                    ->where('payment_status', 'unpaid')
                ))
                ->latest()
                ->get()
                ->map(function ($ride) {
                    return [
                        'id' => (int)$ride->ref_id,
                        'order_type' => 'ride',
                        'status' => $ride->current_status,
                        'is_repeat' => 0,
                        'created_at' => $ride->created_at,
                    ];
                });
        }else{
            $rides = [];
        }

        if(addon_published_status('Rental')){
            $trips = Trips::where('user_id', $user_id)
                ->where(fn($query) => $query->whereNotIn('trip_status', ['completed', 'cancelled']))
                ->latest()
                ->get()
                ->map(function ($ride) {
                    return [
                        'id' => (int)$ride->id,
                        'order_type' => 'trip',
                        'status' => $ride->trip_status,
                        'is_repeat' => 0,
                        'created_at' => $ride->created_at,
                    ];
                });
        }else{
            $trips = [];
        }

        // $merged = $orders->merge($rides)->merge($bookings);
        // $sorted = collect(array_merge($orders->toArray(), $rides->toArray(), $bookings->toArray()))
        //     ->sortByDesc('created_at')
        //     ->take(50)
        //     ->values();

        $merged = $orders->concat($rides)->concat($trips);

        $sorted = $merged->sortByDesc('created_at')->values()->take(50);

        return response()->json([
            'data' => $sorted
        ], 200);
    }

    public function get_last_orders(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([], 200);
        }

        $module_id = $request->header('moduleId') ?? null;
        $store_id = $request->store_id ?? null;

        $orders = Order::where('user_id', $user->id)
            ->where('is_guest', 0)
            ->where('order_status', 'delivered')
            ->when(isset($module_id), function ($q) use ($module_id) {
                $q->where('module_id', $module_id);
            })
            ->when(isset($store_id), function ($q) use ($store_id) {
                $q->where('store_id', $store_id);
            })
            ->with([
                'store:id,name,logo,module_id,zone_id,slug',
                'details:id,order_id,item_id,quantity,item_campaign_id',
                'details.item:id,name,image,store_id',
            ])
            ->orderByDesc('delivered')
            ->latest()
            ->take(10)
            ->get();

        $formatted = OrderLogic::format_order_cards($orders);

        return response()->json($formatted, 200);
    }
}
