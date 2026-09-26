<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Builder\Contracts\OrderProvider as OrderProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\OrderDetailDTO;
use Modules\Builder\ValueObjects\Storefront\OrderSummaryDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

class OrderProvider implements OrderProviderContract
{
    /**
     * Order status → user-facing label. Each row mirrors a real value
     * in the `orders.order_status` column so the badge in the UI never
     * misreports the underlying state. The refund-flow rows used to
     * collapse into "Cancelled" — that was wrong, the order isn't
     * cancelled, it's somewhere in the refund pipeline.
     */
    private const STATUS_LABEL = [
        'pending'                 => 'Pending',
        'failed'                  => 'Pending',
        'confirmed'               => 'On The Way',
        'accepted'                => 'On The Way',
        'processing'              => 'On The Way',
        'handover'                => 'On The Way',
        'picked_up'               => 'On The Way',
        'delivered'               => 'Delivered',
        'canceled'                => 'Cancelled',
        'refund_requested'        => 'Refund Requested',
        'refund_request_canceled' => 'Refund Cancelled',
        'refunded'                => 'Refunded',
        'returned'                => 'Returned',
    ];

    /**
     * Order status → CustomBadge variant. Emitted with the order DTO so
     * the frontend doesn't pattern-match against translated labels.
     * Variants map to the storefront's `CustomBadge` palette
     * (info|warning|success|danger).
     */
    private const STATUS_VARIANT = [
        'pending'                 => 'info',
        'failed'                  => 'info',
        'confirmed'               => 'warning',
        'accepted'                => 'warning',
        'processing'              => 'warning',
        'handover'                => 'warning',
        'picked_up'               => 'warning',
        'delivered'               => 'success',
        'canceled'                => 'danger',
        'refund_requested'        => 'warning',
        'refund_request_canceled' => 'warning',
        'refunded'                => 'danger',
        'returned'                => 'danger',
    ];

    /**
     * Raw order statuses during which a "delivery in progress" polyline
     * should be drawn on the tracking map. Anything outside this set
     * means the DM either hasn't picked up yet or has already delivered,
     * so the route line would be misleading.
     */
    private const ROUTE_ACTIVE_STATUSES = ['handover', 'picked_up'];

    public function customerOrderListing(
        ?StorefrontScope $scope,
        ?int $customerId,
        string $bucket,
        array $statuses,
        int $perPage,
        int $page,
        string $pageName,
    ): PaginatedResult {
        if (!$customerId) {
            return PaginatedResult::fromPaginator(
                new LengthAwarePaginator([], 0, $perPage, $page, ['pageName' => $pageName])
            );
        }

        $predicate = $bucket === 'previous'
            ? fn (EloquentBuilder $q) => $q->whereIn('order_status', $statuses)
            : fn (EloquentBuilder $q) => $q->whereNotIn('order_status', $statuses);

        $paginator = $predicate($this->customerOrdersBaseQuery($scope, $customerId))
            ->withCount('details')
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $perPage,
                columns: ['id', 'order_status', 'order_type', 'order_amount', 'payment_status', 'created_at'],
                pageName: $pageName,
                page: $page,
            )
            ->through(fn (Order $order) => OrderSummaryDTO::fromArray([
                'id'            => $order->id,
                'status'        => $order->order_status,
                'statusLabel'   => $this->statusLabel($order->order_status),
                'statusVariant' => $this->statusVariant($order->order_status),
                'amount'        => (float) $order->order_amount,
                'items'         => (int) $order->details_count,
                'paid'          => $order->payment_status === 'paid',
                // Parcels have no line items; reorder is meaningless. Other
                // failure modes (out-of-stock, expired campaign, …) are
                // discovered at click time by OrderActionsProvider::reorder
                // — we don't hide the button on stock state.
                'reorderable'   => (string) ($order->order_type ?? '') !== 'parcel',
                // Cheap predicate (delivered + non-parcel). The modal's
                // hydration endpoint does the precise per-item check;
                // we don't pay an extra query per listing row just to
                // hide the button when everything's already reviewed.
                'reviewable'    => $order->order_status === 'delivered'
                                   && (string) ($order->order_type ?? '') !== 'parcel',
                'date'          => $order->created_at ? Carbon::parse($order->created_at)->format('h:iA, d M y') : null,
            ])->toArray());

