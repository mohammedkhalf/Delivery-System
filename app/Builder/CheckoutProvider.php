<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CashBackHistory;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\OfflinePaymentMethod;
use App\Models\OfflinePayments;
use App\Models\Store;
use App\Models\User;
use App\Traits\PlaceNewOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\CartProvider;
use Modules\Builder\Contracts\CheckoutProvider as CheckoutProviderContract;
use Modules\Builder\Contracts\CouponProvider;
use Modules\Builder\Contracts\LocationProvider;
use Modules\Builder\Contracts\SettingsProvider;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\Storefront\CheckoutQuoteDTO;
use Modules\Builder\ValueObjects\Storefront\CheckoutSnapshotDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * Host adapter wiring the storefront checkout to 6amMart's existing
 * order-placement pipeline (PlaceNewOrder trait + BusinessSetting + Helpers).
 *
 * Three responsibilities — see CheckoutProvider contract for the canonical
 * shape of each return value.
 *
 * Implementation notes:
 *  - quote() approximates the host's full pricing pipeline (delivery fee +
 *    surge + tax + tips + packaging) accurately enough for the storefront's
 *    UX. The CANONICAL re-validation happens at place-order time inside the
 *    host's PlaceNewOrder trait, which is the single source of truth for the
 *    final order_amount written to the DB.
 *  - placeOrder() builds a Request that PlaceNewOrder::new_place_order()
 *    accepts and forwards to it via an anonymous class that uses the trait.
 *    Auth is bridged by setUserResolver() so $request->user works with the
 *    customer guard's User model.
 */
class CheckoutProvider implements CheckoutProviderContract
{
    use PlaceNewOrder;

    public function __construct(
        private StorefrontContext $context,
        private CartProvider $cart,
        private CouponProvider $coupons,
        private LocationProvider $location,
        private SettingsProvider $settings,
    ) {
    }

    /* ─── snapshot ─────────────────────────────────────────── */

    public function snapshot(?StorefrontScope $scope, ?int $customerId): CheckoutSnapshotDTO
    {
        $storeId = $scope?->subTenantId;
        $store   = $storeId ? $this->loadStoreWithOpenFlag($storeId) : null;
        $deliveryTypes = $this->mapDeliveryTypes($store);

        // Schedule delivery is reserved for authenticated customers — guests
        // can't be reliably re-contacted for slot reminders / no-show flows,
        // and the host's scheduling pipeline assumes a real customer record.
        // Force the flag off (and skip the slot query) when no customerId.
        if ($customerId === null) {
            $deliveryTypes['schedule'] = false;
        }

        return CheckoutSnapshotDTO::fromArray([
            'store'          => $this->mapStore($store),
            'deliveryTypes'  => $deliveryTypes,
            'paymentMethods' => $this->mapPaymentMethods($customerId),
            'features'       => $this->mapFeatures($store),
            // Tip presets come from the host capability manifest so each
            // project can set its own (falls back to the legacy default).
            'tipPresets'     => (array) config('builder.capabilities.checkout.tipPresets', [10, 15, 20, 40]),
            'mostTipped'     => $this->mostTippedAmount(),
            // Only build slots when scheduling is actually enabled — saves
            // a query for every page render on stores that don't allow it.
            'scheduleSlots'  => ($deliveryTypes['schedule'] && $storeId)
                ? $this->buildScheduleSlots($storeId, $deliveryTypes['scheduleSlotDuration'])
                : [],
        ]);
    }

