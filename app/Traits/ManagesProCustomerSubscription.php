<?php

namespace App\Traits;

use App\CentralLogics\Helpers;
use App\Models\DataSetting;
use App\Models\Module;
use App\Models\NotificationMessage;
use App\Models\ProCustomerBenefitSetting;
use App\Models\OrderProDiscount;
use App\Models\ProCustomerSubscription;
use App\Models\ProCustomerSubscriptionPlan;
use App\Models\ProCustomerTransaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

trait ManagesProCustomerSubscription
{
    public function getProCustomerOffer($userId, bool $incrementCount = false, bool $showOnlyActivePlan = true, ?string $moduleType = null): array
    {
        $fail = static fn(string $message, ?array $planDetails = null) => [
            'status' => false, 'message' => $message, 'benefit' => null, 'plan_details' => $planDetails,
        ];

        if (!$userId) return $fail('no_user');

        $user = User::find($userId);
        if (!$user) return $fail('no_user');


        $subscription = ProCustomerSubscription::where('user_id', $user->id)
            ->when($showOnlyActivePlan, function ($query) {
                $query->where('status', 'active')
                    ->where(fn($q) => $q->whereNull('end_at')->orWhereDate('end_at', '>=', now()));
            })
            ->latest('id')
            ->first();

        if (!$subscription) return $fail('no_active_subscription');

        $rows = DataSetting::where('type', 'pro_customer_benefits')
            ->pluck('value', 'key')
            ->toArray();

        $type = match (true) {
            (int) ($rows['discount_status'] ?? 0) === 1     => 'discount',
            (int) ($rows['delivery_fee_status'] ?? 0) === 1 => 'delivery_fee',
            (int) ($rows['coupon_status'] ?? 0) === 1       => 'coupon',
            default                                          => null,
        };

        if (!$type) return $fail('no_benefit_enabled');

        $latestTransaction = null;
        if (!$showOnlyActivePlan || $incrementCount) {
            $latestTransaction = ProCustomerTransaction::where('user_id', $user->id)
                ->where('subscription_id', $subscription->id)
                ->latest('id')
                ->first();
        }

        $planDetails = null;
        if (!$showOnlyActivePlan) {
            $totals = OrderProDiscount::where('user_id', $user->id)
                ->where('transaction_id', $latestTransaction?->id)
                ->selectRaw('COALESCE(SUM(amount_saved), 0) + COALESCE(SUM(delivery_fee_reduction_amount), 0) as total_saved')
                ->selectRaw('COUNT(*) as total_orders')
                ->first();

            $now   = now();
            $endAt = $subscription->end_at;

            $planDetails = [
                'plan_name'      => $subscription->plan_name,
                'total_saved'    => (float) ($totals->total_saved ?? 0),
                'total_orders'   => (int) ($totals->total_orders ?? 0),
                'start_at'       => $subscription->start_at?->format('Y-m-d'),
                'end_at'         => $endAt ? $endAt->format('Y-m-d') : null,
                'days_remaining' => $endAt ? max(0, $now->startOfDay()->diffInDays($endAt->copy()->startOfDay(), false)) : null,
                'paid_by'        => $latestTransaction?->payment_method,
            ];
        }

        $benefit = [
            'type'            => $type,
            'subscription_id' => $subscription->id,
            'plan_id'         => $subscription->plan_id,
        ];

        if ($type === 'discount') {
            if ($moduleType && !in_array($moduleType, ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES, true)) {
                return $fail('no_benefit_for_module', planDetails: $planDetails);
            }
            $setupMode = $rows['discount_setup_mode'] ?? 'central';
            $lookupKey = ($setupMode === 'individual' && $moduleType) ? $moduleType : null;
            $cfg       = ProCustomerBenefitSetting::getSettings('discount', $lookupKey);

            $benefit += [
                'percentage'       => isset($cfg['percentage']) && $cfg['percentage'] !== null ? (float) $cfg['percentage'] : null,
                'max_amount'       => isset($cfg['max_amount']) && $cfg['max_amount'] !== null ? (float) $cfg['max_amount'] : null,
                'min_order_status' => (int) ($cfg['min_order_status'] ?? 0),
                'min_order_amount' => isset($cfg['min_order_amount']) && $cfg['min_order_amount'] !== null ? (float) $cfg['min_order_amount'] : null,
            ];
        } elseif ($type === 'delivery_fee') {
            if ($moduleType && !in_array($moduleType, ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES, true)) {
                return $fail('no_benefit_for_module', planDetails: $planDetails);
            }
            $cfg = $moduleType ? ProCustomerBenefitSetting::getSettings('delivery_fee', $moduleType) : [];

            $benefit += [
                'offer_type'                 => $cfg['offer_type'] ?? 'full_free',
                'min_order_status'           => (int) ($cfg['min_order_status'] ?? 0),
                'min_order_amount'           => isset($cfg['min_order_amount']) && $cfg['min_order_amount'] !== null ? (float) $cfg['min_order_amount'] : null,
                'charge_discount_percentage' => isset($cfg['charge_discount']) && $cfg['charge_discount'] !== null ? (float) $cfg['charge_discount'] : null,
            ];
        } elseif ($type === 'coupon') {
            if($moduleType && $moduleType == 'parcel') {
                return $fail('no_benefit_for_module', planDetails: $planDetails);
            }
        }

        if ($incrementCount) {
            $latestTransaction?->increment('order_count');
        }

        // Effective validity is date-inclusive through the whole end_at day, matching the
        // expiry cron (whereDate end_at < now). This keeps the reported status in agreement
        // with order placement even when the expiry cron has not yet flipped the column.
        $isActive = $subscription->status === 'active'
            && ($subscription->end_at === null
                || $subscription->end_at->copy()->startOfDay()->gte(now()->startOfDay()));

        return [
            'status'       => $isActive,
            'message'      => $subscription->status,
            'benefit'      => $benefit,
            'plan_details' => $planDetails,
        ];
    }

