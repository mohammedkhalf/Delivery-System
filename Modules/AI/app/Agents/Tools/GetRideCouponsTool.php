<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\RideShare\Entities\PromotionManagement\CouponSetup;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lists ride-share coupons currently applicable to the customer.
 *
 * Eligibility mirrors the host's CouponSetupRepository::getAppliedCoupon
 * logic, minus the per-trip fare check (we don't know the trip yet — this
 * is a passive listing). The audit (§4) confirmed:
 *
 *   - zone_coupon_type = 'all' OR a row exists in ride_zone_coupon_setups
 *     for one of the user's zones.
 *   - customer_coupon_type = 'all' OR a row exists in
 *     ride_customer_coupon_setups for this user.
 *   - category_coupon_type JSON contains 'all' or 'ride_request' (we only
 *     surface ride-applicable coupons here; parcel coupons stay out).
 *   - is_active = 1 and today is within [start_date, end_date].
 *
 * We deliberately do NOT call CouponSetupRepository::getAppliedCoupon
 * because that method demands a trip fare + level_id + booking context the
 * customer hasn't supplied yet. Chat is showing a menu, not validating a
 * booking.
 */
class GetRideCouponsTool implements Tool
{
    /**
     * @param int[] $zoneIds Overlapping zones the customer falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user    = null,
        private readonly array             $zoneIds = [],
    ) {}

    public function description(): string
    {
        return 'List the ride-share coupons currently applicable to the customer in their area. Use for "any ride coupons?", "ride discounts", "promo for cabs". Returns coupon code, discount, min trip amount, and expiry. Read-only — does not apply the coupon (apply happens at booking).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetRideCouponsTool');

        $today = date('Y-m-d');

        // ride_request is the category_coupon_type value the host uses for
        // ride-side coupons (parcel uses 'parcel'). 'all' covers both.
        $query = CouponSetup::query()
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(function ($q) {
                $q->where('category_coupon_type', 'like', '%"all"%')
                  ->orWhere('category_coupon_type', 'like', '%"ride_request"%');
            });

        // Zone scope: 'all' OR our zones are linked in ride_zone_coupon_setups.
        if (!empty($this->zoneIds)) {
            $zoneIds = $this->zoneIds;
            $query->where(function ($q) use ($zoneIds) {
                $q->where('zone_coupon_type', 'all')
                  ->orWhereHas('zones', fn ($z) => $z->whereIn('zones.id', $zoneIds));
            });
        } else {
            $query->where('zone_coupon_type', 'all');
        }

        // Customer scope: 'all' OR a row exists in ride_customer_coupon_setups
        // for this user. Guests can only see 'all'-customer coupons.
        if ($this->user) {
            $userId = $this->user->getKey();
            $query->where(function ($q) use ($userId) {
                $q->where('customer_coupon_type', 'all')
                  ->orWhereHas('customers', fn ($c) => $c->where('users.id', $userId));
            });
        } else {
            $query->where('customer_coupon_type', 'all');
        }

        $coupons = $query
            ->orderBy('end_date')
            ->limit(8)
            ->get([
                'id', 'coupon_code', 'name', 'coupon', 'amount_type',
                'max_coupon_amount', 'min_trip_amount', 'end_date',
            ]);

        if ($coupons->isEmpty()) {
            return 'No ride coupons are active for your area right now. Check back later or watch the app for new promotions.';
        }

        $lines = $coupons->map(function (CouponSetup $c) {
            $code      = $c->getAttribute('coupon_code') ?: ('#' . $c->getKey());
            $amount    = (float) $c->getAttribute('coupon');
            $type      = (string) $c->getAttribute('amount_type');
            // amount_type values from the host: 'percentage' or 'amount'.
            $discount  = $type === 'percentage'
                ? round($amount) . '% off'
                : 'flat ' . round($amount, 2) . ' off';
            $cap       = (float) $c->getAttribute('max_coupon_amount');
            $capPart   = ($type === 'percentage' && $cap > 0)
                ? ', max ' . round($cap, 2)
                : '';
            $minTrip   = (float) $c->getAttribute('min_trip_amount');
            $minPart   = $minTrip > 0
                ? ', min trip ' . round($minTrip, 2)
                : '';
            $expires   = $c->getAttribute('end_date');
            $expiresAt = $expires
                ? ' (expires ' . (is_string($expires) ? $expires : $expires->format('Y-m-d')) . ')'
                : '';
            $name      = $c->getAttribute('name');
            $namePart  = $name ? ' — ' . $name : '';
            return '• ' . $code . ': ' . $discount . $capPart . $minPart . $expiresAt . $namePart;
        })->implode(PHP_EOL);

        return 'Active ride coupons for your area:' . PHP_EOL
            . $lines . PHP_EOL
            . 'Apply the code at booking time.';
    }
}