        return PaginatedResult::fromPaginator($paginator);
    }

    public function customerOrdersCount(?StorefrontScope $scope, ?int $customerId): int
    {
        if (!$customerId) {
            return 0;
        }
        return $this->customerOrdersBaseQuery($scope, $customerId)->count();
    }

    private function customerOrdersBaseQuery(?StorefrontScope $scope, int $customerId): EloquentBuilder
    {
        return Order::query()
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->when(
                $scope?->subTenantId !== null,
                fn (EloquentBuilder $q) => $q->where('store_id', $scope->subTenantId),
            );
    }

    public function latestUnpaidDigitalOrder(?StorefrontScope $scope, ?int $customerId): ?array
    {
        if (!$customerId) {
            return null;
        }

        // The trait writes digital orders with `payment_status = unpaid`
        // and flips `order_status` to `failed` when the gateway doesn't
        // settle. That pair (unpaid + failed) is the "needs the user to
        // do something" signal the PaymentFailedModal handles.
        // COD orders never reach `payment_status = unpaid + failed` so
        // they're naturally excluded.
        $order = $this->customerOrdersBaseQuery($scope, $customerId)
            ->where('payment_status', 'unpaid')
            ->where('order_status', 'failed')
            ->orderByDesc('created_at')
            ->first(['id', 'order_amount', 'partially_paid_amount']);

        if (!$order) {
            return null;
        }

        return [
            'orderId'   => (int) $order->id,
            'dueAmount' => (float) ($order->order_amount - ($order->partially_paid_amount ?? 0)),
        ];
    }

    public function paymentReturnInfo(int $orderId): ?array
    {
        $order = Order::query()
            ->select(['id', 'is_guest', 'user_id', 'delivery_address', 'payment_status'])
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return null;
        }

        $stored = is_array($order->delivery_address)
            ? $order->delivery_address
            : (json_decode((string) $order->delivery_address, true) ?: []);

        return [
            'isGuest'       => (int) $order->is_guest === 1,
            'storedPhone'   => $stored['contact_person_number'] ?? null,
            'paymentStatus' => $order->payment_status !== null ? (string) $order->payment_status : null,
        ];
    }

    public function customerOrderDetails(?StorefrontScope $scope, ?int $customerId, int $orderId): ?array
    {
        if (!$customerId) {
            return null;
        }

        $order = Order::query()
            ->with([
                'details', 'store', 'customer', 'module:id,module_type',
                'delivery_man.rating',
                'delivery_man.last_location',
            ])
            ->where('id', $orderId)
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();

        return $order ? $this->formatOrder($order) : null;
    }

    /**
     * Storefront DTO for a single Order — same shape consumed by
     * `ProfileOrderDetails.jsx` and `OrderTrackingPage.jsx`.
     *
     * Public so the OrderTrackingProvider adapter can reuse the same
     * mapping after running its own (id + phone) lookup, instead of
     * re-implementing every field-by-field transformation.
     */
    public function formatOrder(Order $order): array
    {
        $detailsHydrated = Helpers::order_details_data_formatting($order->details);

        $rawStatus = (string) $order->order_status;
        $paid      = $order->payment_status === 'paid';

        // Cancellable mirrors the host's `cancel_order` window
        // (PlaceNewOrder line 242): pending OR failed. Cancelled
        // orders aren't re-cancellable from the UI.
        // Refundable mirrors `refund_request`: must be delivered + paid.
        // Reorderable: any non-parcel order with item details. Whether
        // the items are still buyable is a runtime decision (the
        // OrderActionsProvider's pre-flight returns specific reasons
        // when a line fails), so we don't hide the button on stock
        // status — let the user discover and see why.
        $cancellable = in_array($rawStatus, ['pending', 'failed'], true);
        // Refunds credit back to the wallet, so they ride on the
        // wallet-features master switch — when off, the refund button is
        // hidden on the storefront and the matching endpoint 404s.
        $refundable  = $rawStatus === 'delivered'
            && $paid
            && (bool) \config('builder.wallet_features_enabled', true);
        $reorderable = (string) ($order->order_type ?? '') !== 'parcel';
        // Reviewable when the order is delivered AND of a type that has
        // reviewable participants (items + DM). Parcel deliveries have
        // no items to rate — exclude. The modal itself handles the
        // "everything already reviewed" empty state, so we don't query
        // Review/DMReview here per-row (one extra query per listing
        // row would be the cost; the modal's hydration endpoint
        // already does the precise check).
        $reviewable = $rawStatus === 'delivered'
            && (string) ($order->order_type ?? '') !== 'parcel';

        // Delivery verification OTP — the customer reads it out to the
        // delivery partner at hand-off. Shown only when admin's
        // `order_delivery_verification` setting is on AND the order still
        // needs verifying (not in a terminal state where the OTP is moot).
        $verificationCode = (int) (Helpers::get_business_settings('order_delivery_verification') ?? 0) === 1
            && ! in_array($rawStatus, ['delivered', 'canceled', 'failed', 'refunded', 'returned'], true)
            && ! empty($order->otp)
                ? (string) $order->otp
                : null;

        return OrderDetailDTO::fromArray([
            'id'             => (string) $order->id,
            'date'           => $order->created_at
                ? Carbon::parse($order->created_at)->format('h:iA, d M y')
                : null,
            'scheduled'      => (bool) $order->scheduled,
            'scheduleAt'     => ((bool) $order->scheduled) && $order->schedule_at
                ? Carbon::parse($order->schedule_at)->format('h:iA, d M y')
                : null,
            'status'         => $this->statusLabel($rawStatus),
            'statusRaw'      => $rawStatus,
            'statusVariant'  => $this->statusVariant($rawStatus),
            'paid'           => $paid,
            'cancellable'    => $cancellable,
            'refundable'     => $refundable,
            'reorderable'    => $reorderable,
            'reviewable'     => $reviewable,
            'paymentMethod'  => $this->paymentLabel($order->payment_method),
            'paymentIcon'    => $this->paymentIcon($order->payment_method),
            'orderType'      => (string) ($order->order_type ?? 'delivery'),
            'moduleType'     => $order->module?->module_type,
            'items'          => $this->mapItems($detailsHydrated),
            'pricing'        => $this->mapPricing($order, $detailsHydrated),
            'delivery'       => $this->mapDelivery($order),
            'seller'         => $this->mapSeller($order->store),
            // null when no DM has been assigned yet — JSX hides the card.
            'deliveryMan'    => $this->mapDeliveryMan($order->delivery_man),
            // 4-step timeline + map coords. Returns null for orders that
            // can't be tracked (pickup orders, parcel, cancelled, etc.).
            'tracking'       => $this->mapTracking($order),
            // Offline-payment bank/reference details the customer submitted.
            'offlinePayment' => $this->mapOfflinePayment($order),
            // Cash "bring change for" amount — only when the shopper asked for change.
            'changeAmount'   => (int) ($order->bring_change_amount ?? 0) > 0 ? (float) $order->bring_change_amount : null,
            // Reason the order was cancelled — naturally null unless cancelled.
            'cancellationNote' => $order->cancellation_note ?: null,
            // Delivery verification OTP (gated above).
            'verificationCode' => $verificationCode,
        ])->toArray();
    }

    /**
     * Offline-payment info the customer submitted (method name + the fields
     * they filled), so the order-details page can show the bank information.
     * Null unless this is an offline-payment order with a stored record.
     */
    private function mapOfflinePayment(Order $order): ?array
    {
        if ($order->payment_method !== 'offline_payment') {
            return null;
        }

        $record = $order->offline_payments;
        if (! $record) {
            return null;
        }

        $info = \json_decode($record->payment_info ?? '[]', true);
        if (! \is_array($info)) {
            $info = [];
        }

        $labels = [
            'method_name'    => 'Payment Method',
            'name'           => 'Payment By',
            'date'           => 'Date',
            'transaction_id' => 'Transaction ID',
        ];

        $fields = [];
        foreach ($info as $key => $value) {
            if ($key === 'method_id' || $value === null || $value === '') {
                continue;
            }
            $fields[] = [
                'label' => $labels[$key] ?? \ucwords(\str_replace('_', ' ', (string) $key)),
                'value' => (string) $value,
            ];
        }

        return [
            'methodName' => $info['method_name'] ?? null,
            'status'     => (string) ($record->status ?? ''),
            'fields'     => $fields,
        ];
    }

    private function mapItems(array $details): array
    {
        $items = [];
        foreach ($details as $row) {
            $unitPrice = (float) ($row['price'] ?? 0);
            $qty       = (int) ($row['quantity'] ?? 0);
            $name      = $row['item_details']['name'] ?? null;
            if (!$name) {
                $name = 'Item';
            }

            $items[] = [
                'id'        => (int) ($row['id'] ?? 0),
                'name'      => (string) $name,
                'variant'   => (string) ($this->variantLabel($row['variation'] ?? null)
                    ?? $this->cleanScalar($row['variant'] ?? null)
                    ?? ''),
                'addons'    => (string) ($this->addOnsLabel($row['add_ons'] ?? null) ?? ''),
                'unitPrice' => $unitPrice,
                'qty'       => $qty,
                'total'     => max(0.0, ($unitPrice * $qty)),
                'image'     => $row['image_full_url'] ?? null,
            ];
        }
        return $items;
    }

    /**
     * Returns the trimmed string when it carries real content, or null when
     * it is blank, the literal string "null", or pure whitespace/quotes.
     */
    private function cleanScalar(mixed $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim((string) $value, " \t\n\r\0\x0B\"'");
        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            return null;
        }
        return $trimmed;
    }

    /**
     * Flatten the `variation` JSON of an OrderDetail into a single label.
     *
     * Two shapes coexist in the column:
     *   - food module: [{name, type, values:[{label, optionPrice}]}, ...]
     *   - other modules: [{type:"Green", price, stock}, ...]
     */
    private function variantLabel(mixed $variation): ?string
    {
        if (!is_array($variation) || empty($variation)) {
            return null;
        }

        $parts = [];
        foreach ($variation as $key => $value) {
            if (!is_array($value)) {
                if ($value !== null && $value !== '') {
                    $parts[] = is_string($key) ? "$key: $value" : (string) $value;
                }
                continue;
            }

            // Food shape: {name, type, values:[{label, optionPrice}]}
            if (isset($value['values']) && is_array($value['values'])) {
                $labels = [];
                foreach ($value['values'] as $v) {
                    if (is_array($v)) {
                        $label = $v['label'] ?? $v['value'] ?? '';
                        $price = isset($v['optionPrice']) ? (float) $v['optionPrice'] : 0.0;
                        if ($label === '') continue;
                        $labels[] = $price > 0
                            ? $label . ' (+$' . number_format($price, 2) . ')'
                            : (string) $label;
                    } elseif ((string) $v !== '') {
                        $labels[] = (string) $v;
                    }
                }
                if (!empty($labels)) {
                    $name = $value['name'] ?? (is_string($key) ? $key : null);
                    $parts[] = $name
                        ? $name . ': ' . implode(', ', $labels)
                        : implode(', ', $labels);
                }
                continue;
            }

            // Non-food shape: {type:"Green", price:500, stock:881}.
            // `price` here is the product's selling price for that variant — it's already
            // reflected in OrderDetail.price (the line's unit price), so we never want
            // to render it again as an upcharge. Only the `type` label is user-facing.
            if (isset($value['type']) && $value['type'] !== '') {
                $parts[] = (string) $value['type'];
                continue;
            }

            // Generic {name, value} fallback.
            if (isset($value['name']) && isset($value['value'])) {
                $parts[] = $value['name'] . ': ' . $value['value'];
            }
        }

        return $parts ? implode(' • ', $parts) : null;
    }

    /**
     * Flatten the selected `add_ons` JSON ([{id,name,price,quantity}, ...])
     * into a single suffix like "Add-ons: Cheese ×1, Coke ×1".
     *
     * Note: `Helpers::order_details_data_formatting` decodes `add_ons` without
     * the assoc flag, so each entry here may be a stdClass — handle both.
     */
    private function addOnsLabel(mixed $addOns): ?string
    {
        if (is_string($addOns)) {
            $addOns = json_decode($addOns, true);
        }
        if (!is_array($addOns) && !is_object($addOns)) {
            return null;
        }
        $addOns = (array) $addOns;
        if (empty($addOns)) {
            return null;
        }

        $labels = [];
        foreach ($addOns as $addOn) {
            $addOn = is_object($addOn) ? (array) $addOn : $addOn;
            if (!is_array($addOn)) continue;
            $name = $addOn['name'] ?? null;
            if (!$name) continue;
            $qty   = max(1, (int) ($addOn['quantity'] ?? 1));
            $price = (float) ($addOn['price'] ?? 0);
            $line  = "$name × $qty";
            if ($price > 0) {
                $line .= ' ($' . number_format($price * $qty, 2) . ')';
            }
            $labels[] = $line;
        }

        return $labels ? implode(', ', $labels) : null;
    }

    private function mapPricing(Order $order, array $details): array
    {
        $itemPrice   = 0.0;
        $addonsPrice = 0.0;

        foreach ($details as $row) {
            $itemPrice   += (float) ($row['price'] ?? 0) * (int) ($row['quantity'] ?? 0);
            $addonsPrice += (float) ($row['total_add_on_price'] ?? 0);
        }

        $subtotal = $itemPrice + $addonsPrice;
        $discount = (float) ($order->store_discount_amount ?? 0)
                  + (float) ($order->flash_admin_discount_amount ?? 0)
                  + (float) ($order->flash_store_discount_amount ?? 0);
        $vatTax = $order->tax_status === 'included'
            ? 0.0
            : (float) ($order->total_tax_amount ?? 0);

        $additionalChargeLabel = (string) (Helpers::get_business_settings('additional_charge_name')
            ?: 'Additional Charge');

        return [
            'itemPrice'             => $itemPrice,
            'addonsPrice'           => $addonsPrice,
            'subtotal'              => $subtotal,
            'discount'              => $discount,
            'couponDiscount'        => (float) ($order->coupon_discount_amount ?? 0),
            'vatTax'                => $vatTax,
            'taxIncluded'           => $order->tax_status === 'included',
            'dmTips'                => (float) ($order->dm_tips ?? 0),
            'deliveryCharge'        => (float) ($order->delivery_charge ?? 0),
            'additionalCharge'      => (float) ($order->additional_charge ?? 0),
            'additionalChargeLabel' => $additionalChargeLabel,
            'extraPackaging'        => (float) ($order->extra_packaging_amount ?? 0),
            'total'                 => (float) ($order->order_amount ?? 0),
        ];
    }

    private function mapDelivery(Order $order): array
    {
        $stored = is_array($order->delivery_address)
            ? $order->delivery_address
            : (json_decode((string) $order->delivery_address, true) ?: []);

        $fallback = $order->delivery_address_id
            ? CustomerAddress::query()->find($order->delivery_address_id)
            : null;

        $field = function (string $primaryKey, ?string $fallbackAttr = null) use ($stored, $fallback) {
            if (!empty($stored[$primaryKey])) {
                return $stored[$primaryKey];
            }
            if ($fallback && $fallbackAttr && !empty($fallback->{$fallbackAttr})) {
                return $fallback->{$fallbackAttr};
            }
            return null;
        };

        return [
            'label'   => $field('address_type', 'address_type'),
            'address' => $field('address', 'address'),
            'floor'   => $field('floor', 'floor'),
            'house'   => $field('house', 'house'),
            'road'    => $field('road', 'road'),
            'name'    => $field('contact_person_name', 'contact_person_name'),
            'phone'   => $field('contact_person_number', 'contact_person_number'),
            'email'   => $stored['contact_person_email'] ?? $order->customer?->email ?? null,
            // Customer's checkout instructions, surfaced on the order-details page.
            'instruction'         => $order->delivery_instruction ?: null,
            'unavailableItemNote' => $order->unavailable_item_note ?: null,
        ];
    }

    private function mapSeller(?Store $store): array
    {
        if (!$store) {
            return [
                'name'    => null,
                'rating'  => 0.0,
                'reviews' => 0,
                'image'   => null,
            ];
        }

        [$avg, $count] = $this->ratingFromBuckets($store->getRawOriginal('rating'));

        return [
            'name'    => $store->name,
            'rating'  => round($avg, 1),
            'reviews' => $count,
            'image'   => $store->logo_full_url ?? null,
        ];
    }

    /**
     * Delivery-man card data — same shape as seller (name + rating +
     * reviews + image) plus a phone the customer can call directly.
     * Returns null when no DM is assigned (JSX hides the card).
     *
     * `DeliveryMan::rating()` is a `hasMany` that selects an aggregated
     * row (`avg(rating)`, `count(delivery_man_id)`) grouped by
     * `delivery_man_id` — so `$dm->rating` is a Collection containing
     * (typically) one row. Use `->first()` to unwrap it; reading
     * `$dm->rating?->average` directly hits a HigherOrderCollectionProxy
     * and throws on cast.
     */
    private function mapDeliveryMan(mixed $dm): ?array
    {
        if (!$dm) {
            return null;
        }

        $ratingRow = $dm->relationLoaded('rating')
            ? $dm->getRelation('rating')->first()
            : $dm->rating()->first();
        $average = (float) ($ratingRow->average ?? 0);
        $count   = (int)   ($ratingRow->rating_count ?? 0);

        $name = trim(($dm->f_name ?? '') . ' ' . ($dm->l_name ?? ''));
        if ($name === '') {
            $name = 'Delivery Partner';
        }

        return [
            // Surfaced so the order-details DM card's chat icon can
            // deep-link into the inbox at this specific delivery man
            // (via ?openWith=dm:<id>). The provider's
            // reachableUserInfoIds + UserInfo-ensure handle the rest.
            'id'      => (int) ($dm->id ?? 0),
            'name'    => $name,
            'phone'   => $dm->phone ?? null,
            'rating'  => round($average, 1),
            'reviews' => $count,
            'image'   => $dm->image_full_url ?? null,
        ];
    }

    /**
     * Four-step delivery timeline + map coordinates. The timeline maps
     * the 7+ underlying `order_status` values onto the four steps the
     * customer cares about:
     *
     *   confirmed   ← pending, confirmed, accepted
     *   preparing   ← processing
     *   on_the_way  ← handover, picked_up
     *   delivered   ← delivered
     *
     * Each step exposes `done` (in the past) and `current` (the active
     * one) so the JSX can colour the dots and connecting line.
     *
     * Returns null for orders that can't be tracked at all:
     *   - take_away / parcel (no delivery route)
     *   - canceled / refund_requested / refund_request_canceled / refunded
     *
     * Coordinates: storeLocation (route start), customerLocation (end),
     * deliveryManLocation (current DM position, null when no DM assigned
     * or DM hasn't reported a position yet).
     */
    private function mapTracking(Order $order): ?array
    {
        $orderType = (string) ($order->order_type ?? 'delivery');
        if ($orderType !== 'delivery') {
            return null;
        }

        $raw = (string) $order->order_status;
        // Hide the tracker for orders that are NOT in a deliverable
        // trajectory — refunds and cancellations get their own status
        // chip on the order header, no need for a misleading timeline.
        if (in_array($raw, ['canceled', 'refunded', 'refund_requested', 'refund_request_canceled', 'failed'], true)) {
            return null;
        }

        // Map raw status → step index (0-based, 4 steps total).
        $bucket = match ($raw) {
            'pending', 'confirmed', 'accepted' => 0,
            'processing'                       => 1,
            'handover', 'picked_up'            => 2,
            'delivered'                        => 3,
            default                            => 0,
        };

        $stepDefs = [
            ['key' => 'confirmed',  'label' => 'Order Confirmed'],
            ['key' => 'preparing',  'label' => 'Preparing items'],
            ['key' => 'on_the_way', 'label' => 'Items on the way'],
            ['key' => 'delivered',  'label' => 'Delivered'],
        ];

        $steps = [];
        foreach ($stepDefs as $i => $def) {
            $steps[] = [
                'key'     => $def['key'],
                'label'   => $def['label'],
                'done'    => $i < $bucket,
                'current' => $i === $bucket,
            ];
        }

        // Store location is the route's start point.
        $storeLoc = null;
        if ($order->store && $order->store->latitude && $order->store->longitude) {
            $storeLoc = [
                'lat' => (float) $order->store->latitude,
                'lng' => (float) $order->store->longitude,
            ];
        }

        // Customer drop-off — from delivery_address JSON column or the
        // fallback CustomerAddress row when the JSON is empty.
        $customerLoc = null;
        $stored = is_array($order->delivery_address)
            ? $order->delivery_address
            : (json_decode((string) $order->delivery_address, true) ?: []);
        if (!empty($stored['latitude']) && !empty($stored['longitude'])) {
            $customerLoc = [
                'lat' => (float) $stored['latitude'],
                'lng' => (float) $stored['longitude'],
            ];
        } elseif ($order->delivery_address_id) {
            $row = CustomerAddress::query()->find($order->delivery_address_id);
            if ($row && $row->latitude && $row->longitude) {
                $customerLoc = [
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                ];
            }
        }

        // DM's last reported position, when one is assigned.
        $dmLoc = null;
        $dm = $order->delivery_man;
        if ($dm) {
            $last = $dm->relationLoaded('last_location')
                ? $dm->getRelation('last_location')
                : $dm->last_location()->first();
            if ($last && $last->latitude && $last->longitude) {
                $dmLoc = [
                    'lat' => (float) $last->latitude,
                    'lng' => (float) $last->longitude,
                ];
            }
        }

        return [
            'steps'               => $steps,
            'storeLocation'       => $storeLoc,
            'customerLocation'    => $customerLoc,
            'deliveryManLocation' => $dmLoc,
            // True only when the DM is en-route. The frontend uses this
            // to decide whether to draw the polyline. Keeps raw status
            // strings out of the React tree.
            'routeActive'         => in_array($raw, self::ROUTE_ACTIVE_STATUSES, true) && $dmLoc !== null,
            // False once the order is delivered — frontend uses this to
            // stop the 10s tracking-refresh polling. Same portability
            // reason as `routeActive`: don't leak raw status enums.
            'isLive'              => $raw !== 'delivered',
        ];
    }

    /**
     * stores.rating is JSON like {"1":0,"2":1,"3":1,"4":4,"5":3}.
     * @return array{0: float, 1: int}
     */
    private function ratingFromBuckets(mixed $raw): array
    {
        $buckets = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        $count   = 0;
        $sum     = 0.0;
        foreach ($buckets as $stars => $n) {
            $stars = (int) $stars;
            $n     = (int) $n;
            $count += $n;
            $sum   += $stars * $n;
        }
        return [$count > 0 ? $sum / $count : 0.0, $count];
    }

    private function statusLabel(?string $status): string
    {
        return self::STATUS_LABEL[(string) $status] ?? Str::title(str_replace('_', ' ', (string) $status));
    }

    private function statusVariant(?string $status): string
    {
        return self::STATUS_VARIANT[(string) $status] ?? 'info';
    }

    private function paymentLabel(?string $key): ?string
    {
        if (!$key) return null;
        $row = $this->paymentConfig($key);
        $title = $row?->additional_data?->gateway_title ?? null;
        return $title ?: Str::title(str_replace('_', ' ', $key));
    }

    private function paymentIcon(?string $key): ?string
    {
        if (!$key) return null;
        $row = $this->paymentConfig($key);
        $filename = $row?->additional_data?->gateway_image ?? null;
        if (!$filename) return null;

        return Helpers::get_full_url(
            'payment_modules/gateway_image',
            $filename,
            $row?->additional_data?->storage ?? 'public',
        );
    }

    private function paymentConfig(string $key): ?object
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $row = DB::table('addon_settings')
            ->where('key_name', $key)
            ->where('settings_type', 'payment_config')
            ->value('additional_data');

        if (!$row) {
            return $cache[$key] = null;
        }

        $decoded = json_decode((string) $row);
        return $cache[$key] = $decoded ? (object) ['additional_data' => $decoded] : null;
    }
}