    public function applyProCustomerDiscount(?int $userId, float $subtotal, float $totalPrice, ?string $moduleType = null): array
    {
        $noop = fn(string $message) => [
            'offer'       => ['status' => false, 'message' => $message, 'benefit' => null, 'plan_details' => null],
            'discount'    => 0.0,
            'total_price' => (float) $totalPrice,
        ];

        if ($moduleType !== null) {
            if (!in_array($moduleType, ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES, true)) {
                return $noop('no_benefit_for_module');
            }
            if ($moduleType === 'ride-share' && !addon_published_status('RideShare')) {
                return $noop('module_addon_unpublished');
            }
            if ($moduleType === 'rental' && !addon_published_status('Rental')) {
                return $noop('module_addon_unpublished');
            }
            if ($moduleType === 'service' && !addon_published_status('Service')) {
                return $noop('module_addon_unpublished');
            }
        }

        $offer    = $this->getProCustomerOffer($userId, false, true, $moduleType);
        $discount = $this->computeProDiscountAmount($offer, $subtotal, $totalPrice);

        return [
            'offer'       => $offer,
            'discount'    => (float) $discount,
            'total_price' => (float) max($totalPrice - $discount, 0),
        ];
    }

    public function computeProDiscountAmount(array $proOffer, float $subtotal, float $totalPrice): float
    {
        if (!($proOffer['status'] ?? false) || ($proOffer['benefit']['type'] ?? null) !== 'discount') {
            return 0.0;
        }

        $cfg   = $proOffer['benefit'];
        $minOk = $cfg['min_order_status'] !== 1
            || ($cfg['min_order_amount'] !== null && $subtotal >= $cfg['min_order_amount']);

        if (!$minOk || empty($cfg['percentage'])) {
            return 0.0;
        }

        $discount = $totalPrice * ((float) $cfg['percentage'] / 100);
        if ($cfg['max_amount'] !== null) {
            $discount = min($discount, (float) $cfg['max_amount']);
        }

        return (float) $discount;
    }

