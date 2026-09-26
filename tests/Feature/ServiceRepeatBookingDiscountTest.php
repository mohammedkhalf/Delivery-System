<?php

namespace Tests\Feature;

use App\CentralLogics\CouponLogic;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Service\Entities\ServiceBooking;
use Modules\Service\Http\Controllers\Api\V1\Customer\BookingController;
use Modules\Service\Lib\RepeatBookingCreator;
use ReflectionMethod;
use Tests\TestCase;

class ServiceRepeatBookingDiscountTest extends TestCase
{
    use DatabaseTransactions;

    private const PRICE = 100.0;
    private const N = 10;

    private function pricing(array $detailsResponse, $coupon, $userId, int $isGuest, Store $provider, bool $applyFirstOrder, int $repeatCount): array
    {
        $controller = app(BookingController::class);
        $method = new ReflectionMethod($controller, 'calculateBookingPricing');
        $method->setAccessible(true);

        return $method->invoke($controller, $detailsResponse, $coupon, $userId, $isGuest, $provider, $applyFirstOrder, $repeatCount);
    }

    private function details(float $price): array
    {
        return [
            'price' => $price,
            'discount' => 0,
            'is_custom' => true,
            'details_data' => [[
                'service_id' => null,
                'service_campaign_id' => null,
                'service_name' => 'Test',
                'is_custom' => 1,
                'category_id' => null,
                'sub_category_id' => null,
                'quantity' => 1,
                'price' => $price,
                'original_price' => $price,
                'calculated_price' => $price,
                'discount_amount' => 0,
                'discount_type' => null,
                'discount_by' => 'vendor',
                'discount_percentage' => 0,
            ]],
        ];
    }

    private function provider(): Store
    {
        return Store::query()->firstOrFail();
    }

    private function coupon(string $type, float $value, float $maxDiscount = 0): Coupon
    {
        $coupon = new Coupon();
        $coupon->discount_type = $type;
        $coupon->discount = $value;
        $coupon->max_discount = $maxDiscount;
        $coupon->created_by = 'admin';

        return $coupon;
    }

    public function test_regular_booking_coupon_is_unchanged(): void
    {
        $coupon = $this->coupon('percent', 10, 30);

        $result = $this->pricing($this->details(self::PRICE), $coupon, null, 1, $this->provider(), true, 1);

        $this->assertEqualsWithDelta(10.0, $result['coupon_discount_amount'], 0.001);
        $this->assertEqualsWithDelta(90.0, $result['price'], 0.001);
    }

    public function test_repeat_percent_coupon_caps_on_series_then_splits(): void
    {
        // 10% of (100 x 10 = 1000) = 100, capped at 30, split across 10 -> 3 each.
        $coupon = $this->coupon('percent', 10, 30);

        $result = $this->pricing($this->details(self::PRICE), $coupon, null, 1, $this->provider(), true, self::N);

        $this->assertEqualsWithDelta(3.0, $result['coupon_discount_amount'], 0.001);
        $this->assertEqualsWithDelta(97.0, $result['price'], 0.001);
        $this->assertEqualsWithDelta(30.0, $result['coupon_discount_amount'] * self::N, 0.001, 'aggregate equals the capped series total');
    }

    public function test_repeat_fixed_amount_coupon_splits_across_series(): void
    {
        // Flat 30 for the whole series, split across 10 -> 3 each.
        $coupon = $this->coupon('amount', 30);

        $single = $this->pricing($this->details(self::PRICE), $coupon, null, 1, $this->provider(), true, 1);
        $repeat = $this->pricing($this->details(self::PRICE), $coupon, null, 1, $this->provider(), true, self::N);

        $this->assertEqualsWithDelta(30.0, $single['coupon_discount_amount'], 0.001);
        $this->assertEqualsWithDelta(3.0, $repeat['coupon_discount_amount'], 0.001);
    }

    public function test_repeat_uncapped_percent_coupon_matches_per_occurrence(): void
    {
        // No cap: 10% per occurrence == 10% of the series / N. Per-occurrence figure is identical.
        $coupon = $this->coupon('percent', 10, 0);

        $result = $this->pricing($this->details(self::PRICE), $coupon, null, 1, $this->provider(), true, self::N);

        $this->assertEqualsWithDelta(10.0, $result['coupon_discount_amount'], 0.001);
    }

