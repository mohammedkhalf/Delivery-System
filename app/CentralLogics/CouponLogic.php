<?php

namespace App\CentralLogics;

use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;
use Modules\Rental\Entities\Trips;

class CouponLogic
{
    public static function get_discount($coupon, $order_amount)
    {
        $discount_ammount = 0;
        if($coupon->discount_type=='percent' && $coupon->discount > 0)
        {
            $discount_ammount = $order_amount* ($coupon->discount/100);
        }
        else
        {
            $discount_ammount = $coupon->discount;
        }
        if($coupon->max_discount > 0)
        {
            $discount_ammount = $discount_ammount > $coupon->max_discount?$coupon->max_discount:$discount_ammount;
        }
        return $discount_ammount;
    }

    public static function is_valide($coupon, $user_id, $store_id, $module_id = null, $order_amount = null)
    {

        $start_date = Carbon::parse($coupon->start_date);
        $expire_date = Carbon::parse($coupon->expire_date);
        $customer_ids=json_decode($coupon->customer_id, true);

        $today = Carbon::now();

        $module_id = isset($module_id)?$module_id:config('module.current_module_data')['id'];

        if(isset($module_id) && $coupon->module_id != $module_id)
        {
            return 404;
        }

        if($start_date->format('Y-m-d') > $today->format('Y-m-d') || $expire_date->format('Y-m-d') < $today->format('Y-m-d'))
        {
            return 407;  //coupon expire
        }

        if($coupon->coupon_type == 'store_wise' && !in_array($store_id, json_decode($coupon->data, true)))
        {
            return 404;
        }

        if($coupon->created_by == 'vendor' && $store_id != $coupon->store_id  ){
            return 404;
        }

        if($coupon->coupon_type == 'pro_customer'){
            $proOffer = (new class { use \App\Traits\ManagesProCustomerSubscription; })->getProCustomerOffer(userId: $user_id);
            if(!($proOffer['status'] ?? false) || ($proOffer['benefit']['type'] ?? null) !== 'coupon'){
                return 408; //unauthorized user
            }
        }

        if((!in_array("all", $customer_ids) && !in_array($user_id,$customer_ids)) ){
            return 408; //unauthorized user
            }

        else if($coupon->coupon_type == 'zone_wise')
        {
            if(json_decode($coupon->data, true)){
                $data = Store::whereIn('zone_id',json_decode($coupon->data, true))->where('id', $store_id)->first();
                if(!$data)
                {
                    return 409; //coupon not valid for the selected store's zone
                }
            }
            else
            {
                return 409; //coupon not valid for the selected store's zone
            }
        }
        else if($coupon->coupon_type == 'first_order')
        {
            $total = Order::where(['user_id' => $user_id])->count();
            if ($total < $coupon['limit']) {
                return 200;
            }else{
                return 406;//Limite orer
            }
        }

        if($coupon->coupon_type == 'free_delivery' && self::proAlreadyGivesFreeDelivery($user_id, $order_amount, $store_id)){
            return 410;
        }

        if ($coupon['limit'] == null || $user_id == null) {
            return 200;
        } else {
            if ($coupon->module?->module_type === 'rental') {
                return Trips::couponLimitReached(userId: $user_id, coupon: $coupon) ? 406 : 200;
            }
            $total = Order::where(['user_id' => $user_id, 'coupon_code' => $coupon['code']])->count();
            if ($total < $coupon['limit']) {
                return 200;
            }else{
                return 406;//Limite orer
            }
        }
        return 404; //not found
    }

    private static function proAlreadyGivesFreeDelivery($user_id, $order_amount = null, $store_id = null)
    {
        $module_type = $store_id
            ? Store::with('module:id,module_type')->find($store_id)?->module?->module_type
            : null;

        if(!$module_type){
            return false;
        }

        $proOffer = (new class { use \App\Traits\ManagesProCustomerSubscription; })->getProCustomerOffer(userId: $user_id, moduleType: $module_type);

        if(!($proOffer['status'] ?? false) || ($proOffer['benefit']['type'] ?? null) !== 'delivery_fee'){
            return false;
        }

        $benefit = $proOffer['benefit'];

        if(($benefit['offer_type'] ?? null) !== 'full_free'){
            return false;
        }

        return (int)($benefit['min_order_status'] ?? 0) !== 1
            || (($benefit['min_order_amount'] ?? null) !== null && $order_amount !== null && $order_amount >= $benefit['min_order_amount']);
    }

    public static function is_valid_for_guest($coupon, $store_id, $module_id = null)
    {

        $start_date = Carbon::parse($coupon->start_date);
        $expire_date = Carbon::parse($coupon->expire_date);

        $today = Carbon::now();

        $module_id = isset($module_id)?$module_id:config('module.current_module_data')['id'];

        if(isset($module_id) && $coupon->module_id != $module_id)
        {
            return 404;
        }

        if($start_date->format('Y-m-d') > $today->format('Y-m-d') || $expire_date->format('Y-m-d') < $today->format('Y-m-d'))
        {
            return 407;  //coupon expire
        }

        if($coupon->coupon_type == 'store_wise' && !in_array($store_id, json_decode($coupon->data, true)))
        {
            return 404;
        }

        if($coupon->created_by == 'vendor' && $store_id != $coupon->store_id  ){
            return 404;
        }

        if($coupon->coupon_type == 'pro_customer'){
            return 408; //unauthorized user
        }

        else if($coupon->coupon_type == 'zone_wise')
        {
            if(json_decode($coupon->data, true)){
                $data = Store::whereIn('zone_id',json_decode($coupon->data, true))->where('id', $store_id)->first();
                if(!$data)
                {
                    return 409; //coupon not valid for the selected store's zone
                }
            }
            else
            {
                return 409; //coupon not valid for the selected store's zone
            }
        }
        if ($coupon['limit'] == null) {
            return 200;
        }
        return 404; //not found
    }
}