    public function applyProCustomerDeliveryFee(
        array $proOffer,
        float $deliveryCharge,
        float $totalPrice,
        ?string $freeDeliveryBy = null,
        ?string $moduleType = null,
    ): array {
        $passthrough = [
            'delivery_charge'  => $deliveryCharge,
            'free_delivery_by' => $freeDeliveryBy,
            'savings'          => 0.0,
        ];

        if ($moduleType !== null && !in_array($moduleType, ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES, true)) {
            return $passthrough;
        }

        $applies = ($proOffer['status'] ?? false)
            && ($proOffer['benefit']['type'] ?? null) === 'delivery_fee'
            && $deliveryCharge > 0;

        if (!$applies) {
            return $passthrough;
        }

        $cfg     = $proOffer['benefit'];
        $savings = 0.0;

        if ($cfg['offer_type'] === 'full_free') {
            $minOk = $cfg['min_order_status'] !== 1
                || ($cfg['min_order_amount'] !== null && $totalPrice >= $cfg['min_order_amount']);
            if ($minOk) {
                $savings        = $deliveryCharge;
                $deliveryCharge = 0.0;
                $freeDeliveryBy = 'admin';
            }
        } elseif ($cfg['offer_type'] === 'partial_free' && $cfg['charge_discount_percentage']) {
            $reduction      = $deliveryCharge * ((float) $cfg['charge_discount_percentage'] / 100);
            $savings        = (float) $reduction;
            $deliveryCharge = max(0, $deliveryCharge - $reduction);
            $freeDeliveryBy = 'admin';
        }

        return [
            'delivery_charge'  => (float) $deliveryCharge,
            'free_delivery_by' => $freeDeliveryBy,
            'savings'          => $savings,
        ];
    }

    public function recordOrderProDiscount(
        int $userId,
        array $proOffer,
        float $amountSaved,
        ?int $orderId = null,
        ?int $tripId = null,
        ?int $rideRequestId = null,
        ?int $serviceBookingId = null,
        ?float $originalDeliveryCharge = null,
        ?string $moduleType = null,
    ): ?OrderProDiscount {
        if (!($proOffer['status'] ?? false)) {
            return null;
        }

        if ($orderId === null && $tripId === null && $rideRequestId === null && $serviceBookingId === null) {
            return null;
        }

        $benefit = $proOffer['benefit'];
        $type    = $benefit['type'];

        if ($amountSaved <= 0 && $type !== 'coupon') {
            return null;
        }

        if ($moduleType !== null) {
            $allowList = match ($type) {
                'discount'     => ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES,
                'delivery_fee' => ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES,
                default        => null,
            };
            if ($allowList !== null && !in_array($moduleType, $allowList, true)) {
                return null;
            }
        }

        $transaction_id = ProCustomerTransaction::where('user_id', $userId)
            ->where('subscription_id', $benefit['subscription_id'])
            ->latest('id')
            ->value('id');

        return OrderProDiscount::create([
            'order_id'                            => $orderId,
            'trip_id'                             => $tripId,
            'ride_request_id'                     => $rideRequestId,
            'service_booking_id'                  => $serviceBookingId,
            'user_id'                             => $userId,
            'subscription_id'                     => $benefit['subscription_id'] ?? null,
            'plan_id'                             => $benefit['plan_id'] ?? null,
            'benefit_type'                        => $type,
            'transaction_id'                      => $transaction_id,
            'amount_saved'                        => $type === 'discount' ? $amountSaved : 0,
            'discount_percentage'                 => $type === 'discount' ? ($benefit['percentage'] ?? null) : null,
            'max_discount_amount'                 => $type === 'discount' ? ($benefit['max_amount'] ?? null) : null,
            'min_order_amount'                    => in_array($type, ['discount', 'delivery_fee'], true)
                ? ($benefit['min_order_amount'] ?? null)
                : null,
            'delivery_offer_type'                 => $type === 'delivery_fee' ? ($benefit['offer_type'] ?? null) : null,
            'delivery_charge_discount_percentage' => $type === 'delivery_fee' ? ($benefit['charge_discount_percentage'] ?? null) : null,
            'delivery_fee_reduction_amount'       => $type === 'delivery_fee' ? $amountSaved : null,
            'original_delivery_charge'            => $type === 'delivery_fee' ? $originalDeliveryCharge : null,
        ]);
    }

