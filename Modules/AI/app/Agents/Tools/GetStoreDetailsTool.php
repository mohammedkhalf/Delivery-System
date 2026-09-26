<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\CentralLogics\StoreLogic;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetStoreDetailsTool implements Tool
{
    /**
     * @param int[] $zoneIds Overlapping zones the customer falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int  $moduleId = null,
        private readonly array $zoneIds  = [],
    ) {}

    public function description(): string
    {
        return 'Get full details of a specific vendor or store by its ID: open/close schedule, today\'s hours, ratings, minimum order, delivery time, free delivery, coupons, and contact info. Use after SearchStoresTool returns a store ID.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'store_id' => $schema->number()->description('The ID of the store or vendor to get details for')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $args    = $request->all();
        $storeId = (int) ($args['store_id'] ?? 0);

        /** @var Store|null $store */
        // Scope to the customer's own module + zone so an arbitrary store_id
        // can't pull a store (and its phone/email/address/shipping config) from
        // another module or delivery zone. Without this the tool would expose
        // any store's contact + config by ID.
        $store = Store::with(['schedules', 'discount', 'activeCoupons'])
            ->where('id', $storeId)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->when(!empty($this->zoneIds), fn ($q) => $q->whereIn('zone_id', $this->zoneIds))
            ->first([
                'id', 'name', 'logo', 'cover_photo', 'rating',
                'delivery_time', 'minimum_order', 'free_delivery', 'active',
                'phone', 'email', 'address', 'zone_id', 'module_id',
                'order_count', 'featured', 'schedule_order', 'delivery',
                'take_away', 'veg', 'non_veg', 'off_day', 'announcement',
                'announcement_message', 'minimum_shipping_charge',
                'maximum_shipping_charge', 'per_km_shipping_charge',
            ]);

        if (! $store) {
            return "Store #{$storeId} not found.";
        }

        // Build schedule summary. store_schedule.day is an INTEGER day-of-week
        // (0=Sun..6=Sat, matching Carbon's dayOfWeek and the host's `open`
        // computation) — NOT a day name, so we must compare against
        // now()->dayOfWeek, not strtolower(format('l')) (which never matched).
        $schedules = $store->schedules->map(fn ($s) => [
            'day'        => (int) $s->getAttribute('day'),
            'open_time'  => $s->getAttribute('opening_time'),
            'close_time' => $s->getAttribute('closing_time'),
        ])->values()->all();

        $todayName  = now()->format('l');
        $todayDow   = now()->dayOfWeek;
        $todaySched = collect($schedules)->firstWhere('day', $todayDow);
        $schedInfo  = $todaySched
            ? "Today ({$todayName}): opens {$todaySched['open_time']}, closes {$todaySched['close_time']}"
            : 'No schedule listed for today';

        // Active store-level discount
        $storeDiscount = null;
        if ($store->discount) {
            $storeDiscount = [
                'discount'      => $store->discount->getAttribute('discount'),
                'discount_type' => $store->discount->getAttribute('discount_type'),
                'min_purchase'  => $store->discount->getAttribute('min_purchase'),
                'max_discount'  => $store->discount->getAttribute('max_discount'),
            ];
        }

        // Active coupons
        $coupons = $store->activeCoupons->map(fn ($c) => [
            'code'          => $c->getAttribute('code'),
            'discount'      => $c->getAttribute('discount'),
            'discount_type' => $c->getAttribute('discount_type'),
            'min_purchase'  => $c->getAttribute('min_purchase'),
        ])->values()->all();

        $name         = $store->getAttribute('name');
        // `rating` is the bucket array [5★,4★,3★,2★,1★]; derive a real average via
        // StoreLogic (casting the array straight to float yields 1.0 for every
        // store) and the count from the bucket sum. (`stores` has no rating_count
        // column — selecting it previously 500'd this whole tool.)
        $ratingBuckets = $store->getAttribute('rating');
        $ratingCount   = is_array($ratingBuckets) ? (int) array_sum($ratingBuckets) : 0;
        $avgRating     = (is_array($ratingBuckets) && count($ratingBuckets) === 5)
            ? (float) (StoreLogic::calculate_store_rating($ratingBuckets)['rating'] ?? 0)
            : 0.0;
        $deliveryTime = $store->getAttribute('delivery_time');
        $minOrder     = $store->getAttribute('minimum_order');

        // Open status mirrors the storefront's `open` column: the store is open
        // when it's active AND today's schedule window covers the current time.
        // (Previously this reflected only the manual `active` toggle and could
        // report OPEN outside business hours.)
        $nowT   = now()->format('H:i:s');
        $isOpen = (bool) $store->getAttribute('active')
            && $todaySched
            && !empty($todaySched['open_time']) && !empty($todaySched['close_time'])
            && $todaySched['open_time'] <= $nowT
            && $todaySched['close_time'] >= $nowT;

        $formatted = [
            'id'                      => $store->getKey(),
            'module_id'               => (int) $store->getAttribute('module_id'),
            'name'                    => $name,
            'logo'                    => $store->getAttribute('logo'),
            'logo_full_url'           => $store->logo_full_url,
            'cover_photo'             => $store->getAttribute('cover_photo'),
            'cover_photo_full_url'    => $store->cover_photo_full_url,
            'rating'                  => $avgRating,
            'rating_count'            => $ratingCount,
            'order_count'             => (int) $store->getAttribute('order_count'),
            'delivery_time'           => $deliveryTime,
            'minimum_order'           => (float) $minOrder,
            'free_delivery'           => (bool) $store->getAttribute('free_delivery'),
            'minimum_shipping_charge' => (float) $store->getAttribute('minimum_shipping_charge'),
            'maximum_shipping_charge' => (float) $store->getAttribute('maximum_shipping_charge'),
            'per_km_shipping_charge'  => (float) $store->getAttribute('per_km_shipping_charge'),
            'is_open'                 => $isOpen,
            'featured'                => (bool) $store->getAttribute('featured'),
            'delivery'                => (bool) $store->getAttribute('delivery'),
            'take_away'               => (bool) $store->getAttribute('take_away'),
            'schedule_order'          => (bool) $store->getAttribute('schedule_order'),
            'veg'                     => (bool) $store->getAttribute('veg'),
            'non_veg'                 => (bool) $store->getAttribute('non_veg'),
            'off_day'                 => $store->getAttribute('off_day'),
            'phone'                   => $store->getAttribute('phone'),
            'email'                   => $store->getAttribute('email'),
            'address'                 => $store->getAttribute('address'),
            'announcement'            => (bool) $store->getAttribute('announcement'),
            'announcement_message'    => $store->getAttribute('announcement_message'),
            'schedules'               => $schedules,
            'store_discount'          => $storeDiscount,
            'coupons'                 => $coupons,
        ];

        $this->context->recordTool('GetStoreDetailsTool');
        $this->context->addStores([$formatted]);

        $couponInfo = count($coupons) > 0
            ? ' — ' . count($coupons) . ' coupon(s) available'
            : '';

        $discountInfo = $storeDiscount
            ? ' — Store discount: ' . $storeDiscount['discount'] . ($storeDiscount['discount_type'] === 'percent' ? '%' : ' flat') . ' off'
            : '';

        return "{$name} — Rating: {$avgRating}/5 — {$schedInfo} — "
            . "Min order: {$minOrder} — Delivery: {$deliveryTime} min — "
            . ((bool) $store->getAttribute('free_delivery') ? 'FREE delivery' : 'Paid delivery')
            . $discountInfo . $couponInfo
            . ' — ' . ($isOpen ? 'Currently OPEN' : 'Currently CLOSED');
    }
}