    /**
     * Generate the next 7 days worth of pickable schedule slots for the
     * store, honoring the store_schedule rows + the host's slot duration
     * setting. Past slots for "today" are dropped so users can't pick a
     * time that has already passed.
     *
     * Returns:
     *   [
     *     ['date' => '2026-05-10', 'label' => 'Today',
     *      'slots' => [
     *        ['start' => '14:00', 'end' => '14:30',
     *         'iso'   => '2026-05-10 14:00:00',
     *         'label' => '2:00 PM - 2:30 PM'],
     *        …,
     *      ]],
     *     …,
     *   ]
     */
    private function buildScheduleSlots(int $storeId, int $slotDurationMin): array
    {
        $duration = max(5, $slotDurationMin); // safety floor — avoids infinite loops
        $rows = \DB::table('store_schedule')
            ->where('store_id', $storeId)
            ->get(['day', 'opening_time', 'closing_time']);

        if ($rows->isEmpty()) {
            return [];
        }

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) $r->day][] = [
                'open'  => $r->opening_time,
                'close' => $r->closing_time,
            ];
        }

        $now    = \Carbon\Carbon::now();
        $today  = $now->copy()->startOfDay();
        $result = [];

        for ($i = 0; $i < 7; $i++) {
            $day      = $today->copy()->addDays($i);
            $dow      = (int) $day->dayOfWeek; // 0=Sunday … 6=Saturday
            $ranges   = $byDay[$dow] ?? [];
            $slots    = [];

            foreach ($ranges as $range) {
                $start = \Carbon\Carbon::parse(
                    $day->format('Y-m-d') . ' ' . $range['open'],
                );
                $closing = \Carbon\Carbon::parse(
                    $day->format('Y-m-d') . ' ' . $range['close'],
                );

                while ($start->copy()->addMinutes($duration)->lte($closing)) {
                    $end = $start->copy()->addMinutes($duration);
                    if ($i === 0 && $start->lt($now)) {
                        $start = $end;
                        continue;
                    }
                    $slots[] = [
                        'start' => $start->format('H:i'),
                        'end'   => $end->format('H:i'),
                        'iso'   => $start->format('Y-m-d H:i:s'),
                        'label' => $start->format('g:i A') . ' - ' . $end->format('g:i A'),
                    ];
                    $start = $end;
                }
            }

            $result[] = [
                'date'  => $day->format('Y-m-d'),
                'label' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $day->format('D, M j')),
                'slots' => $slots,
            ];
        }

        return $result;
    }

    private function loadStoreWithOpenFlag(int $storeId): ?Store
    {
        // Store::scopeWithOpen() needs a coordinate pair — use the customer's
        // selected location when present, otherwise the store's own coords.
        // The `open` flag itself is computed from store_schedule, not distance.
        $loc  = $this->location->current();
        $lat  = $loc['lat'] ?? null;
        $lng  = $loc['lng'] ?? null;

        $base = Store::query()->where('id', $storeId);
        if ($lat !== null && $lng !== null) {
            $base->withOpen($lng, $lat);
        }
        return $base->first();
    }

    private function mapStore(?Store $store): ?array
    {
        if (!$store) {
            return null;
        }

        // `open` is only populated when withOpen() was applied; default to true
        // (closed stores will be re-checked at place-order time anyway).
        $open = isset($store->open) ? (bool) $store->open : true;

        $freeDeliveryOver = (float) ($store->free_delivery == 1 ? 0 : ($store->minimum_order ?? 0));

        return [
            'id'               => (int) $store->id,
            'name'             => (string) $store->name,
            'open'             => $open,
            'minOrder'         => (float) ($store->minimum_order ?? 0),
            'freeDeliveryOver' => $store->free_delivery ? 0.0 : null,
        ];
    }

    private function mapDeliveryTypes(?Store $store = null): array
    {
        // Global master switches from business_settings.
        $delivery = (int) Helpers::get_business_settings('home_delivery_status') === 1;
        $pickup   = (int) Helpers::get_business_settings('takeaway_status') === 1;
        $schedule = (bool) Helpers::get_business_settings('schedule_order');

        // Per-store toggles override the globals — a store can disable any of
        // these for itself even when the platform allows them. A type only
        // stays on when BOTH the global switch and the store flag are enabled.
        if ($store) {
            $delivery = $delivery && (bool) $store->delivery;
            $pickup   = $pickup   && (bool) $store->take_away;
            $schedule = $schedule && (bool) $store->schedule_order;
        }

        return [
            'delivery'             => $delivery,
            'pickup'               => $pickup,
            'schedule'             => $schedule,
            'scheduleSlotDuration' => $this->slotDurationMinutes(),
        ];
    }

    /**
     * `schedule_order_slot_duration` is documented in minutes by the host's
     * BusinessSettingsController, but live data has been seen at 600000 (416
     * days) — the field has no admin-side validation. Clamp to a sensible
     * range so a bad value can't kill the picker.
     */
    private function slotDurationMinutes(): int
    {
        $raw = (int) (Helpers::get_business_settings('schedule_order_slot_duration') ?? 0);
        if ($raw < 5 || $raw > 240) {
            return 30;
        }
        return $raw;
    }

    private function mapPaymentMethods(?int $customerId): array
    {
        $cod             = Helpers::get_business_settings('cash_on_delivery');
        $digital         = Helpers::get_business_settings('digital_payment');
        $offlineEnabled  = (int) Helpers::get_business_settings('offline_payment_status') === 1;

        // Master switch (config/builder.php) forces partial + wallet off
        // regardless of the host's business settings. This is how the
        // storefront hides the wallet+partial payment options without
        // touching admin config.
        $walletFeaturesEnabled = (bool) \config('builder.wallet_features_enabled', true);
        $partialEnabled  = $walletFeaturesEnabled
            && (int) Helpers::get_business_settings('partial_payment_status') === 1;
        $partialMethod   = Helpers::get_business_settings('partial_payment_method');
        $walletEnabled   = $walletFeaturesEnabled
            && (int) Helpers::get_business_settings('wallet_status') === 1;

        $walletBalance = 0.0;
        if ($walletEnabled && $customerId) {
            $walletBalance = (float) (User::query()->where('id', $customerId)->value('wallet_balance') ?? 0);
        }

        // Hydrate offline methods with both `method_fields` (admin-provided
        // bank info shown to the customer for reference) and
        // `method_informations` (the dynamic form schema the customer fills).
        $offlineMethods = $offlineEnabled
            ? OfflinePaymentMethod::query()
                ->where('status', 1)
                ->get(['id', 'method_name', 'method_fields', 'method_informations'])
                ->map(fn ($m) => [
                    'id'           => (int) $m->id,
                    'name'         => (string) $m->method_name,
                    'fields'       => is_array($m->method_fields) ? $m->method_fields
                        : (json_decode((string) $m->method_fields, true) ?: []),
                    'informations' => is_array($m->method_informations) ? $m->method_informations
                        : (json_decode((string) $m->method_informations, true) ?: []),
                ])
                ->all()
            : [];

        $gateways = [];
        if (is_array($digital) && (int) ($digital['status'] ?? 0) === 1) {
            foreach (Helpers::getActivePaymentGateways() as $g) {
                $gateways[] = [
                    'key'      => (string) ($g['gateway'] ?? ''),
                    'title'    => (string) ($g['gateway_title'] ?? $g['gateway']),
                    'imageUrl' => $g['gateway_image_full_url'] ?? null,
                ];
            }
        }

        return [
            'cod' => [
                'enabled'           => is_array($cod) && (int) ($cod['status'] ?? 0) === 1,
                'allowChangeAmount' => true,
            ],
            'offline' => [
                'enabled' => $offlineEnabled,
                'methods' => $offlineMethods,
            ],
            'gateways' => $gateways,
            'partial' => [
                'enabled' => $partialEnabled,
                'method'  => $partialMethod ?: null,
            ],
            'wallet' => [
                'enabled' => $walletEnabled,
                'balance' => $walletBalance,
            ],
        ];
    }

    private function mapFeatures(?Store $store): array
    {
        $additionalChargeEnabled = (int) Helpers::get_business_settings('additional_charge_status') === 1;
        $additionalChargeAmount  = (float) Helpers::get_business_settings('additional_charge');
        $additionalChargeName    = (string) (Helpers::get_business_settings('additional_charge_name') ?: 'Service Charge');

        // Extra packaging has two gates, both of which the host order pipeline
        // also applies (PlaceNewOrder: `extra_packaging_data[module_type]` AND
        // `storeConfig->extra_packaging_status`). Skipping the platform gate
        // here let the storefront offer — and quote — a charge the host would
        // then refuse to bill. 6amMart has no Required/Optional split, so the
        // charge stays a customer opt-in; `required` is reported for shape
        // parity with the shared checkout component.
        $extraPackagingFee = 0.0;
        $extraPackagingEnabled = false;
        if ($store && $this->packagingAllowedForModule($store) && ($cfg = $store->storeConfig ?? null)) {
            $extraPackagingEnabled = (int) ($cfg->extra_packaging_status ?? 0) === 1;
            $extraPackagingFee     = $extraPackagingEnabled ? (float) ($cfg->extra_packaging_amount ?? 0) : 0.0;
        }

        $taxIncluded = false;
        if (\addon_published_status('TaxModule')) {
            $sys = \Modules\TaxModule\Entities\SystemTaxSetup::query()
                ->where('is_active', 1)->where('is_default', 1)->first();
            $taxIncluded = (int) ($sys?->is_included ?? 0) === 1;
        }

        return [
            'tipsEnabled' => (int) Helpers::get_business_settings('dm_tips_status') === 1,
            'additionalCharge' => [
                'enabled' => $additionalChargeEnabled,
                'name'    => $additionalChargeName,
                'amount'  => $additionalChargeAmount,
            ],
            'extraPackaging' => [
                'enabled'  => $extraPackagingEnabled,
                'required' => false,
                'fee'      => $extraPackagingFee,
            ],
            'taxIncluded' => $taxIncluded,
        ];
    }

    private function mostTippedAmount(): ?float
    {
        // Aggregate is a full-table GROUP BY on `orders.dm_tips` — fine
        // for a UX badge but ruinous on every checkout page render once
        // the orders table grows. Cache for 6h; the "most common tip"
        // value moves slowly enough that staleness is invisible.
        try {
            return Cache::remember('builder.checkout.mostTipped', now()->addHours(6), function () {
                $val = DB::table('orders')
                    ->where('dm_tips', '>', 0)
                    ->select('dm_tips', DB::raw('count(*) as n'))
                    ->groupBy('dm_tips')
                    ->orderByDesc('n')
                    ->limit(1)
                    ->value('dm_tips');
                return $val !== null ? (float) $val : null;
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /* ─── quote ────────────────────────────────────────────── */

    public function quote(?StorefrontScope $scope, ?int $customerId, array $state): CheckoutQuoteDTO
    {
        $cart = $this->cart->list();
        $items = $cart['items'] ?? [];

        // The cart's stored line `price` is the DISCOUNTED line total
        // (catalog × qty minus the active sale/flash item discount,
        // baked in at add-to-cart time). For the summary breakdown we
        // need the GROSS itemPrice (catalog × qty) so that subtracting
        // `$itemDiscount` below applies the discount exactly once, the
        // displayed "Item Price - Discount - Coupon" math matches what
        // place_order persists, and the coupon base is computed
        // against the post-item-discount payable (not gross minus
        // twice-applied discount).
        $itemDiscount   = $this->sumItemLevelDiscount($items);
        $discountedLine = (float) ($cart['totals']['subtotal'] ?? 0);
        $itemPrice      = $discountedLine + $itemDiscount;

        // Coupon (optional) — runs against the discounted subtotal so that
        // percent coupons compute against the actually-payable amount.
        $couponCode      = $state['couponCode'] ?? null;
        $couponDiscount  = 0.0;
        $couponTitle     = null;
        $couponError     = null;
        $couponFreeDeliv = false;
        if ($couponCode) {
            $base = max(0.0, $itemPrice - $itemDiscount);
            $r = $this->coupons->validate((string) $couponCode, $customerId, $scope, $base);
            if ($r['ok'] ?? false) {
                // Adopt the canonical code from the DB. The apply-time lookup
                // is case-insensitive, but the host's place-order coupon check
                // (getCouponData) matches `code` exactly — so submitting the
                // shopper's raw casing (e.g. "save10" vs stored "SAVE10")
                // makes the order fail with "coupon expired". Carry the exact
                // stored code through to placeOrder().
                $couponCode      = $r['code'] ?? $couponCode;
                $couponDiscount  = (float) ($r['discount'] ?? 0);
                $couponTitle     = $r['title'] ?? null;
                $couponFreeDeliv = (bool) ($r['freeDelivery'] ?? false);
            } else {
                $couponError = (string) ($r['error'] ?? 'Invalid coupon');
                $couponCode  = null; // ignore the bad code in the totals
            }
        }

        $discountedSubtotal = max(0.0, $itemPrice - $itemDiscount - $couponDiscount);
        $state['couponCode'] = $couponCode;

        // Delivery — pickup short-circuits to zero.
        $deliveryType = $state['deliveryType'] ?? 'delivery';
        [$deliveryFee, $deliveryFeeNote, $distanceKm, $freeDelivery] = $deliveryType === 'pickup'
            ? [0.0, null, null, ['active' => false, 'reason' => 'pickup']]
            : $this->computeDelivery($scope, $state, $discountedSubtotal, $couponFreeDeliv);

        $tax = $this->computeTax($scope, $customerId, $state, $discountedSubtotal);

        $additionalCharge = (int) Helpers::get_business_settings('additional_charge_status') === 1
            ? (float) Helpers::get_business_settings('additional_charge')
            : 0.0;
        if ($deliveryType === 'pickup') {
            $additionalCharge = 0.0;
        }

        $tipsEnabled = (int) Helpers::get_business_settings('dm_tips_status') === 1;
        $dmTip = ($tipsEnabled && $deliveryType !== 'pickup') ? (float) ($state['tip'] ?? 0) : 0.0;

        $packagingFee = $this->extraPackagingFee($scope, $state);

        $taxIncluded = $this->isTaxIncluded();
        $total = $discountedSubtotal + ($taxIncluded ? 0 : $tax) + $deliveryFee + $additionalCharge + $dmTip + $packagingFee;

        $cashback = $this->computeCashback($customerId, $total);

        return CheckoutQuoteDTO::fromArray([
            'itemPrice'        => $this->roundMoney($itemPrice),
            'itemDiscount'     => $this->roundMoney($itemDiscount),
            'couponCode'       => $couponCode,
            'couponTitle'      => $couponTitle,
            'couponDiscount'   => $this->roundMoney($couponDiscount),
            'couponError'      => $couponError,
            'tax'              => $this->roundMoney($tax),
            'taxEnabled'       => true,
            'taxIncluded'      => $taxIncluded,
            'deliveryFee'      => $this->roundMoney($deliveryFee),
            'deliveryFeeNote'  => $deliveryFeeNote,
            'additionalCharge' => $this->roundMoney($additionalCharge),
            'dmTip'            => $this->roundMoney($dmTip),
            'extraPackaging'   => $this->roundMoney($packagingFee),
            'distance'         => $distanceKm,
            'total'            => $this->roundMoney($total),
            'cashback'         => $cashback,
            'freeDelivery'     => $freeDelivery,
        ]);
    }


    public function shippingMethods(?StorefrontScope $scope, ?int $customerId): array
    {
        return ['enabled' => false, 'groups' => []];
    }

    public function selectShippingMethod(?StorefrontScope $scope, ?int $customerId, string $cartGroupId, int $shippingMethodId): array
    {
        return ['success' => false, 'error' => 'Order-wise shipping selection is not available.'];
    }

    public function resolveDigitalPaymentReturn(array $query): array
    {
        return ['orderId' => null, 'phone' => null];
    }

    private function sumItemLevelDiscount(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $row) {
            $catalog = $row['item']['price'] ?? null;
            $qty     = (int) ($row['quantity'] ?? 0);
            $line    = (float) ($row['price'] ?? 0);
            if ($catalog === null || $qty === 0) {
                continue;
            }
            $gross = (float) $catalog * $qty;
            if ($gross > $line) {
                $sum += $gross - $line;
            }
        }
        return $sum;
    }

    private function computeDelivery(?StorefrontScope $scope, array $state, float $eligibleAmount, bool $couponFreeDelivery): array
    {
        $storeId = $scope?->subTenantId;
        if (!$storeId) {
            return [0.0, __('messages.service_not_available_in_this_area'), null, ['active' => false, 'reason' => null]];
        }

        // Resolve destination coords (from saved address id, ad-hoc state, or current location).
        [$destLat, $destLng] = $this->resolveDestinationCoords($state);
        if ($destLat === null || $destLng === null) {
            return [0.0, 'Select a delivery address to see the delivery fee.', null, ['active' => false, 'reason' => null]];
        }

        $store = Store::query()
            ->select(['id', 'latitude', 'longitude', 'self_delivery_system', 'free_delivery',
                      'per_km_shipping_charge', 'minimum_shipping_charge', 'maximum_shipping_charge',
                      'minimum_order', 'zone_id'])
            ->where('id', $storeId)
            ->first();
        if (!$store) {
            return [0.0, null, null, ['active' => false, 'reason' => null]];
        }

        $distanceKm = $this->haversineKm((float) $store->latitude, (float) $store->longitude, $destLat, $destLng);

        if ((int) ($store->self_delivery_system ?? 0) === 1) {

            $fee = $this->boundedDistanceFee(
                $distanceKm,
                (float) $store->per_km_shipping_charge,
                (float) $store->minimum_shipping_charge,
                (float) $store->maximum_shipping_charge,
            );
            $note = $fee > 0
                ? sprintf('Based on %.2f km delivery distance', $distanceKm)
                : 'Store has no per-km delivery rate set.';
        } else {
            $pivot = \DB::table('module_zone')
                ->where('zone_id', $store->zone_id)
                ->where('module_id', $scope?->moduleId)
                ->first();
            if (!$pivot) {
                return [0.0, 'No delivery pricing rule configured for this zone.', $distanceKm, ['active' => false, 'reason' => null]];
            }

            $type = $pivot->delivery_charge_type ?? 'fixed';
            if ($type === 'distance') {
                $fee = $this->boundedDistanceFee(
                    $distanceKm,
                    (float) ($pivot->per_km_shipping_charge ?? 0),
                    (float) ($pivot->minimum_shipping_charge ?? 0),
                    (float) ($pivot->maximum_shipping_charge ?? 0),
                );
                $note = $fee > 0
                    ? sprintf('%.2f km × zone rate', $distanceKm)
                    : 'Distance pricing configured but per-km rate is zero.';
            } else {
                $fee  = (float) ($pivot->fixed_shipping_charge ?? 0);
                $note = $fee > 0 ? 'Flat zone delivery fee.' : 'Flat fee for this zone is zero.';
            }

            $vehicleExtra = $this->resolveVehicleExtraCharge($distanceKm);
            if ($vehicleExtra > 0) {
                $fee += $vehicleExtra;
            }

            $surge = $this->resolveSurgePrice($store->zone_id, $scope?->moduleId, $state['scheduleAt'] ?? null);
            if ($surge['amount'] > 0 && $fee > 0) {
                $surgeExtra = $surge['type'] === 'percent'
                    ? ($fee * $surge['amount']) / 100
                    : $surge['amount'];
                $fee += $surgeExtra;

                $note = trim(($note ?? '') . sprintf(' + surge (%.2f)', $surgeExtra));
            }
            if ($vehicleExtra > 0) {
                $note = trim(($note ?? '') . sprintf(' + vehicle (%.2f)', $vehicleExtra));
            }
        }

        $effective = \App\CentralLogics\DeliveryFeeLogic::effectiveFee(
            (float) $fee,
            $store,
            max(0.0, $eligibleAmount),
            null,
        );
        if ($effective['is_free']) {
            return [0.0, 'Free delivery (' . $effective['free_by'] . ').', $distanceKm, ['active' => true, 'reason' => $effective['free_by']]];
        }

        if ($couponFreeDelivery && $fee > 0) {
            return [0.0, 'Coupon includes free delivery.', $distanceKm, ['active' => true, 'reason' => 'coupon']];
        }

        return [$fee, $note, $distanceKm, ['active' => false, 'reason' => null]];
    }

    private function resolveVehicleExtraCharge(float $distanceKm): float
    {
        try {
            $r = $this->getVehicleExtraCharge($distanceKm);
            return (float) ($r['extraCharge'] ?? 0);
        } catch (\Throwable $e) {
            \info('Builder quote: vehicle-extra lookup failed — ' . $e->getMessage());
            return 0.0;
        }
    }

    private function resolveSurgePrice($zoneId, $moduleId, ?string $scheduleAt): array
    {
        if (!$zoneId || !$moduleId) {
            return ['amount' => 0.0, 'type' => 'amount'];
        }
        try {
            $when = $scheduleAt ? \Carbon\Carbon::parse($scheduleAt) : \Carbon\Carbon::now();
            $surge = $this->getSurgePriceValue((int) $zoneId, (int) $moduleId, $when);
            return [
                'amount' => (float) ($surge['price'] ?? 0),
                'type'   => (string) ($surge['price_type'] ?? 'amount'),
            ];
        } catch (\Throwable $e) {
            \info('Builder quote: surge lookup failed — ' . $e->getMessage());
            return ['amount' => 0.0, 'type' => 'amount'];
        }
    }

    private function boundedDistanceFee(float $distanceKm, float $perKm, float $min, float $max): float
    {
        $raw = $distanceKm * $perKm;
        if ($raw < $min) return $min;
        if ($max > 0 && $raw > $max) return $max;
        return $raw;
    }

    private function resolveDestinationCoords(array $state): array
    {
        if (!empty($state['lat']) && !empty($state['lng'])) {
            return [(float) $state['lat'], (float) $state['lng']];
        }
        if (!empty($state['addressId'])) {
            $row = \App\Models\CustomerAddress::query()->find((int) $state['addressId']);
            if ($row) {
                return [(float) $row->latitude, (float) $row->longitude];
            }
        }
        $loc = $this->location->current();
        return [$loc['lat'] ?? null, $loc['lng'] ?? null];
    }

    private function resolvePickupOverride(?StorefrontScope $scope): ?array
    {
        if (!$scope?->subTenantId) {
            return null;
        }
        $store = Store::query()
            ->select('latitude', 'longitude', 'address')
            ->find((int) $scope->subTenantId);
        if (!$store || !$store->latitude || !$store->longitude) {
            return null;
        }
        return [
            'lat'     => (float) $store->latitude,
            'lng'     => (float) $store->longitude,
            'address' => (string) ($store->address ?? ''),
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusM = 6_378_137.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return ($earthRadiusM * 2 * asin(sqrt($a))) / 1000.0;
    }

    private function ensureModuleConfig(?StorefrontScope $scope): void
    {
        if (!$scope?->moduleId) {
            return;
        }
        $current = config('module.current_module_data');
        if ($current && (int) ($current['id'] ?? 0) === (int) $scope->moduleId) {
            return;
        }
        $module = \App\Models\Module::find((int) $scope->moduleId);
        if ($module) {
            config(['module.current_module_data' => $module]);
        }
    }

    /**
     * Platform gate: `extra_packaging_data` is a JSON map of module_type => "1",
     * and PlaceNewOrder refuses to bill packaging when the store's module is not
     * enabled in it. Mirror that here so the storefront never offers a charge
     * the host would drop.
     */
    private function packagingAllowedForModule(?Store $store): bool
    {
        $moduleType = $store?->module?->module_type;
        if (!$moduleType) {
            return false;
        }

        $raw = BusinessSetting::where('key', 'extra_packaging_data')->first()?->value;
        $map = json_decode((string) $raw, true);

        return is_array($map) && (string) ($map[$moduleType] ?? '0') === '1';
    }

    /**
     * Extra packaging charge actually payable for this checkout, applying the
     * same gates the vendor panel exposes: the platform per-module setting, the
     * store's `extra_packaging_status`, and the shopper's opt-in.
     *
     * Single source of truth for the quote and the place-order request, so the
     * quoted total and the billed total can never disagree.
     */
    private function extraPackagingFee(?StorefrontScope $scope, array $state): float
    {
        if (!$scope?->subTenantId || empty($state['extraPackaging'])) {
            return 0.0;
        }

        $store = Store::find($scope->subTenantId);
        if (!$store || !$this->packagingAllowedForModule($store)) {
            return 0.0;
        }

        $cfg = $store->storeConfig ?? null;
        if (!$cfg || (int) ($cfg->extra_packaging_status ?? 0) !== 1) {
            return 0.0;
        }

        return (float) ($cfg->extra_packaging_amount ?? 0);
    }

    private function computeTax(?StorefrontScope $scope, ?int $customerId, array $state, float $discountedSubtotal): float
    {
        if (!$scope?->subTenantId || $discountedSubtotal <= 0) {
            return 0.0;
        }

        $this->ensureModuleConfig($scope);

        $user    = $customerId ? User::query()->find($customerId) : null;
        $guestId = $user ? null : $this->context->getGuestId();

        if (!$user && !$guestId) {
            return 0.0;
        }

        try {
            $request = Request::create('', 'POST', [
                'store_id'              => $scope->subTenantId,
                'order_type'            => $state['deliveryType'] === 'pickup' ? 'take_away' : 'delivery',
                'order_amount'          => $discountedSubtotal,
                'coupon_code'           => $state['couponCode'] ?? null,
                // Resolved through extraPackagingFee() so the tax base only
                // includes packaging when it is actually billable.
                'extra_packaging_amount' => $this->extraPackagingFee($scope, $state) > 0 ? 1 : 0,
                'is_prescription'       => false,
                'is_buy_now'            => 0,
                'guest_id'              => $guestId,
            ]);
            $request->headers->set('moduleId', (string) $scope->moduleId);

            if ($user) {
                $request->setUserResolver(fn () => $user);

                $request->merge(['user' => $user]);
            }

            $response = $this->getCalculatedTax($request);
            $payload  = $response->getData(true);
            if (isset($payload['tax_amount'])) {
                $tax = (float) $payload['tax_amount'];
                if ($tax <= 0 && $discountedSubtotal > 0) {
                    $cartRows = Cart::where('user_id', $user?->id ?? (int) $guestId)
                        ->where('module_id', \getModuleId((string) $scope->moduleId))
                        ->selectRaw('is_guest, count(*) as n, sum(price) as total_price')
                        ->groupBy('is_guest')
                        ->get();
                    \info('Builder quote tax: trait returned tax_amount=0 despite payable subtotal', [
                        'store_id'    => $scope->subTenantId,
                        'module_id'   => $scope->moduleId,
                        'user_id'     => $customerId,
                        'guest_id'    => $guestId,
                        'subtotal'    => $discountedSubtotal,
                        'cart_rows'   => $cartRows->toArray(),
                        'tax_status'  => $payload['tax_status'] ?? null,
                        'tax_included' => $payload['tax_included'] ?? null,
                        'taxmodule_published' => \addon_published_status('TaxModule'),
                        'system_tax_active'   => \addon_published_status('TaxModule')
                            ? \Modules\TaxModule\Entities\SystemTaxSetup::query()
                                ->where('is_active', 1)->where('tax_payer', 'vendor')->exists()
                            : null,
                    ]);
                }
                return $tax;
            }

            \info('Builder quote tax: trait returned no tax_amount', [
                'store_id'  => $scope->subTenantId,
                'module_id' => $scope->moduleId,
                'user_id'   => $customerId,
                'guest_id'  => $guestId,
                'payload'   => $payload,
            ]);
        } catch (\Throwable $e) {
            \info('Builder quote tax: trait threw — quoting zero tax. ' . $e->getMessage());
        }

        return 0.0;
    }

    private function isTaxIncluded(): bool
    {
        if (!\addon_published_status('TaxModule')) {
            return false;
        }
        $sys = \Modules\TaxModule\Entities\SystemTaxSetup::query()
            ->where('is_active', 1)->where('is_default', 1)->first();
        return (int) ($sys?->is_included ?? 0) === 1;
    }

    private function computeCashback(?int $customerId, float $orderTotal): ?array
    {
        if (!$customerId || $orderTotal <= 0) {
            return null;
        }
        // Wallet cashback rides on the wallet-features master switch.
        if (! \config('builder.wallet_features_enabled', true)) {
            return null;
        }
        try {
            $r = Helpers::getCalculatedCashBackAmount($orderTotal, $customerId);
            if (!is_array($r) || empty($r)) {
                return null;
            }
            return [
                'eligible'    => (float) ($r['cashback_amount'] ?? 0) > 0,
                'amount'      => (float) ($r['cashback_amount'] ?? 0),
                'minPurchase' => (float) ($r['min_purchase'] ?? 0),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function roundMoney(float $value): float
    {
        return round($value, (int) (config('round_up_to_digit') ?? 2));
    }

    /* ─── placeOrder ───────────────────────────────────────── */

    public function placeOrder(?StorefrontScope $scope, ?int $customerId, array $state): array
    {
        // The host's coupon validation (CouponLogic::is_valide) reads the
        // module from config('module.current_module_data') — set it here since
        // our synthetic request never runs ModuleCheckMiddleware. Without it a
        // coupon order throws and surfaces as "Failed to place order".
        $this->ensureModuleConfig($scope);

        // When the wallet-features master switch is off, reject any incoming
        // request that tries to pay with wallet or split via partial payment.
        // The UI hides these options, so reaching here means a stale page or
        // a crafted request — fail loudly with a 422-style error.
        if (! \config('builder.wallet_features_enabled', true)) {
            $rawMethod = (string) ($state['paymentMethod'] ?? '');
            $partial   = !empty($state['partialPayment']);
            if ($rawMethod === 'wallet' || $partial) {
                return ['success' => false, 'errors' => [['code' => 'wallet_disabled', 'message' => 'Wallet and partial payment are not available. Please pick another payment method.']]];
            }
        }

        $user    = $customerId ? User::query()->find($customerId) : null;
        $guestId = $user ? null : $this->context->getGuestId();

        if (!$user && !$guestId) {
            return ['success' => false, 'errors' => [['code' => 'auth', 'message' => 'Could not identify the current shopper. Please refresh and try again.']]];
        }

        // Guest checkout requires the host's `guest_checkout_status` flag and
        // the contact-person fields the trait validates. Surface a friendly
        // error early so we don't trip the trait's validator.
        if (!$user) {
            if (!Helpers::get_mail_status('guest_checkout_status')) {
                return ['success' => false, 'errors' => [['code' => 'is_guest', 'message' => 'Guest checkout is currently disabled. Please sign in to place an order.']]];
            }
            $missing = [];
            if (empty($state['contactName']))  $missing[] = 'name';
            if (empty($state['contactPhone'])) $missing[] = 'phone';
            if (empty($state['contactEmail'])) $missing[] = 'email';
            if ($missing) {
                return ['success' => false, 'errors' => [['code' => 'contact', 'message' => 'Please provide your ' . implode(', ', $missing) . ' to continue.']]];
            }
        }

        // Quote once more so the order_amount we submit matches what the user
        // saw at the moment of click. Host trait re-validates anyway.
        // `->toArray()` keeps the buildPlaceOrderRequest money path on the
        // existing array shape (it reads $quote['total'] etc.).
        $quote = $this->quote($scope, $customerId, $state)->toArray();

        $request = $user
            ? $this->buildPlaceOrderRequest($scope, $user, $state, $quote)
            : $this->buildGuestPlaceOrderRequest($scope, $guestId, $state, $quote);

        // PlaceNewOrder::new_place_order returns a JsonResponse (200 success,
        // 403 error). Unwrap and map to our DTO.
        $response = $this->new_place_order($request);
        $payload  = $response->getData(true);
        $status   = $response->getStatusCode();

        if ($status === 200) {
            $orderId = (int) ($payload['order_id'] ?? 0);

            // Clear the store-scoped cart rows ourselves. The trait skips
            // its own delete loop because we ran with `is_buy_now=1`.
            // Other-store cart rows in the same module stay intact —
            // the customer might still want them when they switch stores.
            $this->clearStoreScopedCart(
                $user ? $user->id : (int) $guestId,
                (int) $scope?->moduleId,
                (int) $scope?->subTenantId,
                $user ? 0 : 1,
            );

            // Offline-payment chain: the host's `new_place_order` writes the
            // order with `payment_method='offline_payment'` and status `failed`;
            // the customer's chosen method + dynamic-form fields go into the
            // `offline_payments` row, which flips the order back to `pending`
            // for admin verification. Mirrors OrderController::offline_payment.
            if (($state['paymentMethod'] ?? null) === 'offline_payment') {
                $regError = $this->registerOfflinePayment($orderId, $state);
                if ($regError) {
                    // Order was created but the offline registration failed.
                    // Surface the error so the user can retry from the order
                    // detail; the order itself is still valid (just awaits a
                    // proof-of-payment record).
                    return [
                        'success' => false,
                        'errors'  => [['code' => 'offline_payment', 'message' => $regError]],
                    ];
                }
            }

            return [
                'success'         => true,
                'orderId'         => $orderId,
                'paymentRedirect' => $this->paymentRedirectFor(
                    $state['paymentMethod'] ?? null,
                    $orderId,
                    // For guests, the order row carries `user_id = guest_id`
                    // (per PlaceNewOrder::new_place_order line 189). The
                    // host's PaymentController::payment + success/fail/cancel
                    // all run `Order::where(user_id = customer_id)` against
                    // SESSION('customer_id'). Passing null for guests would
                    // make those queries miss and the gateway flow returns
                    // "Data not found". So always send the user_id that's
                    // ACTUALLY on the order row — auth: user.id; guest: guestId.
                    $user?->id ?? (int) $guestId,
                    !$user, // isGuest
                    // Guests don't have a profile to land on after the
                    // gateway returns — point them at the tracking page
                    // (deep-linked with orderId + the phone they used,
                    // normalized to leading-+ E.164 form).
                    $user ? null : $this->normalizePhone($state['contactPhone'] ?? null),
                ),
                'message'         => (string) ($payload['message'] ?? 'Order placed successfully'),
                'total'           => (float) ($payload['total_ammount'] ?? $quote['total']),
                // Normalized phone for downstream UI (the success modal +
                // the gateway callback URL). Always E.164-ish (leading +).
                'contactPhone'    => $this->normalizePhone($state['contactPhone'] ?? null),
            ];
        }

        $errors = $payload['errors'] ?? [['code' => 'unknown', 'message' => 'Failed to place order.']];
        if (!is_array($errors[0] ?? null)) {
            $errors = [['code' => 'unknown', 'message' => is_string($errors) ? $errors : 'Failed to place order.']];
        }
        return ['success' => false, 'errors' => $errors];
    }

    /**
     * E.164-ish normalization: ensure a leading `+`. The frontend uses
     * react-phone-input-2 which emits the phone as digits-only (e.g.
     * "8801521333257") even though the visible input shows a "+" prefix.
     * The host's `track_order` lookup matches the JSON column
     * `delivery_address.contact_person_number` against the value
     * verbatim, so storing without the "+" makes guest tracking fail.
     * Mirrors `OrderController::track_order` lines 49-51.
     */
    private function normalizePhone(?string $phone): ?string
    {
        $trimmed = $phone === null ? null : trim($phone);
        if ($trimmed === null || $trimmed === '') {
            return null;
        }
        return str_starts_with($trimmed, '+') ? $trimmed : '+' . ltrim($trimmed, '+ ');
    }

    /**
     * Gateway keys that map to the host's `digital_payment` bucket. The
     * frontend-selected gateway (paypal, stripe, …) is NOT what gets stored
     * on `Order.payment_method` — the host writes the literal string
     * `digital_payment` and routes the user through `/payment-mobile` where
     * the gateway-specific flow runs server-side.
     */
    private const DIGITAL_GATEWAYS = [
        'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paystack',
        'flutterwave', 'ssl_commerz', 'paytabs', 'paytm', 'paymob_accept',
        'liqpay', 'bkash', 'mercadopago',
    ];

    private function isDigitalGateway(?string $key): bool
    {
        return $key && in_array($key, self::DIGITAL_GATEWAYS, true);
    }

    /**
     * Translate our state DTO into the Request the host trait expects.
     * Headers (`moduleId`, `zoneId`) carry scope; user resolver carries auth.
     */
    private function buildPlaceOrderRequest(?StorefrontScope $scope, User $user, array $state, array $quote): Request
    {
        $deliveryType = $state['deliveryType'] ?? 'delivery';
        $orderType    = $deliveryType === 'pickup' ? 'take_away' : 'delivery';
        $scheduleAt   = $deliveryType === 'schedule' ? ($state['scheduleAt'] ?? null) : null;

        [$lat, $lng] = $this->resolveDestinationCoords($state);
        $address = (string) ($state['address'] ?? '');
        if (!$address && !empty($state['addressId'])) {
            $row = \App\Models\CustomerAddress::query()->find((int) $state['addressId']);
            if ($row) {
                $address = (string) $row->address;
            }
        }
        if ($deliveryType === 'pickup') {
            $pickup = $this->resolvePickupOverride($scope);
            if ($pickup) {
                $lat = $pickup['lat'];
                $lng = $pickup['lng'];
                if (!$address) {
                    $address = $pickup['address'];
                }
            }
        }

        // Build cart JSON in the EXACT shape `PlaceNewOrder::makeOrderDetails`
        // expects (FQCN item_type, decoded variation/add_on_*, etc.) AND
        // pass `is_buy_now=1` so the trait uses this list instead of running
        // its own `Cart::where(user_id, module_id)` query — which would pull
        // in items from OTHER stores in the same module that the customer
        // accumulated in past sessions, triggering the "select items from
        // the same store" rejection at line 1063 of the trait.
        //
        // We track the cart-row IDs so we can clear them ourselves after
        // the order succeeds (since `is_buy_now=1` skips the trait's
        // post-order delete loop on line 571–574).
        $cartRows = $this->loadStoreScopedCart($user->id, (int) $scope?->moduleId, (int) $scope?->subTenantId);
        $cart     = $cartRows->map(fn (Cart $row) => $this->cartRowForOrderDetails($row))->all();

        // Normalize the gateway key the host expects on Order.payment_method.
        // PayPal/Stripe/etc. all collapse to "digital_payment"; the actual
        // gateway selection rides through to /payment-mobile via the redirect
        // URL we build in placeOrder().
        $rawMethod = $state['paymentMethod'] ?? 'cash_on_delivery';
        $hostMethod = $this->isDigitalGateway($rawMethod) ? 'digital_payment' : $rawMethod;

        $body = [
            'cart'                   => json_encode($cart),
            'order_amount'           => $quote['total'],
            'discount_amount'        => $quote['itemDiscount'],
            'coupon_code'            => $quote['couponCode'],
            'coupon_discount_amount' => $quote['couponDiscount'],
            'coupon_discount_title'  => $quote['couponTitle'],
            'distance'               => $quote['distance'] ?? 0,
            'order_type'             => $orderType,
            'payment_method'         => $hostMethod,
            'store_id'               => $scope?->subTenantId,
            'address'                => $address,
            'address_type'           => $state['addressType'] ?? 'Delivery',
            'latitude'               => $lat,
            'longitude'              => $lng,
            'house'                  => $state['house'] ?? null,
            'floor'                  => $state['floor'] ?? null,
            'road'                   => $state['road'] ?? null,
            'contact_person_name'    => $state['contactName']  ?? trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
            'contact_person_number'  => $this->normalizePhone($state['contactPhone'] ?? $user->phone),
            'contact_person_email'   => $state['contactEmail'] ?? $user->email,
            'dm_tips'                => $quote['dmTip'],
            'extra_packaging_amount' => $quote['extraPackaging'],
            'unavailable_item_note'  => $state['unavailableAction'] ?? null,
            'delivery_instruction'   => $state['instructions'] ?? null,
            // Free-text order note (checkout.orderNote capability). 6amMart has
            // no order_note column so the trait ignores it; hosts that support
            // it persist this key.
            'order_note'             => $state['orderNote'] ?? null,
            'bring_change_amount'    => $state['bringChange'] ?? 0,
            'schedule_at'            => $scheduleAt,
            'partial_payment'        => !empty($state['partialPayment']) ? 1 : 0,
            // Always 1 — see comment above. We pass our store-scoped cart
            // and clear the rows ourselves after the order succeeds.
            'is_buy_now'             => 1,
            'guest_id'               => null,
        ];

        $request = Request::create('', 'POST', $body);
        $request->headers->set('moduleId', (string) ($scope?->moduleId ?? ''));
        $request->headers->set('zoneId',   json_encode($scope?->regionId ? [$scope->regionId] : []));
        $request->setUserResolver(fn () => $user);

        // The trait reads `$request->user` as a property — Laravel's Request
        // `__get` resolves that against the input bag (NOT setUserResolver,
        // which only powers `$request->user()` method calls). The host's
        // APIGuestMiddleware does the same merge to satisfy this. Without
        // it, `guest_id` is `required` per the trait's validation rule.
        $request->merge(['user' => $user]);

        return $request;
    }

    /**
     * Guest counterpart of buildPlaceOrderRequest. The trait's validator
     * accepts `guest_id` in lieu of an authenticated user, then routes
     * through the same place-order pipeline with `is_guest = 1` on the
     * resulting Order row. Contact-person fields are required (the trait
     * uses them for the receipt email + driver pings).
     */
    private function buildGuestPlaceOrderRequest(?StorefrontScope $scope, int $guestId, array $state, array $quote): Request
    {
        $deliveryType = $state['deliveryType'] ?? 'delivery';
        // Guard against a tampered request body — the snapshot already hides
        // the Schedule option from guests, but a posted `deliveryType=schedule`
        // would otherwise sail through. Coerce back to standard delivery so
        // `schedule_at` cannot be set on a guest order.
        if ($deliveryType === 'schedule') {
            $deliveryType = 'delivery';
        }
        $orderType    = $deliveryType === 'pickup' ? 'take_away' : 'delivery';
        $scheduleAt   = null;

        [$lat, $lng] = $this->resolveDestinationCoords($state);
        $address = (string) ($state['address'] ?? '');
        if ($deliveryType === 'pickup') {
            $pickup = $this->resolvePickupOverride($scope);
            if ($pickup) {
                $lat = $pickup['lat'];
                $lng = $pickup['lng'];
                if (!$address) {
                    $address = $pickup['address'];
                }
            }
        }

        $cartRows = $this->loadStoreScopedCart($guestId, (int) $scope?->moduleId, (int) $scope?->subTenantId, 1);
        $cart     = $cartRows->map(fn (Cart $row) => $this->cartRowForOrderDetails($row))->all();

        $rawMethod  = $state['paymentMethod'] ?? 'cash_on_delivery';
        $hostMethod = $this->isDigitalGateway($rawMethod) ? 'digital_payment' : $rawMethod;

        $body = [
            'cart'                   => json_encode($cart),
            'order_amount'           => $quote['total'],
            'discount_amount'        => $quote['itemDiscount'],
            'coupon_code'            => $quote['couponCode'],
            'coupon_discount_amount' => $quote['couponDiscount'],
            'coupon_discount_title'  => $quote['couponTitle'],
            'distance'               => $quote['distance'] ?? 0,
            'order_type'             => $orderType,
            'payment_method'         => $hostMethod,
            'store_id'               => $scope?->subTenantId,
            'address'                => $address,
            'address_type'           => $state['addressType'] ?? 'Delivery',
            'latitude'               => $lat,
            'longitude'              => $lng,
            'house'                  => $state['house'] ?? null,
            'floor'                  => $state['floor'] ?? null,
            'road'                   => $state['road'] ?? null,
            'contact_person_name'    => $state['contactName']  ?? null,
            'contact_person_number'  => $this->normalizePhone($state['contactPhone'] ?? null),
            'contact_person_email'   => $state['contactEmail'] ?? null,
            'dm_tips'                => $quote['dmTip'],
            'extra_packaging_amount' => $quote['extraPackaging'],
            'unavailable_item_note'  => $state['unavailableAction'] ?? null,
            'delivery_instruction'   => $state['instructions'] ?? null,
            // Free-text order note (checkout.orderNote capability) — see auth path.
            'order_note'             => $state['orderNote'] ?? null,
            'bring_change_amount'    => $state['bringChange'] ?? 0,
            'schedule_at'            => $scheduleAt,
            'partial_payment'        => !empty($state['partialPayment']) ? 1 : 0,
            'is_buy_now'             => 1,
            // Guest checkout contract: trait writes the order with
            // user_id = guest_id, is_guest = 1.
            'guest_id'               => $guestId,
            // We deliberately do NOT pass `create_new_user`/`password` —
            // the LoginOrGuest modal puts the customer in the explicit
            // "stay-as-guest" branch. They can sign up later.
        ];

        $request = Request::create('', 'POST', $body);
        $request->headers->set('moduleId', (string) ($scope?->moduleId ?? ''));
        $request->headers->set('zoneId',   json_encode($scope?->regionId ? [$scope->regionId] : []));

        // Explicitly NULL the user property — the trait branches on
        // `$request->user ? … : $request['guest_id']` (line 189), and a stale
        // user from another request would hijack the guest path.
        $request->merge(['user' => null]);

        return $request;
    }

    /**
     * For digital_payment orders the storefront sends the user to the host's
     * `/payment-mobile` endpoint, which routes to the gateway-specific flow
     * server-side. Required query params:
     *   - order_id      — host's Order.id
     *   - customer_id   — host's User.id
     *   - payment_method — the SPECIFIC gateway key (paypal/stripe/…) so the
     *                      router picks the right integration
     *   - callback      — URL the user lands on after gateway success/cancel
     */
    /**
     * Load the customer's cart rows scoped to the active storefront's store.
     * Uses `whereHasMorph` against the polymorphic `item` relation — same
     * predicate `Modules\Builder` uses everywhere else for store scoping.
     */
    private function loadStoreScopedCart(int $userId, int $moduleId, int $storeId, int $isGuest = 0)
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->get();
    }

    /**
     * Re-shape a Cart row into the assoc-array structure
     * `PlaceNewOrder::makeOrderDetails` expects when `is_buy_now=1`.
     * Critical fields: `item_type` MUST be the FQCN (the trait does a
     * literal string comparison against `'App\Models\ItemCampaign'`),
     * variation/add_ons MUST be decoded.
     */
    private function cartRowForOrderDetails(Cart $row): array
    {
        return [
            'id'         => (int) $row->id,
            'item_id'    => (int) $row->item_id,
            'item_type'  => $row->item_type,
            'price'      => (float) $row->price,
            'quantity'   => (int) $row->quantity,
            'variation'  => $this->decodeJson($row->variation),
            'variant'    => '', // legacy field — populated only by older POS flows
            'add_on_ids' => $this->decodeJson($row->add_on_ids),
            'add_on_qtys'=> $this->decodeJson($row->add_on_qtys),
        ];
    }

    private function decodeJson($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Required by `PlaceNewOrder` (line 601) but NOT defined on the trait —
     * lives on `OrderController` in the host. Ported verbatim so our class
     * (which `use`s the trait without extending the controller) can satisfy
     * the `$this->createCashBackHistory(...)` call.
     */
    private function createCashBackHistory($order_amount, $user_id, $order_id)
    {
        $cashBack = Helpers::getCalculatedCashBackAmount(amount: $order_amount, customer_id: $user_id);
        if (data_get($cashBack, 'calculated_amount') > 0) {
            $row = new CashBackHistory();
            $row->user_id           = $user_id;
            $row->order_id          = $order_id;
            $row->calculated_amount = data_get($cashBack, 'calculated_amount');
            $row->cashback_amount   = data_get($cashBack, 'cashback_amount');
            $row->cash_back_id      = data_get($cashBack, 'id');
            $row->cashback_type     = data_get($cashBack, 'cashback_type');
            $row->min_purchase      = data_get($cashBack, 'min_purchase');
            $row->max_discount      = data_get($cashBack, 'max_discount');
            $row->save();

            $row?->order()->update(['cash_back_id' => $row->id]);
        }
        return true;
    }

    /**
     * Delete the just-ordered store's cart rows for this customer. Mirrors
     * the trait's post-order cart delete (which we skipped via `is_buy_now=1`)
     * but ONLY for the active store — items in the customer's other-store
     * carts (same module, different store) stay untouched.
     */
    private function clearStoreScopedCart(int $userId, int $moduleId, int $storeId, int $isGuest = 0): void
    {
        Cart::query()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->delete();
    }

    /**
     * Persist the customer's offline-payment selection against an order.
     * Mirrors `App\Http\Controllers\Api\V1\OrderController::offline_payment`
     * — the host's reference flow — without round-tripping through HTTP.
     *
     * Returns a user-facing error string on failure, or null on success.
     */
    private function registerOfflinePayment(int $orderId, array $state): ?string
    {
        $methodId = (int) ($state['offlinePayment']['methodId'] ?? 0);
        if (!$methodId) {
            return 'Offline payment method is required.';
        }

        $method = OfflinePaymentMethod::query()->where(['id' => $methodId, 'status' => 1])->first();
        if (!$method) {
            return 'Selected offline payment method is no longer available.';
        }

        $offlineFields = (array) ($state['offlinePayment']['fields'] ?? []);
        $customerNote  = (string) ($state['offlinePayment']['customerNote'] ?? '');

        // Filter the submitted fields against the method's declared schema —
        // mirrors the host controller's array_column intersection.
        $declared = array_column($method->method_informations ?? [], 'customer_input');
        $info = ['method_id' => $methodId, 'method_name' => $method->method_name];
        foreach ($declared as $key) {
            if (array_key_exists($key, $offlineFields)) {
                $info[$key] = $offlineFields[$key];
            }
        }

        try {
            $row = OfflinePayments::firstOrNew(['order_id' => $orderId]);
            $row->payment_info   = json_encode($info);
            $row->customer_note  = $customerNote;
            $row->method_fields  = json_encode($method->method_fields);
            $row->save();

            // Match the host: flip order from failed → pending now that
            // proof-of-payment is captured.
            \App\Models\Order::query()->where('id', $orderId)->update([
                'order_status'   => 'pending',
                'payment_method' => 'offline_payment',
            ]);
        } catch (\Throwable $e) {
            return 'Could not register offline payment: ' . $e->getMessage();
        }

        return null;
    }

    /**
     * Build the `/payment-mobile` URL the storefront jumps to via
     * Inertia::location for digital orders.
     *
     * `$payerId` is the value the host's PaymentController will compare
     * against `Order.user_id` on every step (initial render + success/
     * fail/cancel callbacks). For auth orders it's the user id; for
     * guest orders it's the guest id (which the trait writes to
     * `Order.user_id` for guests). Passing null for guests would make
     * the host's query at line 51 of PaymentController return null and
     * the gateway flow surfaces "Data not found".
     */
    private function paymentRedirectFor(
        ?string $paymentMethod,
        int $orderId,
        int $payerId,
        bool $isGuest,
        ?string $guestPhone = null,
    ): ?string {
        if (!$this->isDigitalGateway($paymentMethod) || $orderId === 0 || $payerId === 0) {
            return null;
        }

        // Path-based callback so the host's `?flag=…` / `&status=…`
        // append doesn't collide with our own query string. The
        // PaymentCallbackController fans out from there to home/profile
        // with the right flash payload. See that controller for the
        // full rationale on why this matters.
        $callback = url(route(
            'storefront.payment_callback',
            array_filter([
                'orderId' => $orderId,
                'phone'   => $isGuest ? ($guestPhone ?: null) : null,
            ]),
            false,
        ));

        try {
            return route('payment-mobile', [
                'order_id'         => $orderId,
                'customer_id'      => $payerId,
                'payment_method'   => $paymentMethod,
                'payment_platform' => 'web',
                'callback'         => $callback,
            ]) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
