<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\ProductLogic;
use App\Mail\RefundRequest;
use App\Models\AddOn;
use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Order;
use App\Models\OrderCancelReason;
use App\Models\OrderDetail;
use App\Models\Refund;
use App\Models\RefundReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Modules\Builder\Contracts\CartProvider;
use Modules\Builder\Contracts\OrderActionsProvider as OrderActionsProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * Storefront-side counterparts to the host's order admin/customer
 * actions. Five operations:
 *   - cancel:               wraps `Api\V1\OrderController::cancel_order`
 *   - switchToCod:          wraps `Admin\OrderController::switch_to_cod`
 *   - repay:                re-issues the gateway redirect for an unpaid order
 *   - cancellationReasons:  admin-configured customer cancel reasons
 *   - refundReasons:        admin-configured refund reasons
 *   - requestRefund:        wraps `Api\V1\OrderController::refund_request`
 *
 * Ownership:
 *   - authenticated: `(user_id, is_guest=0)` joins
 *   - guest:         phone-match against `delivery_address->contact_person_number`
 *
 * Mirrors the host's existing predicates so a guest can't pivot into
 * another guest's order by guessing the id alone.
 */
class OrderActionsProvider implements OrderActionsProviderContract
{
    public function cancellationReasons(): array
    {
        // user_type='customer' so we don't surface store/deliveryman
        // reasons (those exist in the same table for vendor/dm UIs).
        return OrderCancelReason::query()
            ->where('status', 1)
            ->where('user_type', 'customer')
            ->orderBy('id')
            ->get(['id', 'reason'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'reason' => (string) $r->reason])
            ->all();
    }

