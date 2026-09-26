<?php

namespace App\CentralLogics;

use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\ModuleZoneDeliveryOption;

class DeliveryFeeLogic
{
    public const FREE_BY_ADMIN  = 'admin';
    public const FREE_BY_VENDOR = 'vendor';
    public const FREE_BY_COUPON = 'coupon';

    public static function applyDeliveryTypeToAmount($order, float $amount): float
    {
        $rounding = (int) config('round_up_to_digit');

        if ((float) ($order->delivery_charge ?? 0) <= 0) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return $amount;
        }

        $isSaverType = in_array($order->delivery_type, [ModuleZoneDeliveryOption::TYPE_EXPRESS, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY], true);
        if ($isSaverType && (int) ($order->store?->sub_self_delivery ?? 0) === 1) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return $amount;
        }

        if ($order->delivery_type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
            return round($amount + (float) $order->delivery_type_charge, $rounding);
        }

        if ($order->delivery_type === ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY) {
            return round($amount - (float) $order->delivery_type_charge, $rounding);
        }

        return $amount;
    }

    public static function effectiveFee(
        float $baseFee,
        $store,
        float $eligibleOrderAmount,
        ?string $couponCode = null
    ): array {
        if ($baseFee <= 0) {
            return ['fee' => 0.0, 'free_by' => null, 'is_free' => false];
        }

        $settings = BusinessSetting::query()
            ->whereIn('key', ['free_delivery_over', 'admin_free_delivery_status', 'admin_free_delivery_option'])
            ->pluck('value', 'key')
            ->all();

        $adminStatus = (int) ($settings['admin_free_delivery_status'] ?? 0);
        if ($adminStatus === 1) {
            $option    = (string) ($settings['admin_free_delivery_option'] ?? '');
            $threshold = (float)  ($settings['free_delivery_over'] ?? 0);

            if ($option === 'free_delivery_to_all_store') {
                return ['fee' => 0.0, 'free_by' => self::FREE_BY_ADMIN, 'is_free' => true];
            }
            if ($option === 'free_delivery_by_order_amount' && $threshold > 0 && $eligibleOrderAmount >= $threshold) {
                return ['fee' => 0.0, 'free_by' => self::FREE_BY_ADMIN, 'is_free' => true];
            }
        }

        if ($store && (int) ($store->free_delivery ?? 0) === 1) {
            return ['fee' => 0.0, 'free_by' => self::FREE_BY_VENDOR, 'is_free' => true];
        }

        if ($couponCode !== null && $couponCode !== '') {
            $coupon = Coupon::query()
                ->where('code', $couponCode)
                ->where('coupon_type', 'free_delivery')
                ->where('status', 1)
                ->first();
            if ($coupon && (float) ($coupon->min_purchase ?? 0) <= $eligibleOrderAmount) {
                return ['fee' => 0.0, 'free_by' => self::FREE_BY_COUPON, 'is_free' => true];
            }
        }

        return ['fee' => $baseFee, 'free_by' => null, 'is_free' => false];
    }

    public static function adjustedFeeForOrder($order): array
    {
        $base       = (float) ($order->delivery_charge ?? 0);
        $type       = (string) ($order->delivery_type ?? '');
        $typeCharge = (float) ($order->delivery_type_charge ?? 0);
        $freeBy     = $order->free_delivery_by ?? null;

        $isExpress  = $type === 'express'        && $typeCharge > 0 && $base > 0;
        $isSlightly = $type === 'slightly_delay' && $typeCharge > 0 && $base > 0;
        $isFree     = !$isExpress && !$isSlightly && $freeBy && $base <= 0;

        $adjustedFee = $base;
        if ($isExpress) {
            $adjustedFee = $base + $typeCharge;
        } elseif ($isSlightly) {
            $adjustedFee = max(0, $base - $typeCharge);
        }

        $suffix = '';
        if ($isExpress) {
            $suffix = ' (' . translate('messages.express') . ')';
        } elseif ($isSlightly) {
            $suffix = ' (' . translate('messages.slightly_delay') . ')';
        } elseif ($isFree) {
            $by = strtolower((string) $freeBy);
            $freeLabel = translate('messages.Free Delivery');
            if (\in_array($by, ['admin', 'admin_dm'], true)) {
                $suffix = ' (' . $freeLabel . ')';
            } elseif (\in_array($by, ['vendor', 'restaurant'], true)) {
                $suffix = ' (' . $freeLabel . ')';
            } elseif ($by === 'coupon') {
                $suffix = ' (' . $freeLabel . ')';
            } else {
                $suffix = ' (' . $freeLabel . ')';
            }
        }

        return [
            'base'        => $base,
            'adjusted'    => $adjustedFee,
            'type_charge' => $typeCharge,
            'is_express'  => $isExpress,
            'is_slightly' => $isSlightly,
            'is_free'     => $isFree,
            'free_by'     => $freeBy,
            'suffix'      => $suffix,
        ];
    }

    public static function proDeliveryBreakdown($order): array
    {
        $pro = $order->orderProDiscount ?? null;

        $reduction = 0.0;
        if ($pro && ($pro->benefit_type ?? null) === 'delivery_fee') {
            $reduction = (float) ($pro->delivery_fee_reduction_amount ?? 0);
        }

        $charged = (float) ($order->delivery_charge ?? 0);

        return [
            'has_reduction' => $reduction > 0,
            'reduction'     => $reduction,
            'charged_fee'   => $charged,
            'original_fee'  => $charged + $reduction,
        ];
    }

    public static function resolveCouponCodeFromSession(): ?string
    {
        $candidates = ['coupon_code', 'coupon'];
        foreach ($candidates as $key) {
            $value = session()->get($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_array($value) && !empty($value['code'])) {
                return (string) $value['code'];
            }
        }
        return null;
    }
}
