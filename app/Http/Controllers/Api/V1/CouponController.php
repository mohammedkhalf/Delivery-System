<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\CouponLogic;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Store;
use App\Traits\ManagesProCustomerSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    use ManagesProCustomerSubscription;

    public function list(Request $request)
    {
        $customer_id=Auth::user()?->id ?? $request->customer_id ?? null;
        $store_id = $request->store_id ?? null;
        $zone_id= isset($request->zone_id) ? $request->zone_id : $request->header('zoneId');
        if (is_array($zone_id)) {
            $zone_ids = $zone_id;
        } else {
            $decoded_zone = json_decode($zone_id, true);
            $zone_ids = is_array($decoded_zone) ? $decoded_zone : array_filter([$zone_id], fn($value) => $value !== null && $value !== '');
        }
        $data = [];
        $proOffer = $this->getProCustomerOffer(userId: $customer_id);
        $proCouponEligible = ($proOffer['status'] ?? false) && (($proOffer['benefit']['type'] ?? null) === 'coupon');
        // try {
            $coupons = Coupon::with('store:id,name')->active()
            ->when(config('module.current_module_data'), function($query){
                $query->module(config('module.current_module_data')['id']);
            })
            // Pro Customer coupons carry no start/expire date (their validity is the customer's
            // subscription window, checked below via $proCouponEligible), so they're exempted from
            // the date-window filter here instead of being silently dropped by whereDate on a null.
            ->where(function($query){
                $query->where('coupon_type', 'pro_customer')
                    ->orWhere(function($query){
                        $query->whereDate('expire_date', '>=', date('Y-m-d'))
                            ->whereDate('start_date', '<=', date('Y-m-d'));
                    });
            })
            ->get();
            foreach($coupons as $key=>$coupon)
            {
                if($coupon->coupon_type == 'store_wise')
                {
                    $coupon_stores = json_decode($coupon->data, true) ?? [];
                    if($store_id && !in_array($store_id, $coupon_stores)){
                        continue;
                    }
                    $temp = Store::active()
                    ->when(config('module.current_module_data'), function($query)use($zone_ids){
                        if(!config('module.current_module_data')['all_zone_service']) {
                            $query->whereIn('zone_id', $zone_ids);
                        }
                    })
                    ->with('storeConfig:id,store_id,verified_seller')
                    ->when($store_id, fn($query) => $query->where('id', $store_id))
                    ->whereIn('id', $coupon_stores)->first();
                    if($temp && (in_array("all", json_decode($coupon->customer_id, true)) || in_array($customer_id,json_decode($coupon->customer_id, true))))
                    {
                        $coupon->data = $temp->name;
                        $coupon['store_id'] = (int)$temp->id;
                        $temp['verified_seller'] = Helpers::get_verified_seller_status($temp, $temp?->storeConfig);
                        unset($temp['storeConfig']);
                        $coupon->setRelation('store', $temp);
                        $data[] = $coupon;
                    }
                }
                else if($coupon->coupon_type == 'zone_wise')
                {
                    if(count(array_intersect($zone_ids, json_decode($coupon->data,true) ?? [])))
                    {
                        $data[] = $coupon;
                    }
                }
                else if($coupon->coupon_type == 'first_order')
                {
                    if($customer_id  && Order::where('user_id', $customer_id)->where('is_guest', '0')->doesntExist())
                    {
                        $data[] = $coupon;
                    }
                }
                else if($coupon->coupon_type == 'pro_customer')
                {
                    if($proCouponEligible)
                    {
                        $data[] = $coupon;
                    }
                }
                else if(isset($coupon->store_id) )
                {
                    if($store_id && $coupon->store_id != $store_id){
                        continue;
                    }
                    $temp = Store::active()->when(config('module.current_module_data'), function($query)use($zone_ids){
                        if(!config('module.current_module_data')['all_zone_service']) {
                            $query->whereIn('zone_id', $zone_ids);
                        }
                    })->where('id', $coupon->store_id)->exists();

                    if($temp){
                        $coupon->store?->loadMissing('storeConfig:id,store_id,verified_seller');
                        if ($coupon->store) {
                            $coupon->store['verified_seller'] = Helpers::get_verified_seller_status($coupon->store, $coupon->store?->storeConfig);
                            unset($coupon->store['storeConfig']);
                        }
                        $data[] = $coupon;
                    }

                }
                else{
                    if((in_array("all", json_decode($coupon->customer_id, true)) || in_array($customer_id,json_decode($coupon->customer_id, true))) ){
                        $coupon->store?->loadMissing('storeConfig:id,store_id,verified_seller');
                        if ($coupon->store) {
                            $coupon->store['verified_seller'] = Helpers::get_verified_seller_status($coupon->store, $coupon->store?->storeConfig);
                            unset($coupon->store['storeConfig']);
                        }
                        $data[] = $coupon;
                    }
                }
            }

            return response()->json($data, 200);
        // } catch (\Exception $e) {
        //     return response()->json(['errors' => $e], 403);
        // }
    }

    public function apply(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'store_id' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $coupon = Coupon::active()->where(['code' => $request['code']])->first();
            if (isset($coupon)) {
                $staus = CouponLogic::is_valide($coupon, $request->user()->id ,$request['store_id'], null, $request['order_amount']);

                switch ($staus) {
                case 200:
                    return response()->json($coupon, 200);
                case 406:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_usage_limit_over')]
                        ]
                    ], 406);
                case 407:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_expire')]
                        ]
                    ], 407);
                case 408:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.You_are_not_eligible_for_this_coupon')]
                        ]
                    ], 403);
                case 409:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_not_valid_for_this_zone')]
                        ]
                    ], 403);
                case 410:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.free_delivery_already_covered_by_pro')]
                        ]
                    ], 403);
                default:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_not_found')]
                        ]
                    ], 404);
                }
            } else {
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('Invalid coupon code.')]
                    ]
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }
}
