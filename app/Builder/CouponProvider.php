<?php

namespace App\Builder;

use App\CentralLogics\CouponLogic;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Builder\Contracts\CouponProvider as CouponProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

class CouponProvider implements CouponProviderContract
{
    private const EXPLICIT_TYPES = ['store_wise', 'zone_wise', 'first_order'];

    public function forCustomer(?int $customerId, ?int $storeId): array
    {
        if (!$storeId) {
            return [];
        }

        $store = Store::query()
            ->select(['id', 'name', 'module_id', 'zone_id'])
            ->where('id', $storeId)
            ->first();

        if (!$store) {
            return [];
        }

        $today = Carbon::today()->toDateString();

        return Coupon::query()
            ->with('store:id,name')
            ->active()
            ->when($store->module_id, fn (Builder $q) => $q->module($store->module_id))
            ->whereDate('start_date', '<=', $today)
            ->whereDate('expire_date', '>=', $today)
            ->where(fn (Builder $q) => $this->applyEligibility($q, $store, $customerId))
            ->get()
            ->map(fn (Coupon $coupon) => $this->toDto($coupon, $store))
            ->values()
            ->all();
    }

    private function applyEligibility(Builder $q, Store $store, ?int $customerId): void
    {
        // Storefront shows ONLY the store's own coupons — coupons the vendor
        // created for this store (created_by = 'vendor', store_id = this store).
        // Every admin-created coupon (zone-wide, first-order, platform-wide, and
        // admin store-targeted store_wise) is intentionally hidden here.
        $q->where('created_by', 'vendor')
          ->where('store_id', $store->id)
          ->where(fn (Builder $c) => $this->jsonContainsCustomer($c, $customerId));
    }

    private function jsonContainsScalar(Builder $q, string $column, int $value): void
    {
        $q->whereJsonContains($column, $value)
          ->orWhereJsonContains($column, (string) $value);
    }

    private function jsonContainsCustomer(Builder $q, ?int $customerId): void
    {
        $q->whereJsonContains('customer_id', 'all');

        if ($customerId !== null) {
            $q->orWhereJsonContains('customer_id', $customerId)
              ->orWhereJsonContains('customer_id', (string) $customerId);
        }
    }

    private function toDto(Coupon $coupon, Store $store): array
    {
        $discount     = (float) $coupon->discount;
        $discountType = $coupon->discount_type;
        $minPurchase  = (float) $coupon->min_purchase;
        $maxDiscount  = (float) $coupon->max_discount;

        // A free-delivery coupon carries no monetary discount, so the generic
        // "$0 Off" reads as broken — show the waived-shipping label instead.
        $isFreeDelivery = $coupon->coupon_type === 'free_delivery';
        $benefit = $isFreeDelivery
            ? 'Free Delivery'
            : ($discountType === 'percent'
                ? $this->trimNumber($discount) . '% Off'
                : '$' . $this->trimNumber($discount) . ' Off');

        // `note` carries the min-purchase requirement only. The max_discount cap
        // gets its own line in the card, so both can show at once.
        $note = $minPurchase > 0
            ? 'Min purchase $' . $this->trimNumber($minPurchase)
            : null;

        $storeName = $coupon->store?->name ?? ($coupon->coupon_type === 'store_wise' ? $store->name : null);

        return [
            'id'            => (int) $coupon->id,
            'code'          => (string) $coupon->code,
            'title'         => (string) $coupon->title,
            'coupon_type'   => $coupon->coupon_type,
            'type_label'    => $this->typeLabel($coupon->coupon_type),
            'discount'      => $discount,
            'discount_type' => $discountType,
            'min_purchase'  => $minPurchase,
            'max_discount'  => $maxDiscount,
            'valid_from'    => $this->formatDate($coupon->start_date),
            'valid_to'      => $this->formatDate($coupon->expire_date),
            'store_name'    => $storeName,
            'benefit'       => $benefit,
            'note'          => $note,
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'store_wise'    => 'Store Special',
            'zone_wise'     => 'Zone Discount',
            'first_order'   => 'First Order',
            'free_delivery' => 'Free Delivery',
            default         => 'Special Offer',
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function validate(string $code, ?int $customerId, ?StorefrontScope $scope, float $cartTotal): array
    {
        $storeId  = $scope?->subTenantId;
        $moduleId = $scope?->moduleId;
        $code     = trim($code);

        if (!$storeId || !$moduleId) {
            return ['ok' => false, 'error' => 'Coupon not available for this storefront.'];
        }

        $coupon = Coupon::query()
            ->active()
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->first();

        if (!$coupon) {
            return ['ok' => false, 'error' => 'Coupon code not found.'];
        }

        if ((float) $coupon->min_purchase > 0 && $cartTotal < (float) $coupon->min_purchase) {
            return [
                'ok'    => false,
                'error' => 'Minimum purchase to use this coupon is ' . number_format((float) $coupon->min_purchase, 2),
            ];
        }
        if ((int) $coupon->module_id !== (int) $moduleId) {
            return ['ok' => false, 'error' => 'This coupon does not apply to the current module.'];
        }
        // Storefront only honors the store's own coupons — matches the coupon list,
        // which hides admin-created coupons (zone-wide, first-order, platform-wide,
        // admin store-targeted). Reject anything not created by the vendor for this
        // store so a hidden admin code can't be redeemed by typing it in.
        if ($coupon->created_by !== 'vendor' || (int) $coupon->store_id !== (int) $storeId) {
            return ['ok' => false, 'error' => 'This coupon is not available at this store.'];
        }
        if ($coupon->coupon_type === 'store_wise') {
            $stores = json_decode((string) $coupon->data, true) ?: [];
            $stores = array_map('intval', $stores);
            if (!in_array((int) $storeId, $stores, true)) {
                return ['ok' => false, 'error' => 'This coupon is not valid at this store.'];
            }
        }

        $status = $customerId
            ? CouponLogic::is_valide($coupon, $customerId, $storeId, $moduleId)
            : CouponLogic::is_valid_for_guest($coupon, $storeId, $moduleId);

        if ($status !== 200) {
            return ['ok' => false, 'error' => $this->statusToMessage($status)];
        }

        $isFreeDelivery = $coupon->coupon_type === 'free_delivery';
        $discount       = $isFreeDelivery ? 0.0 : (float) CouponLogic::get_discount($coupon, $cartTotal);

        return [
            'ok'           => true,
            'code'         => (string) $coupon->code,
            'title'        => (string) $coupon->title,
            'discount'     => $discount,
            'freeDelivery' => $isFreeDelivery,
            'couponType'   => (string) $coupon->coupon_type,
        ];
    }

    private function statusToMessage(int $status): string
    {
        return match ($status) {
            406     => 'Coupon usage limit exceeded.',
            407     => 'Coupon has expired or is not yet valid.',
            408     => 'This coupon is not available for your account.',
            409     => "This coupon is not valid for the selected store's zone.",
            410     => 'You already get free delivery with your Pro Customer benefit, so this coupon is not needed.',
            default => 'Coupon code not found.',
        };
    }
}