    public function recomputeOrderProDiscountOnEdit(
        \App\Models\Order $order,
        float $subtotal,
        float $totalPrice,
        ?string $moduleType = null,
        ?float $deliveryCharge = null,
    ): array {
        $userId         = $order->user_id ? (int) $order->user_id : null;
        $currentDelivery = $deliveryCharge !== null
            ? (float) $deliveryCharge
            : (float) ($order->delivery_charge ?? 0);
        $existingFreeBy = $order->free_delivery_by ?? null;

        $passthrough = [
            'discount'         => 0.0,
            'total_price'      => (float) $totalPrice,
            'delivery_charge'  => $currentDelivery,
            'delivery_savings' => 0.0,
            'free_delivery_by' => $existingFreeBy,
            'pro_offer'        => ['status' => false, 'benefit' => null],
        ];

        OrderProDiscount::where('order_id', $order->id)->delete();

        if (!$userId) {
            return $passthrough;
        }

        $proApply = $this->applyProCustomerDiscount(
            $userId,
            $subtotal,
            $totalPrice,
            $moduleType,
        );

        $proOffer    = $proApply['offer'];
        $proDiscount = (float) $proApply['discount'];
        $newTotal    = (float) $proApply['total_price'];

        $deliverySavings = 0.0;
        $newDelivery     = $currentDelivery;
        $newFreeBy       = $existingFreeBy;
        if ($deliveryCharge !== null) {
            $proDelivery = $this->applyProCustomerDeliveryFee(
                $proOffer,
                $currentDelivery,
                $newTotal,
                $existingFreeBy,
                $moduleType,
            );
            $deliverySavings = (float) $proDelivery['savings'];
            $newDelivery     = (float) $proDelivery['delivery_charge'];
            $newFreeBy       = $proDelivery['free_delivery_by'];
        }

        if (($proOffer['status'] ?? false)) {
            if ($proDiscount > 0) {
                $this->recordOrderProDiscount(
                    orderId: (int) $order->id,
                    userId: $userId,
                    proOffer: $proOffer,
                    amountSaved: $proDiscount,
                    originalDeliveryCharge: $order->original_delivery_charge ?? null,
                    moduleType: $moduleType,
                );
            } elseif ($deliverySavings > 0) {
                $this->recordOrderProDiscount(
                    orderId: (int) $order->id,
                    userId: $userId,
                    proOffer: $proOffer,
                    amountSaved: $deliverySavings,
                    originalDeliveryCharge: $order->original_delivery_charge ?? $currentDelivery,
                    moduleType: $moduleType,
                );
            }
        }

        return [
            'discount'         => $proDiscount,
            'total_price'      => $newTotal,
            'delivery_charge'  => $newDelivery,
            'delivery_savings' => $deliverySavings,
            'free_delivery_by' => $newFreeBy,
            'pro_offer'        => $proOffer,
        ];
    }