    public function test_referral_flat_discount_splits_across_series(): void
    {
        $this->setReferralSettings(type: 'amount', amount: 30);
        $user = $this->firstOrderUser();

        $single = $this->pricing($this->details(self::PRICE), null, $user->id, 0, $this->provider(), true, 1);
        $repeat = $this->pricing($this->details(self::PRICE), null, $user->id, 0, $this->provider(), true, self::N);

        $this->assertEqualsWithDelta(30.0, $single['ref_bonus_amount'], 0.001, 'single occurrence keeps the flat referral');
        $this->assertEqualsWithDelta(3.0, $repeat['ref_bonus_amount'], 0.001, 'flat referral is split across the series');
        $this->assertEqualsWithDelta(0.0, $repeat['pro_discount'], 0.001, 'no pro subscription -> no pro discount');
    }

    public function test_referral_is_skipped_for_guest(): void
    {
        $this->setReferralSettings(type: 'amount', amount: 30);

        $result = $this->pricing($this->details(self::PRICE), null, null, 1, $this->provider(), true, self::N);

        $this->assertEqualsWithDelta(0.0, $result['ref_bonus_amount'], 0.001);
    }

    public function test_pro_discount_caps_on_series_then_splits(): void
    {
        // Exercises the same helper the pricing pipeline caps with: 10% of 1000 = 100, capped 30.
        $controller = app(BookingController::class);
        $proOffer = [
            'status' => true,
            'benefit' => [
                'type' => 'discount',
                'min_order_status' => 0,
                'min_order_amount' => null,
                'percentage' => 10,
                'max_amount' => 30,
            ],
        ];

        $seriesDiscount = $controller->computeProDiscountAmount($proOffer, self::PRICE * self::N, self::PRICE * self::N);

        $this->assertEqualsWithDelta(30.0, $seriesDiscount, 0.001, 'pro discount is capped on the series total');
        $this->assertEqualsWithDelta(3.0, round($seriesDiscount / self::N, (int) config('round_up_to_digit')), 0.001, 'and split per occurrence');
    }

    public function test_repeat_count_one_matches_the_original_formula(): void
    {
        $provider = $this->provider();

        foreach ([['percent', 15, 0], ['percent', 25, 20], ['amount', 40, 0]] as [$type, $value, $max]) {
            $coupon = $this->coupon($type, $value, $max);

            $expected = CouponLogic::get_discount($coupon, self::PRICE);
            $expected = Helpers::minDiscountCheck(productPrice: self::PRICE, discount: $expected)['discount_applied'];

            $result = $this->pricing($this->details(self::PRICE), $coupon, null, 1, $provider, true, 1);

            $this->assertEqualsWithDelta($expected, $result['coupon_discount_amount'], 0.001, "coupon {$type}/{$value}/{$max} unchanged at repeatCount=1");
        }
    }

    public function test_children_hold_the_share_and_container_aggregates_to_the_series_total(): void
    {
        $user = $this->firstOrderUser();
        $coupon = $this->savedCoupon();
        $parent = $this->repeatContainer($user, $coupon->code, aggregateCoupon: 30, aggregateAmount: 970);

        $template = $this->childTemplate($user, $coupon->code, shareCoupon: 3, shareAmount: 97);
        $dates = [];
        for ($i = 1; $i <= self::N; $i++) {
            $dates[] = now()->addDays($i)->toDateTimeString();
        }

        $created = app(RepeatBookingCreator::class)->createChunk($parent->id, $template, $dates);

        $this->assertSame(self::N, $created);

        $children = ServiceBooking::where('parent_booking_id', $parent->id)->get();
        $this->assertCount(self::N, $children);
        foreach ($children as $child) {
            $this->assertEqualsWithDelta(3.0, (float) $child->coupon_discount_amount, 0.001);
            $this->assertEqualsWithDelta(97.0, (float) $child->booking_amount, 0.001);
        }

        $breakdown = $parent->fresh()->amountBreakdown();
        $this->assertEqualsWithDelta(30.0, $breakdown['coupon_discount_amount'], 0.001, 'container coupon = sum of children');
        $this->assertEqualsWithDelta(970.0, $breakdown['booking_amount'], 0.001, 'container amount = sum of children');
        $this->assertEqualsWithDelta((float) $parent->coupon_discount_amount, $breakdown['coupon_discount_amount'], 0.001, 'stored parent aggregate matches the children sum');
    }