    public function refundReasons(): array
    {
        return RefundReason::query()
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'reason'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'reason' => (string) $r->reason])
            ->all();
    }

    public function cancel(
        ?StorefrontScope $scope,
        int $orderId,
        ?int $customerId,
        ?string $guestPhone,
        ?string $reason = null,
        ?string $note = null,
    ): array {
        $order = $this->loadOrder($scope, $orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        // Mirrors `cancel_order` line 242. We deliberately drop 'canceled'
        // here even though the host allows re-cancel — re-cancelling
        // re-runs stock/flash restoration, which double-credits the
        // inventory.
        $allowed = ['pending', 'failed'];
        if (!in_array($order->order_status, $allowed, true)) {
            return ['success' => false, 'error' => 'This order can no longer be cancelled.'];
        }

        // Mirror host validator: at least one of reason/note is required.
        // Checked BEFORE any writes so an invalid call can't leave the
        // order with restored stock but no cancellation row.
        if (!$reason && !$note) {
            return ['success' => false, 'error' => 'Please provide a reason or a note for cancelling.'];
        }

        // Stock + flash-discount restoration (same as the host).
        $hasStock = config('module.' . ($order->module->module_type ?? '') . '.stock');
        $hasFlash = $order->flash_admin_discount_amount > 0
                 && $order->flash_store_discount_amount > 0;

        try {
            DB::beginTransaction();

            if ($hasStock || $hasFlash) {
                foreach ($order->details as $detail) {
                    $item = $detail->campaign ?? $detail->item;
                    if ($hasStock) {
                        $variant = json_decode($detail->variation, true);
                        $variantType = !empty($variant) ? ($variant[0]['type'] ?? null) : null;
                        ProductLogic::update_stock($item, -$detail->quantity, $variantType)?->save();
                    }
                    if ($hasFlash) {
                        ProductLogic::update_flash_stock($detail->item, $detail->quantity, true)?->save();
                    }
                }
            }

            if ((int) $order->is_guest === 0) {
                try { OrderLogic::refund_before_delivered($order); } catch (\Throwable) { /* best effort */ }
            }

            $order->order_status         = 'canceled';
            $order->canceled             = now();
            $order->cancellation_reason  = $reason ?: null;
            $order->cancellation_note    = $note   ?: null;
            $order->canceled_by          = 'customer';
            $order->save();

            DB::commit();
        } catch (\Throwable) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Could not cancel the order. Please try again.'];
        }

        try { Helpers::send_order_notification($order); } catch (\Throwable) { /* best effort */ }

        return ['success' => true, 'message' => 'Order cancelled.'];
    }

    public function switchToCod(
        ?StorefrontScope $scope,
        int $orderId,
        ?int $customerId,
        ?string $guestPhone,
    ): array {
        $order = $this->loadOrder($scope, $orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order->payment_method === 'cash_on_delivery') {
            return ['success' => false, 'error' => 'This order is already cash on delivery.'];
        }

        // Mirror `Admin\OrderController::switch_to_cod`:
        //  - delete any offline_payments rows
        //  - flip partial-payment legs from unpaid → COD
        //  - flip order back to pending if not already
        try {
            DB::beginTransaction();

            $order->offline_payments()?->delete();

            if ($order->payment_method === 'partial_payment') {
                $order->payments()
                    ->where('payment_status', 'unpaid')
                    ->update(['payment_method' => 'cash_on_delivery']);
            }

            if ($order->order_status !== 'pending') {
                $order->order_status = 'pending';
            }
            $order->payment_method = 'cash_on_delivery';
            $order->save();

            DB::commit();
        } catch (\Throwable) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Could not switch payment method. Please try again.'];
        }

        try { Helpers::send_order_notification($order); } catch (\Throwable) { /* best effort */ }

        return ['success' => true, 'message' => 'Switched to Cash on Delivery.'];
    }

    public function repay(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        string $paymentMethod,
    ): array {
        $order = $this->loadOrder($scope, $orderId, $customerId, null);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order->payment_status === 'paid') {
            return ['success' => false, 'error' => 'This order has already been paid.'];
        }

        // Update the order's selected gateway so PaymentController can
        // route to the right addon. Order.payment_method stays as
        // 'digital_payment' (the bucket); the gateway-specific key
        // travels in the URL.
        $order->payment_method = 'digital_payment';
        $order->save();

        // Path-based callback for the same reason CheckoutProvider uses
        // it — the host's `?flag=…` append would collide with any query
        // string we put here. PaymentCallbackController normalises the
        // result and redirects to profile/orders.
        $callback = url(route(
            'storefront.payment_callback',
            ['orderId' => $orderId],
            false,
        ));

        try {
            $url = route('payment-mobile', [
                'order_id'         => $orderId,
                'customer_id'      => $customerId,
                'payment_method'   => $paymentMethod,
                'payment_platform' => 'web',
                'callback'         => $callback,
            ]);
        } catch (\Throwable) {
            return ['success' => false, 'error' => 'Could not build the payment URL.'];
        }

        return ['success' => true, 'paymentRedirect' => $url ?: null];
    }

    public function requestRefund(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        string $customerReason,
        ?string $customerNote,
        array $imageFiles,
    ): array {
        // Same admin gate the host's refund_request enforces.
        if ((int) (\App\Models\BusinessSetting::query()->where('key', 'refund_active_status')->value('value') ?? 0) !== 1) {
            return ['success' => false, 'error' => 'Refund requests are not currently accepted.'];
        }

        $order = $this->loadOrder($scope, $orderId, $customerId, null);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order->order_status !== 'delivered' || $order->payment_status !== 'paid') {
            return ['success' => false, 'error' => 'You can only request a refund on a delivered, paid order.'];
        }

        // Upload each image into the same `refund/` bucket the host
        // controller uses, and store the path JSON on the Refund row.
        // Mirrors `OrderController::refund_request` lines 320–328.
        $imagePaths = [];
        foreach ($imageFiles as $file) {
            try {
                $path = Helpers::upload('refund/', 'png', $file);
                $imagePaths[] = ['img' => $path, 'storage' => Helpers::getDisk()];
            } catch (\Throwable) {
                // Skip individual upload failures rather than aborting
                // the whole refund — partial proof is better than none.
            }
        }

        $refundAmount = round(
            $order->order_amount - $order->delivery_charge - ($order->dm_tips ?? 0),
            (int) (config('round_up_to_digit') ?? 2),
        );

        try {
            DB::beginTransaction();

            $refund = new Refund();
            $refund->order_id        = $order->id;
            $refund->user_id         = $order->user_id;
            $refund->order_status    = $order->order_status;
            $refund->refund_status   = 'pending';
            $refund->refund_method   = 'wallet';
            $refund->customer_reason = $customerReason;
            $refund->customer_note   = $customerNote;
            $refund->refund_amount   = $refundAmount;
            $refund->image           = json_encode($imagePaths);
            $refund->save();

            $order->order_status     = 'refund_requested';
            $order->refund_requested = now();
            $order->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // Don't leak the underlying exception text to the customer —
            // it can carry SQL fragments, file paths, or PII. Logged for
            // ops; user sees a generic message.
            Log::warning('Refund request failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Could not file the refund request. Please try again.'];
        }

        // Best-effort admin notification — same as host's controller.
        try {
            $admin = Admin::query()->where('role_id', 1)->first();
            $mailStatus = Helpers::get_mail_status('refund_request_mail_status_admin');
            if (config('mail.status')
                && $admin?->email
                && $mailStatus == '1'
                && Helpers::getNotificationStatusData('admin', 'order_refund_request', 'mail_status')
            ) {
                Mail::to($admin->getRawOriginal('email'))->send(new RefundRequest($order->id));
            }
        } catch (\Throwable) { /* swallow — refund itself is committed */ }

        return ['success' => true, 'message' => 'Refund request submitted.'];
    }

    /**
     * Resolve the order with the same scope/ownership predicates the
     * host uses. Returns null when the lookup misses — caller surfaces
     * a generic "Order not found" so we don't leak whether it's a
     * scope mismatch vs. a wrong phone.
     */
    private function loadOrder(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone): ?Order
    {
        $normalizedPhone = $guestPhone
            ? (str_starts_with($guestPhone, '+') ? $guestPhone : '+' . ltrim($guestPhone, '+ '))
            : null;

        return Order::query()
            ->with(['details', 'module:id,module_type', 'store'])
            ->where('id', $orderId)
            ->when(
                $customerId,
                fn ($q) => $q->where('user_id', $customerId)->where('is_guest', 0),
                fn ($q) => $q
                    ->where('is_guest', 1)
                    ->whereJsonContains('delivery_address->contact_person_number', $normalizedPhone),
            )
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();
    }

    /* ─── invoice ─────────────────────────────────────────── */

    /**
     * Stream the host's existing PDF invoice for an owned order. Reuses
     * the same `order-invoice` blade view + `Helpers::gen_mpdf` pipeline
     * that `HomeController::order_invoice` uses, so the storefront PDF
     * is byte-identical to admin/vendor-side downloads of the same order.
     *
     * `gen_mpdf` writes the PDF to the response stream via mpdf->Output(
     * filename, 'D') — caller must not emit a second response after this
     * returns success.
     */
    public function downloadInvoice(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone = null): array
    {
        // loadOrder() enforces (user_id, is_guest=0) ownership + scope.
        // The host's /order-invoice/{id} route has NO ownership check
        // (it accepts a base64-encoded id and 200s for any signed-in
        // user). We deliberately don't expose that route from the
        // storefront; this method is the safe path.
        //
        // loadOrder already eager-loads `store` for the cancel/refund
        // flows — the storefront invoice header reads store.{name,
        // address, phone, email, logo_full_url} for branding, so the
        // existing eager-load is exactly what we need here too.
        //
        // loadOrder enforces ownership by auth customer id OR matching guest
        // phone, so the widened (guest-capable) signature reuses the same guard.
        $order = $this->loadOrder($scope, $orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        try {
            // `storefront-order-invoice` is a host-owned fork of
            // `resources/views/order-invoice.blade.php`. Same layout,
            // but the header brands the SELLING STORE (logo, name,
            // address, phone, email) instead of the platform.
            //
            // Lives in the host's resource path (NOT in Modules/Builder/
            // Resources/views) because the blade uses host helpers like
            // `\App\CentralLogics\Helpers::get_full_url`, `BusinessSetting`,
            // and `Store.logo_full_url` accessor. Putting it in the
            // Builder module would leak `App\*` references into the
            // portable layer. The host owns the template; the adapter
            // (which is already host-coupled by design) references it.
            //
            // Admin/vendor PDFs still use the platform-branded view
            // because their controllers call View::make('order-invoice').
            $BusinessData = BusinessSetting::query()
                ->whereIn('key', ['footer_text', 'email_address'])
                ->pluck('value', 'key');
            $logo = BusinessSetting::query()->where('key', 'logo')->first();

            $mpdfView = View::make('storefront-order-invoice', compact('order', 'BusinessData', 'logo'));
            Helpers::gen_mpdf(view: $mpdfView, file_prefix: 'OrderInvoice', file_postfix: (string) $order->id);
        } catch (\Throwable $e) {
            Log::warning('Invoice download failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Could not generate the invoice. Please try again.'];
        }

        return ['success' => true];
    }

    /**
     * 6amMart is a multi-vendor mart with no digital-product concept, so there
     * is no downloadable file to stream. Returns the not-available payload; the
     * storefront never surfaces a digital-download action for mart orders.
     */
    public function downloadDigitalProduct(
        ?StorefrontScope $scope,
        int $orderDetailId,
        ?int $customerId,
        ?string $guestPhone,
    ): array {
        return ['success' => false, 'error' => 'Digital downloads are not available.'];
    }

    /* ─── reorder ─────────────────────────────────────────── */

    /**
     * Re-add every item from a past order to the cart. All-or-nothing —
     * if any line fails the live-catalog pre-flight, nothing is added
     * and the caller gets the full list of human-readable reasons.
     *
     * Auth-only. Cart is store-scoped, so the active storefront is the
     * store the items will land in (defensive store-match check below).
     */
    public function reorder(?StorefrontScope $scope, int $orderId, int $customerId): array
    {
        // === DEBUG: every gate logs why it returned or proceeded ===
        Log::info('Reorder: start', [
            'orderId'      => $orderId,
            'customerId'   => $customerId,
            'scope'        => $scope ? [
                'subTenantId' => $scope->subTenantId,
                'moduleId'    => $scope->moduleId,
                'tenantId'    => $scope->tenantId,
            ] : null,
        ]);

        // Eager-load both polymorphic targets so the per-line validator
        // doesn't N+1. `details.item` and `details.campaign` both fire,
        // only one is non-null per row.
        $order = Order::query()
            ->with(['details.item', 'details.campaign', 'module:id,module_type', 'store'])
            ->where('id', $orderId)
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();

        if (!$order) {
            Log::info('Reorder: order not found', ['orderId' => $orderId, 'customerId' => $customerId]);
            return ['success' => false, 'errors' => ['Order not found.']];
        }
        Log::info('Reorder: order loaded', [
            'orderId'   => $order->id,
            'storeId'   => $order->store_id,
            'moduleId'  => $order->module_id,
            'orderType' => $order->order_type,
            'detailsCount' => $order->details?->count() ?? 0,
        ]);

        if ((string) ($order->order_type ?? '') === 'parcel') {
            return ['success' => false, 'errors' => ['Parcel orders can\'t be reordered.']];
        }

        $details = $order->details ?? collect();
        if ($details->isEmpty()) {
            return ['success' => false, 'errors' => ['This order has no items to re-add.']];
        }

        // Resolve once for all lines. We require an ACTIVE storefront
        // store/module — the cart is store-scoped and `module_id` is
        // NOT NULL on the carts table; without a scope, the write either
        // bombs at the DB layer or silently lands in an unreadable
        // (NULL module) row. Bail with a clear message instead.
        $activeStoreId  = $scope?->subTenantId;
        $activeModuleId = $scope?->moduleId;
        if (!$activeStoreId || !$activeModuleId) {
            Log::info('Reorder: missing scope', [
                'activeStoreId'  => $activeStoreId,
                'activeModuleId' => $activeModuleId,
            ]);
            return ['success' => false, 'errors' => ['Open the storefront first, then reorder.']];
        }
        // Defensive — should never trip because the listing is scope-
        // filtered, but make the cross-store mismatch explicit.
        if ((int) $order->store_id !== (int) $activeStoreId) {
            Log::info('Reorder: store mismatch', [
                'orderStoreId'  => $order->store_id,
                'activeStoreId' => $activeStoreId,
            ]);
            return ['success' => false, 'errors' => ['This order is from a different store. Switch stores to reorder it.']];
        }
        $moduleType     = (string) ($order->module?->module_type ?? '');
        $hasStock       = (bool) config('module.' . $moduleType . '.stock');
        Log::info('Reorder: resolved context', [
            'activeStoreId'  => $activeStoreId,
            'activeModuleId' => $activeModuleId,
            'moduleType'     => $moduleType,
            'hasStock'       => $hasStock,
        ]);

        // Aggregate the customer's existing cart-line qty for each
        // (item, item_type, variation-key) tuple so we can fail
        // pre-flight (rather than mid-write) when reorder + existing
        // would exceed `maximum_cart_quantity`.
        $cart = \app(CartProvider::class);

        $errors   = [];
        $payloads = [];
        foreach ($details as $d) {
            [$ok, $payload, $err] = $this->planReorderLine($d, $activeStoreId, $activeModuleId, $moduleType, $hasStock, $customerId);
            Log::info('Reorder: planLine', [
                'detailId'      => $d->id,
                'itemId'        => $d->item_id,
                'campaignId'    => $d->item_campaign_id,
                'quantity'      => $d->quantity,
                'ok'            => $ok,
                'error'         => $err,
                'payloadPrice'  => $payload['price'] ?? null,
            ]);
            if (!$ok) {
                $errors[] = $err;
                continue;
            }
            $payloads[] = $payload;
        }

        if ($errors) {
            Log::info('Reorder: pre-flight rejected', ['errors' => $errors]);
            return ['success' => false, 'errors' => $errors];
        }

        if (empty($payloads)) {
            // Belt and braces — if planReorderLine ever returned [true, null, null]
            // we'd silently "succeed" with nothing in cart. Make it noisy.
            Log::warning('Reorder: empty payloads after clean pre-flight', [
                'orderId'      => $order->id,
                'detailsCount' => $details->count(),
            ]);
            return ['success' => false, 'errors' => ['Nothing to add — please contact support.']];
        }

        // All clean — write in one transaction so we never end up with a
        // partial cart on a freak failure. Reuses CartProvider::add()
        // (the single writer) so dedupe + JSON-encode quirk + store
        // scoping stay consistent with normal cart adds.
        try {
            DB::beginTransaction();
            foreach ($payloads as $p) {
                Log::info('Reorder: cart->add', ['payload' => $p]);
                $cart->add($p);
            }
            DB::commit();
            Log::info('Reorder: committed', [
                'orderId' => $order->id,
                'rows'    => count($payloads),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // CartProvider::add throws this for known-bad reasons (item
            // gone, wrong store, max-qty exceeded after existing-line
            // sum). Surface the host's message so the user sees the
            // actual cause instead of a generic "try again".
            DB::rollBack();
            $messages = [];
            foreach ($e->errors() as $field => $fieldMessages) {
                foreach ((array) $fieldMessages as $m) {
                    $messages[] = (string) $m;
                }
            }
            return [
                'success' => false,
                'errors'  => $messages ?: ['Could not add items to cart.'],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('Reorder write failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'errors' => ['Could not add items to cart. Please try again.']];
        }

        $count = count($payloads);
        // Tag the message with the order id so identical reorders fire
        // a fresh FlashToaster event (its useEffect deps are the message
        // strings; same-string-twice would silently skip the toast).
        $orderId = (int) $order->id;
        return [
            'success' => true,
            'count'   => $count,
            'message' => $count === 1
                ? "Added 1 item from order #{$orderId} to your cart."
                : "Added {$count} items from order #{$orderId} to your cart.",
        ];
    }

    /**
     * Per-line pre-flight. Returns one of:
     *   [true,  payload, null]   → ready for CartProvider::add()
     *   [false, null,    string] → human-readable reason for the user
     */
    private function planReorderLine(
        OrderDetail $d,
        int $activeStoreId,
        int $activeModuleId,
        string $moduleType,
        bool $hasStock,
        int $customerId,
    ): array {
        $isCampaign = !empty($d->item_campaign_id);
        $item = $isCampaign ? ($d->campaign ?? null) : ($d->item ?? null);

        // Use the snapshot name from order_details.item_details when the
        // item itself is gone, so errors are still recognizable.
        $snapshotName = $this->snapshotItemName($d);

        if (!$item) {
            return [false, null, "{$snapshotName} is no longer available."];
        }

        // Defensive: should never trip because ProfileOrders is scope-
        // filtered, but if the user somehow holds an order id from
        // another store, refuse.
        if ((int) $item->store_id !== $activeStoreId) {
            return [false, null, "{$snapshotName} is from a different store."];
        }
        if ((int) ($item->module_id ?? 0) !== $activeModuleId) {
            return [false, null, "{$snapshotName} isn't available in this section."];
        }

        if ((int) $item->status !== 1) {
            return [false, null, "{$snapshotName} is currently unavailable."];
        }

        // Item-only (campaigns don't carry is_approved). Eloquent doesn't
        // declare DB columns as class properties so `property_exists` is
        // useless here — read via the magic getter and default to 1 ("approved")
        // when the column doesn't exist on the model (ItemCampaign).
        if (!$isCampaign && (int) ($item->is_approved ?? 1) !== 1) {
            return [false, null, "{$snapshotName} is currently unavailable."];
        }

        // Campaign window check.
        if ($isCampaign) {
            $end = $item->end_date ? $item->end_date->format('Y-m-d') : null;
            if ($end && $end < date('Y-m-d')) {
                return [false, null, "The offer for {$snapshotName} has ended."];
            }
        }

        // Time-of-day check (only when the item carries window fields).
        $now = date('H:i:s');
        $startTime = $item->available_time_starts ?? null;
        $endTime   = $item->available_time_ends ?? null;
        if ($startTime && $endTime && ($now < (string) $startTime || $now > (string) $endTime)) {
            return [false, null, "{$snapshotName} isn't available right now."];
        }

        $variation = $this->decodeJsonArray($d->variation);
        // order_details.variation stores food variation in a different
        // shape than the cart expects. Cart-shape:
        //     [{name, values: {label: ["small"]}}]
        // Order-details snapshot shape:
        //     [{name, values: [{label, optionPrice}]}]
        // Helpers::cart_product_data_formatting (called by CartProvider::add
        // via list()) reads `values.label` directly and crashes on the
        // snapshot shape. Normalize once, here.
        if ($moduleType === 'food') {
            $variation = $this->normalizeFoodVariation($variation);
        }
        $addOnIds  = array_values(array_map('intval', $this->decodeJsonArray($d->add_on_ids)));
        $addOnQtys = array_values(array_map('intval', $this->decodeJsonArray($d->add_on_qtys)));
        $qty       = max(1, (int) $d->quantity);

        // Variation still offered. We match on `type` (non-food) or
        // `name`+`values.label` (food) so a renamed-but-still-there
        // option doesn't silently fall through.
        $variationError = $this->validateVariation($item, $variation, $moduleType);
        if ($variationError) {
            return [false, null, str_replace('{name}', $snapshotName, $variationError)];
        }

        // Existing cart qty for the same combo — needed by both the stock
        // check and the max-cart-qty check below, because CartProvider::add
        // bumps an existing line by the reorder qty (it doesn't replace).
        // Queried once; both checks compare `(existing + reorder)` against
        // their respective ceilings.
        $existingCartQty = $this->existingCartLineQty(
            $item,
            $variation,
            $addOnIds,
            $addOnQtys,
            $customerId,
            $activeModuleId,
        );

        // Stock — only when the module enforces it.
        if ($hasStock) {
            $stockError = $this->validateStock($item, $variation, $qty, $snapshotName, $existingCartQty, $moduleType);
            if ($stockError) {
                return [false, null, $stockError];
            }
        }

        // Addons exist + still active.
        if (!empty($addOnIds)) {
            $liveAddons = AddOn::query()
                ->whereIn('id', $addOnIds)
                ->where('status', 1)
                ->get(['id', 'name', 'price'])
                ->keyBy('id');
            foreach ($addOnIds as $aid) {
                if (!$liveAddons->has($aid)) {
                    return [false, null, "An add-on for {$snapshotName} is no longer available."];
                }
            }
        }

        // Max cart qty — compare `(existing + reorder)` against the cap.
        // CartProvider::add sums quantities on an existing line; without
        // this check we'd pass pre-flight then throw at write time
        // (the catch surfaces it, but the message is generic).
        $maxCartQty = (int) ($item->maximum_cart_quantity ?? 0);
        if ($maxCartQty > 0 && ($qty + $existingCartQty) > $maxCartQty) {
            return [false, null, $existingCartQty > 0
                ? "You can add at most {$maxCartQty} of {$snapshotName} per order (you already have {$existingCartQty} in your cart)."
                : "You can add at most {$maxCartQty} of {$snapshotName} per order."];
        }

        // Live re-price. See liveLinePrice() — module-aware variation math.
        $price = $this->liveLinePrice($item, $variation, $addOnIds, $addOnQtys, $qty, $moduleType);

        // Pass-through payload matches CartProvider::add()'s schema. We
        // send the model alias rather than the FQCN because the cart
        // controller's validator gates on the alias.
        $payload = [
            'item_id'     => (int) $item->id,
            'model'       => $isCampaign ? 'ItemCampaign' : 'Item',
            'price'       => $price,
            'quantity'    => $qty,
            'variation'   => $variation,
            'add_on_ids'  => $addOnIds,
            'add_on_qtys' => $addOnQtys,
        ];
        return [true, $payload, null];
    }

    /**
     * Match historical variation choices against the live item's
     * variation JSON. Shape differs by module:
     *   - food: `food_variations` = [{name, values: [{label, optionPrice, ...}]}]
     *           historical = [{name, values: {label: string[]}}]
     *   - non-food: `variations` = [{type, price, stock}]
     *               historical = [{type, ...}]
     */
    private function validateVariation($item, array $variation, string $moduleType): ?string
    {
        if (empty($variation)) return null;

        if ($moduleType === 'food') {
            $live = $this->decodeJsonArray($item->food_variations ?? []);
            foreach ($variation as $sel) {
                $name   = (string) ($sel['name'] ?? '');
                $labels = $this->coerceLabels($sel['values']['label'] ?? null);
                if ($name === '') continue;

                $group = collect($live)->firstWhere('name', $name);
                if (!$group) {
                    return "An option you previously chose for {name} is no longer offered.";
                }
                $liveLabels = collect($group['values'] ?? [])->pluck('label')->map(fn ($l) => (string) $l)->all();
                foreach ($labels as $lbl) {
                    if (!in_array($lbl, $liveLabels, true)) {
                        return "An option you previously chose for {name} is no longer offered.";
                    }
                }
            }
            return null;
        }

        // Non-food
        $live = $this->decodeJsonArray($item->variations ?? []);
        $liveTypes = array_column($live, 'type');
        foreach ($variation as $sel) {
            $type = (string) ($sel['type'] ?? '');
            if ($type === '') continue;
            if (!in_array($type, $liveTypes, true)) {
                return "A variation you previously chose for {name} is no longer offered.";
            }
        }
        return null;
    }

    /**
     * Stock check. Non-variant orders look at item-level stock; variant
     * orders pull from the per-variation `stock` field. `hasStock` was
     * already gated by the caller.
     *
     * Module-aware: food variations live in `food_variations` (option
     * groups — no inherent stock), while non-food variations live in
     * `variations` (each entry carries its own stock). For food we
     * always fall through to item-level `stock` because option groups
     * don't have stock semantics. (Default config sets food.stock=false
     * anyway, but be defensive in case the admin flips it.)
     *
     * Compares `live_stock` against `(reorder_qty + existing_cart_qty)`
     * because CartProvider::add bumps an existing matching line by the
     * reorder qty — the final cart line has to fit within live stock.
     */
    private function validateStock(
        $item,
        array $variation,
        int $qty,
        string $name,
        int $existingCartQty = 0,
        string $moduleType = '',
    ): ?string {
        $needed = $qty + $existingCartQty;

        // Non-food: look up the chosen variant's per-variant stock.
        if ($moduleType !== 'food' && !empty($variation[0]['type'])) {
            $variant = (string) $variation[0]['type'];
            $live = $this->decodeJsonArray($item->variations ?? []);
            foreach ($live as $v) {
                if (($v['type'] ?? null) === $variant) {
                    $available = (int) ($v['stock'] ?? 0);
                    if ($available < $needed) {
                        return $this->stockMessage($name, $available, $existingCartQty);
                    }
                    return null;
                }
            }
            // Variant not present in live — validateVariation should have
            // caught this already; defensive null here.
            return null;
        }

        // Food, or non-food with no variant chosen → item-level stock.
        $stock = (int) ($item->stock ?? 0);
        if ($stock < $needed) {
            return $this->stockMessage($name, $stock, $existingCartQty);
        }
        return null;
    }

    private function stockMessage(string $name, int $available, int $existingCartQty): string
    {
        if ($available <= 0) {
            return "{$name} is out of stock.";
        }
        if ($existingCartQty > 0) {
            return "Only {$available} of {$name} left in stock (you already have {$existingCartQty} in your cart).";
        }
        return "Only {$available} of {$name} left in stock.";
    }

    /**
     * How many of this (item, variation, addons) tuple does the customer
     * already have in their cart? Mirrors CartProvider::findMatchingLine's
     * dedupe key (variation matched ignoring volatile price/stock fields).
     *
     * We can't call CartProvider::findMatchingLine directly — it's private.
     * Cheaper to duplicate the small bit of matching logic than to widen
     * the contract for one caller.
     */
    private function existingCartLineQty(
        $item,
        array $variation,
        array $addOnIds,
        array $addOnQtys,
        int $customerId,
        int $moduleId,
    ): int {
        $itemType = $item instanceof ItemCampaign ? ItemCampaign::class : Item::class;
        $needle = $this->variationMatchKey($variation);
        $wantAddOnIds  = array_values(array_map('intval', $addOnIds));
        $wantAddOnQtys = array_values(array_map('intval', $addOnQtys));

        $rows = Cart::query()
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->where('module_id', $moduleId)
            ->where('item_id', $item->id)
            ->whereIn('item_type', [$itemType, class_basename($itemType)])
            ->get(['variation', 'add_on_ids', 'add_on_qtys', 'quantity']);

        $total = 0;
        foreach ($rows as $row) {
            $rowVar       = $this->decodeJsonArray($row->variation);
            $rowAddOnIds  = array_values(array_map('intval', $this->decodeJsonArray($row->add_on_ids)));
            $rowAddOnQtys = array_values(array_map('intval', $this->decodeJsonArray($row->add_on_qtys)));

            if ($this->variationMatchKey($rowVar) === $needle
                && $rowAddOnIds  == $wantAddOnIds
                && $rowAddOnQtys == $wantAddOnQtys) {
                $total += (int) $row->quantity;
            }
        }
        return $total;
    }

    /**
     * Stable identity for a variation, ignoring volatile fields. Mirrors
     * CartProvider::variationMatchKey verbatim so existing-cart matching
     * stays consistent with the cart's own dedupe behavior.
     */
    private function variationMatchKey(array $variation): string
    {
        $normalized = array_map(static function ($entry) {
            if (!is_array($entry)) return $entry;
            $copy = $entry;
            unset($copy['price'], $copy['stock'], $copy['oldPrice'], $copy['discountPercent'], $copy['inStock']);
            return $copy;
        }, $variation);
        return json_encode($normalized) ?: '';
    }

    /**
     * Recompute the line price from live catalog data:
     *   non-food: matched variation.price REPLACES base item price
     *             (each variant carries its own price)
     *   food:     each chosen option's optionPrice ADDS to base
     *
     * Addons are summed per-line (NOT per-unit) to match the cart's
     * existing convention — see Resources/js/utils/cartPrice.js.
     *
     * Discounts intentionally not applied here — `PlaceNewOrder` re-
     * derives the canonical `order_amount` at place-order time from
     * the live cart, so any drift between our number and the final
     * order is corrected then. We just want a sane price visible in
     * the cart drawer / checkout summary.
     */
    private function liveLinePrice($item, array $variation, array $addOnIds, array $addOnQtys, int $qty, string $moduleType): float
    {
        $base = (float) ($item->price ?? 0);

        if (!empty($variation)) {
            if ($moduleType === 'food') {
                $live = $this->decodeJsonArray($item->food_variations ?? []);
                foreach ($variation as $sel) {
                    $name   = (string) ($sel['name'] ?? '');
                    $labels = $this->coerceLabels($sel['values']['label'] ?? null);
                    if ($name === '') continue;
                    foreach ($live as $group) {
                        if (($group['name'] ?? null) !== $name) continue;
                        foreach (($group['values'] ?? []) as $val) {
                            if (in_array((string) ($val['label'] ?? ''), $labels, true)) {
                                $base += (float) ($val['optionPrice'] ?? 0);
                            }
                        }
                    }
                }
            } else {
                // Non-food: variation REPLACES base.
                $live = $this->decodeJsonArray($item->variations ?? []);
                $type = (string) ($variation[0]['type'] ?? '');
                foreach ($live as $v) {
                    if (($v['type'] ?? null) === $type) {
                        $base = (float) ($v['price'] ?? $base);
                        break;
                    }
                }
            }
        }

        $addonExtra = 0.0;
        if (!empty($addOnIds)) {
            $liveAddons = AddOn::query()
                ->whereIn('id', $addOnIds)
                ->where('status', 1)
                ->get(['id', 'price'])
                ->keyBy('id');
            foreach ($addOnIds as $i => $aid) {
                $aq = (int) ($addOnQtys[$i] ?? 0);
                if (isset($liveAddons[$aid])) {
                    $addonExtra += (float) $liveAddons[$aid]->price * $aq;
                }
            }
        }

        return round($base * $qty + $addonExtra, (int) (config('round_up_to_digit') ?? 2));
    }

    /**
     * Convert the order-details snapshot's food-variation shape into the
     * cart's expected shape. The host's two surfaces stored variation
     * differently:
     *   carts:         [{name, values: {label: ["small", "large"]}}]
     *   order_details: [{name, values: [{label, optionPrice}, ...]}]
     *
     * The cart's renderer (Helpers::cart_product_data_formatting) reads
     * `values.label` directly — writing the snapshot shape verbatim
     * crashes with "Undefined array key 'label'".
     */
    private function normalizeFoodVariation(array $orderVariation): array
    {
        $out = [];
        foreach ($orderVariation as $group) {
            if (!is_array($group)) continue;

            $labels = [];
            $values = $group['values'] ?? null;
            if (is_array($values)) {
                if (array_key_exists('label', $values)) {
                    // Already cart-shape — possible if the host already
                    // normalized somewhere upstream. Pass through.
                    $labels = is_array($values['label']) ? $values['label'] : [$values['label']];
                } else {
                    // Order-details shape: a list of {label, optionPrice}.
                    foreach ($values as $v) {
                        if (is_array($v) && isset($v['label'])) {
                            $labels[] = $v['label'];
                        }
                    }
                }
            }

            // Preserve every field on the group (name/type/min/max/
            // required/…) so the cart row carries the same metadata
            // the user originally picked. Only `values` gets rewritten.
            $normalized = $group;
            $normalized['values'] = [
                'label' => array_values(array_map(static fn ($x) => (string) $x, $labels)),
            ];
            $out[] = $normalized;
        }
        return $out;
    }

    private function snapshotItemName(OrderDetail $d): string
    {
        $details = $this->decodeJsonArray($d->item_details ?? null);
        $name = $details['name'] ?? null;
        return is_string($name) && $name !== '' ? $name : 'Item';
    }

    private function coerceLabels($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map(static fn ($x) => (string) $x, $raw));
        }
        if (is_string($raw) && $raw !== '') {
            return [$raw];
        }
        return [];
    }

    private function decodeJsonArray($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