    public function applyProCustomerPlan(User $user, ProCustomerSubscriptionPlan $plan, array $payment = [], string $mode = 'start'): ProCustomerSubscription
    {
        $rawPlanName = ProCustomerSubscriptionPlan::withoutGlobalScope('translate')
            ->where('id', $plan->id)
            ->value('plan_name');

        $isFreeTrial        = $plan->plan_type === 'free_trial';
        $duration           = (int) $plan->duration;
        $price              = (float) $plan->price;
        $paymentMethodInput = $payment['payment_method'] ?? null;

        if ($isFreeTrial && $this->hasUsedFreeTrial($user->id)) {
            throw new RuntimeException('free_trial_already_used');
        }

        if (!$isFreeTrial && $paymentMethodInput === 'wallet'
            && (float) $user->wallet_balance < $price) {
            throw new RuntimeException('insufficient_wallet_balance');
        }

        $subscription = DB::transaction(function () use ($user, $plan, $rawPlanName, $isFreeTrial, $duration, $price, $paymentMethodInput, $payment, $mode) {
            $subscription = ProCustomerSubscription::where('user_id', $user->id)
                ->latest('id')
                ->first()
                ?? new ProCustomerSubscription(['user_id' => $user->id]);

            $isNew = !$subscription->exists;
            $now   = now();

            $extending = $mode === 'renew'
                && !$isNew
                && $subscription->status !== 'canceled'
                && $subscription->end_at
                // Date-inclusive: a subscription is still valid through its whole end_at day,
                // so renewing any time on the last day extends rather than restarts it.
                && $subscription->end_at->copy()->startOfDay()->gte($now->copy()->startOfDay());

            if ($extending) {
                $startAt = $subscription->start_at ?? $now;
                $endAt   = $subscription->end_at->copy()->addDays($duration);
            } else {
                $startAt = $now;
                $endAt   = (clone $now)->addDays($duration);
            }

            $subscription->fill([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'plan_name'  => $rawPlanName,
                'plan_type'  => $plan->plan_type,
                'plan_price' => $isFreeTrial ? 0 : $price,
                'start_at'   => $startAt,
                'end_at'     => $endAt,
                'status'     => 'active',
            ]);
            if ($isNew) {
                $subscription->auto_renew = 0;
            }
            $subscription->save();

            $paymentMethod = $payment['payment_method'] ?? ($isFreeTrial ? 'free_trial' : null);
            $paymentStatus = $payment['payment_status'] ?? (($isFreeTrial || $paymentMethod) ? 'success' : 'pending');
            $paidAt        = $payment['paid_at'] ?? ($paymentStatus === 'success' ? $now : null);

            $walletTxnUuid = (!$isFreeTrial && $paymentMethodInput === 'wallet' && $price > 0)
                ? (string) Str::uuid()
                : null;

            $transaction = ProCustomerTransaction::create([
                'user_id'               => $user->id,
                'subscription_id'       => $subscription->id,
                'plan_id'               => $plan->id,
                'transaction_reference' => $payment['transaction_reference'] ?? $walletTxnUuid,
                'plan_name'             => $rawPlanName,
                'plan_type'             => $plan->plan_type,
                'plan_price'            => $isFreeTrial ? 0 : $price,
                'amount'                => $isFreeTrial ? 0 : $price,
                'payment_method'        => $paymentMethod,
                'payment_status'        => $paymentStatus,
                'start_at'              => $startAt,
                'end_at'                => $endAt,
                'order_count'           => 0,
                'paid_at'               => $paidAt,
            ]);

            if ($walletTxnUuid !== null) {
                $newBalance = (float) $user->wallet_balance - $price;

                $walletTxn                   = new WalletTransaction();
                $walletTxn->user_id          = $user->id;
                $walletTxn->transaction_id   = $walletTxnUuid;
                $walletTxn->reference        = 'pro_subscription_' . $transaction->id;
                $walletTxn->transaction_type = 'pro_subscription';
                $walletTxn->debit            = $price;
                $walletTxn->credit           = 0;
                $walletTxn->admin_bonus      = 0;
                $walletTxn->balance          = $newBalance;
                $walletTxn->created_at       = $now;
                $walletTxn->updated_at       = $now;
                $walletTxn->save();

                $user->wallet_balance = $newBalance;
            }

            $user->pro_status = 1;
            $user->save();

            return $subscription;
        });

        $this->sendUserPushNotification(
            user: $user,
            messageKey: 'subscription_activated',
            title: $mode === 'renew'
                ? translate('messages.Subscription Renewed')
                : translate('messages.Subscription Activated'),
            type: 'customer_subscription_activated',
            dataId: $subscription->id,
        );

        return $subscription;
    }

    private function hasUsedFreeTrial(int $userId): bool
    {
        return ProCustomerTransaction::where('user_id', $userId)
            ->where('plan_type', 'free_trial')
            ->exists();
    }

    public function proActiveModuleTypes(): array
    {
        return Module::where('status', 1)->distinct()->pluck('module_type')->all();
    }

    public function proAddonEnabledDiscountModules(): array
    {
        return array_values(array_filter(
            ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES,
            fn ($mod) => match ($mod) {
                'ride-share' => (bool) addon_published_status('RideShare'),
                'rental'     => (bool) addon_published_status('Rental'),
                'service'    => (bool) addon_published_status('Service'),
                default      => true,
            }
        ));
    }

    public function proVisibleDiscountModules(): array
    {
        $active = $this->proActiveModuleTypes();

        return array_values(array_filter(
            $this->proAddonEnabledDiscountModules(),
            fn ($mod) => in_array($mod, $active, true)
        ));
    }

    public function proVisibleDeliveryFeeModules(): array
    {
        $active = $this->proActiveModuleTypes();

        return array_values(array_filter(
            ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES,
            fn ($mod) => in_array($mod, $active, true)
        ));
    }

    public function expireDueSubscriptions(): void
    {
        static $running = false;
        if ($running) return;
        $running = true;

        try {
            $this->doExpireDueSubscriptions();
        } finally {
            $running = false;
        }
    }

