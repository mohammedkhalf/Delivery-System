<?php

namespace App\Traits;

use App\Models\ModuleZone;
use App\Models\ModuleZoneDeliveryOption;
use Illuminate\Http\Request;

trait POSDeliveryTypeTrait
{
    public function loadDeliveryTypes(int $moduleId, int $zoneId, ?float $currentDeliveryCharge = null, ?bool $storeSelfDelivery = null): array
    {
        $empty = [
            'enabled'                 => false,
            'options'                 => [],
            'minimum_delivery_time'   => 0,
            'minimum_delivery_charge' => 0.0,
            'current_delivery_charge' => $currentDeliveryCharge !== null ? (float) $currentDeliveryCharge : 0.0,
            'reason'                  => 'not_configured',
        ];

        if ($moduleId <= 0 || $zoneId <= 0) {
            return $empty;
        }

        if ($storeSelfDelivery === true) {
            return array_merge($empty, ['reason' => 'self_delivery_on']);
        }

        $pivot = ModuleZone::query()
            ->where('module_id', $moduleId)
            ->where('zone_id', $zoneId)
            ->first();

        if (!$pivot || !((bool) $pivot->additional_delivery_option_status)) {
            return $empty;
        }

        $minDeliveryCharge = (float) ($pivot->minimum_delivery_charge ?? 0);
        $orderType = (string) (session('order_type') ?? '');
        $currentFee = $currentDeliveryCharge !== null
            ? (float) $currentDeliveryCharge
            : (float) (session('address.delivery_fee') ?? 0);

        if ($orderType !== '' && $orderType !== 'delivery') {
            return array_merge($empty, [
                'reason' => 'not_delivery_order',
            ]);
        }

        if ($currentFee > 0 && $minDeliveryCharge > 0 && $currentFee < $minDeliveryCharge) {
            return [
                'enabled'                 => false,
                'options'                 => [],
                'minimum_delivery_time'   => (int) ($pivot->minimum_delivery_time ?? 0),
                'minimum_delivery_charge' => $minDeliveryCharge,
                'current_delivery_charge' => $currentFee,
                'reason'                  => 'below_minimum_charge',
            ];
        }

        $rows = ModuleZoneDeliveryOption::for($moduleId, $zoneId)
            ->orderByRaw("FIELD(delivery_type, 'standard','express','slightly_delay')")
            ->get();

        if ($rows->isEmpty()) {
            return array_merge($empty, ['reason' => 'no_options']);
        }

        $options = $rows->map(function (ModuleZoneDeliveryOption $row) {
            return [
                'id'                   => (int) $row->id,
                'delivery_type'        => (string) $row->delivery_type,
                'delivery_type_text'   => translate($row->delivery_type),
                'extra_charge'         => (float) ($row->extra_charge ?? 0),
                'reduce_charge'        => (float) ($row->reduce_charge ?? 0),
                'add_delivery_time'    => (int) ($row->getRawOriginal('add_delivery_time') ?? 0),
                'reduce_delivery_time' => (int) ($row->getRawOriginal('reduce_delivery_time') ?? 0),
            ];
        })->values()->all();

        $freeDeliveryActive = $currentFee <= 0;

        return [
            'enabled'                 => true,
            'options'                 => $options,
            'minimum_delivery_time'   => (int) ($pivot->minimum_delivery_time ?? 0),
            'minimum_delivery_charge' => $minDeliveryCharge,
            'current_delivery_charge' => $currentFee,
            'free_delivery_active'    => $freeDeliveryActive,
            'reason'                  => $freeDeliveryActive ? 'free_delivery_active' : 'ok',
        ];
    }

    public function storeDeliveryType(Request $request): void
    {
        $type = $request->input('delivery_type');
        $charge = (float) $request->input('delivery_type_charge', 0);
        $baseFee = (float) (session('address.delivery_fee') ?? 0);

        $allowed = [
            ModuleZoneDeliveryOption::TYPE_STANDARD,
            ModuleZoneDeliveryOption::TYPE_EXPRESS,
            ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY,
        ];

        if ($type === '' || $type === null || !\in_array($type, $allowed, true)) {
            session()->forget(['delivery_type', 'delivery_type_charge', 'cart_delivery_fee']);
            return;
        }

        if ($baseFee <= 0) {
            session()->forget(['delivery_type', 'delivery_type_charge', 'cart_delivery_fee']);
            return;
        }

        session()->put('delivery_type', $type);
        session()->put('delivery_type_charge', \max(0.0, $charge));

        $finalCharge = $baseFee;
        if ($charge > 0) {
            if ($type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
                $finalCharge = $baseFee + $charge;
            } elseif ($type === ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY) {
                $finalCharge = $baseFee - $charge;
            }
        }
        session()->put('cart_delivery_fee', \max(0.0, $finalCharge));
    }

    public function clearDeliveryTypeSession(): void
    {
        session()->forget(['delivery_type', 'delivery_type_charge', 'cart_delivery_fee']);
    }

    public function applySaverToOrder($order, int $moduleId, int $zoneId, float $baseDeliveryCharge, ?bool $storeSelfDelivery = null): void
    {
        if (!$order) {
            return;
        }

        if ($storeSelfDelivery === true) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        if ($baseDeliveryCharge <= 0 || $moduleId <= 0 || $zoneId <= 0) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $pivot = ModuleZone::query()
            ->where('module_id', $moduleId)
            ->where('zone_id', $zoneId)
            ->first();

        if (!$pivot || !((bool) $pivot->additional_delivery_option_status)) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $minCharge = (float) ($pivot->minimum_delivery_charge ?? 0);
        if ($minCharge > 0 && $baseDeliveryCharge < $minCharge) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $type = (string) (session('delivery_type') ?? '');
        if (!\in_array($type, [ModuleZoneDeliveryOption::TYPE_EXPRESS, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY], true)) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $option = ModuleZoneDeliveryOption::for($moduleId, $zoneId)
            ->where('delivery_type', $type)
            ->first();
        if (!$option) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $rounding = (int) (config('round_up_to_digit') ?? 2);

        if ($type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
            $finalCharge = (float) max(0, $option->extra_charge ?? 0);
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_EXPRESS;
            $order->delivery_type_charge = round($finalCharge, $rounding);
            $order->order_amount = round(((float) ($order->order_amount ?? 0)) + $finalCharge, $rounding);
            return;
        }

        $reduce = (float) max(0, $option->reduce_charge ?? 0);
        $maxReducible = max(0, $baseDeliveryCharge - $minCharge);
        $finalCharge = min($reduce, $maxReducible);

        $order->delivery_type = ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY;
        $order->delivery_type_charge = round($finalCharge, $rounding);
        $order->order_amount = round(((float) ($order->order_amount ?? 0)) - $finalCharge, $rounding);
    }
}