    public function test_a_repeat_series_counts_as_one_coupon_use_per_customer(): void
    {
        $user = $this->firstOrderUser();
        $coupon = $this->savedCoupon();
        $parent = $this->repeatContainer($user, $coupon->code, aggregateCoupon: 30, aggregateAmount: 970);

        $template = $this->childTemplate($user, $coupon->code, shareCoupon: 3, shareAmount: 97);
        $dates = [];
        for ($i = 1; $i <= self::N; $i++) {
            $dates[] = now()->addDays($i)->toDateTimeString();
        }
        app(RepeatBookingCreator::class)->createChunk($parent->id, $template, $dates);

        // 1 parent + N children all carry the coupon_code, but the series must count once.
        $usage = (int) ServiceBooking::userCouponUsage($user->id, $coupon->code)->get($coupon->code, 0);
        $this->assertSame(1, $usage, 'the whole series counts as a single coupon use, not N+1');
    }

    private function savedCoupon(): Coupon
    {
        $coupon = new Coupon();
        $coupon->code = 'REPEAT'.strtoupper(substr(uniqid(), -6));
        $coupon->title = 'Repeat test';
        $coupon->module_id = $this->provider()->module_id;
        $coupon->coupon_type = 'default';
        $coupon->discount_type = 'percent';
        $coupon->discount = 10;
        $coupon->max_discount = 30;
        $coupon->created_by = 'admin';
        $coupon->status = 1;
        $coupon->total_uses = 0;
        $coupon->save();

        return $coupon;
    }

    private function repeatContainer(User $user, string $couponCode, float $aggregateCoupon, float $aggregateAmount): ServiceBooking
    {
        $provider = $this->provider();

        $booking = new ServiceBooking();
        $booking->booking_type = 'repeat';
        $booking->parent_booking_id = null;
        $booking->user_id = $user->id;
        $booking->is_guest = 0;
        $booking->provider_id = $provider->id;
        $booking->zone_id = $provider->zone_id;
        $booking->module_id = $provider->module_id;
        $booking->booking_amount = $aggregateAmount;
        $booking->coupon_code = $couponCode;
        $booking->coupon_discount_amount = $aggregateCoupon;
        $booking->coupon_discount_by = 'admin';
        $booking->pro_discount = 0;
        $booking->ref_bonus_amount = 0;
        $booking->discount_amount = 0;
        $booking->payment_method = 'cash_after_service';
        $booking->payment_status = 'unpaid';
        $booking->scheduled = 1;
        $booking->quantity = 1;
        $booking->schedule_at = now()->addDay();
        $booking->markStatus('pending');
        $booking->save();

        return $booking;
    }

    private function childTemplate(User $user, string $couponCode, float $shareCoupon, float $shareAmount): array
    {
        $provider = $this->provider();

        return [
            'booking' => [
                'booking_type' => 'repeat',
                'is_custom' => 0,
                'user_id' => $user->id,
                'is_guest' => 0,
                'provider_id' => $provider->id,
                'zone_id' => $provider->zone_id,
                'module_id' => $provider->module_id,
                'booking_amount' => $shareAmount,
                'discount_amount' => 0,
                'discount_by' => 'vendor',
                'coupon_discount_amount' => $shareCoupon,
                'coupon_discount_by' => 'admin',
                'coupon_code' => $couponCode,
                'pro_discount' => 0,
                'ref_bonus_amount' => 0,
                'payment_method' => 'cash_after_service',
                'payment_status' => 'unpaid',
                'tax_amount' => 0,
                'tax_status' => 'excluded',
                'additional_charge' => 0,
                'scheduled' => 1,
                'quantity' => 1,
            ],
            'details' => [],
            'pro_offer' => ['status' => false, 'benefit' => null, 'plan_details' => null],
            'pro_discount' => 0,
        ];
    }

    private function setReferralSettings(string $type, float $amount): void
    {
        $values = [
            'new_customer_discount_status' => 1,
            'new_customer_discount_amount' => $amount,
            'new_customer_discount_amount_type' => $type === 'percentage' ? 'percentage' : 'amount',
            'new_customer_discount_amount_validity' => 30,
            'new_customer_discount_validity_type' => 'year',
        ];

        foreach ($values as $key => $value) {
            BusinessSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function firstOrderUser(): User
    {
        $suffix = uniqid();
        $user = new User();
        $user->f_name = 'Repeat';
        $user->l_name = 'Tester';
        $user->email = "repeat_{$suffix}@example.test";
        $user->phone = '+100'.substr($suffix, -9);
        $user->password = bcrypt('password');
        $user->ref_by = 1;
        $user->save();

        return $user;
    }
}