    private function doExpireDueSubscriptions(): void
    {
        $now = now();

        $subscriptions = ProCustomerSubscription::query()
            ->select(['id', 'user_id'])
            ->with(['user:id,f_name,l_name,cm_firebase_token,current_language_key'])
            ->where('status', 'active')
            ->whereNotNull('end_at')
            ->whereDate('end_at', '<', $now)
            ->lazy();

        foreach ($subscriptions as $subscription) {
            DB::transaction(function () use ($subscription) {
                $subscription->status     = 'expired';
                $subscription->auto_renew = 0;
                $subscription->save();
                User::where('id', $subscription->user_id)->update(['pro_status' => 0]);
            });

            if ($subscription->user) {
                $this->sendUserPushNotification(
                    user: $subscription->user,
                    messageKey: 'subscription_expired',
                    title: translate('messages.Subscription Expire Message'),
                    type: 'subscription_expired',
                    dataId: $subscription->id,
                );
            }
        }

        DataSetting::updateOrCreate(
            ['key' => 'subscription_expiry_last_run_at', 'type' => 'notification_settings'],
            ['value' => $now->toDateTimeString()],
        );
    }

    public function cancelProCustomerSubscription(ProCustomerSubscription $subscription): void
    {
        $user = $subscription->user ?? User::find($subscription->user_id);

        DB::transaction(function () use ($subscription, $user) {
            $subscription->status     = 'canceled';
            $subscription->auto_renew = 0;
            $subscription->save();

            if ($user) {
                $user->pro_status = 0;
                $user->save();
            }
        });

        if ($user) {
            $this->sendUserPushNotification(
                user: $user,
                messageKey: 'subscription_canceled',
                title: translate('messages.Subscription Canceled'),
                type: 'subscription_canceled',
                dataId: $subscription->id,
            );
        }
    }

    public function sendCustomerSubscriptionExpireNotification(): void
    {
        $beforeTime = (int) (DataSetting::where(['key' => 'subscription_reminder_before_time', 'type' => 'notification_settings'])->first()?->value ?? 0);
        $beforeUnit = DataSetting::where(['key' => 'subscription_reminder_before', 'type' => 'notification_settings'])->first()?->value ?? 'days';

        if ($beforeTime <= 0) return;

        $isEnabled = NotificationMessage::where('key', 'subscription_expire_reminder')
            ->where('status', 1)
            ->exists();
        if (!$isEnabled) return;

        $target = match ($beforeUnit) {
            'hour'  => now()->addHours($beforeTime),
            'min'   => now()->addMinutes($beforeTime),
            default => now()->addDays($beforeTime),
        };

        [$windowStart, $windowEnd] = $beforeUnit === 'hour'
            ? [$target->copy()->startOfHour(), $target->copy()->endOfHour()]
            : [$target->copy()->startOfDay(), $target->copy()->endOfDay()];

        $subscriptions = ProCustomerSubscription::query()
            ->select(['id', 'user_id'])
            ->with(['user:id,f_name,l_name,cm_firebase_token,current_language_key'])
            ->where('status', 'active')
            ->whereBetween('end_at', [$windowStart, $windowEnd])
            ->whereHas('user', fn($q) => $q
                ->whereNotNull('cm_firebase_token')
                ->where('cm_firebase_token', '!=', '@'))
            ->lazy();

        foreach ($subscriptions as $subscription) {
            if (!$subscription->user) continue;
            $this->sendUserPushNotification(
                user: $subscription->user,
                messageKey: 'subscription_expire_reminder',
                title: translate('messages.Subscription Expire Reminder'),
                type: 'customer_subscription_expire_reminder',
                dataId: $subscription->id,
            );
        }
    }

    private function sendUserPushNotification(
        User $user,
        string $messageKey,
        string $title,
        string $type,
        int|string|null $dataId = null,
    ): bool {
        if (!$user->cm_firebase_token || $user->cm_firebase_token === '@') {
            return false;
        }

        $notificationMessage = NotificationMessage::with(['translations' => function ($query) use ($user) {
            $query->where('locale', $user->current_language_key ?: 'en');
        }])
            ->where('key', $messageKey)
            ->where('status', 1)
            ->first();

        if (!$notificationMessage) return false;

        $description = Helpers::text_variable_data_format(
            value: $notificationMessage->message,
            user_name: trim($user->f_name . ' ' . $user->l_name),
        );

        $data = [
            'title'       => $title,
            'description' => $description,
            'image'       => '',
            'type'        => $type,
            'data_id'     => (string) $dataId,
        ];

        try {
            Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);
            UserNotification::create([
                'data'    => json_encode($data),
                'user_id' => $user->id,
            ]);
            return true;
        } catch (\Exception $e) {
            info($e->getMessage());
            return false;
        }
    }
}
