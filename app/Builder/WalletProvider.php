<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletPayment;
use App\Models\WalletTransaction;
use App\Traits\Payment;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Builder\Contracts\PaymentMethodProvider;
use Modules\Builder\Contracts\WalletProvider as WalletProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\WalletSummaryDTO;

class WalletProvider implements WalletProviderContract
{
    public function __construct(
        private PaymentMethodProvider $paymentMethodProvider,
    ) {
    }

    private const TYPE_LABELS = [
        'add_fund'                              => 'Add Fund',
        'add_fund_by_admin'                     => 'Admin Bonus',
        'order_place'                           => 'Order Place',
        'order_refund'                          => 'Order Refund',
        'loyalty_point'                         => 'Loyalty Point',
        'partial_payment'                       => 'Partial Payment',
        'CashBack'                              => 'Cashback',
        'referrer'                              => 'Referral',
        'trip_booking'                          => 'Trip Booking',
        'ride_booking'                          => 'Ride Booking',
        'wallet_transfer_mart_to_drivemond'     => 'Transfer Out',
        'wallet_transfer_mart_from_drivemond'   => 'Transfer In',
    ];

    public function summary(int $customerId): WalletSummaryDTO
    {
        if ($customerId <= 0) {
            return WalletSummaryDTO::fromArray([
                'balance'          => 0.0,
                'loyaltyPoint'     => 0.0,
                'joinedDaysAgo'    => 0,
                'totalOrders'      => 0,
                'transactionTypes' => [],
            ]);
        }

        $user = User::query()
            ->select('id', 'wallet_balance', 'loyalty_point', 'created_at')
            ->find($customerId);

        if (!$user) {
            return WalletSummaryDTO::fromArray([
                'balance'          => 0.0,
                'loyaltyPoint'     => 0.0,
                'joinedDaysAgo'    => 0,
                'totalOrders'      => 0,
                'transactionTypes' => [],
            ]);
        }

        $totalOrders = Order::query()
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->count();

        $joinedDaysAgo = $user->created_at
            ? max(0, (int) Carbon::parse($user->created_at)->diffInDays(now()))
            : 0;

        return WalletSummaryDTO::fromArray([
            'balance'          => (float) $user->wallet_balance,
            'loyaltyPoint'     => (float) $user->loyalty_point,
            'joinedDaysAgo'    => $joinedDaysAgo,
            'totalOrders'      => $totalOrders,
            'transactionTypes' => $this->distinctTransactionTypes($customerId),
        ]);
    }

    public function transactions(int $customerId, ?string $type, int $perPage, int $page): PaginatedResult
    {
        if ($customerId <= 0) {
            return PaginatedResult::fromPaginator(new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $perPage,
                currentPage: $page,
                options: ['pageName' => 'walletPage'],
            ));
        }

        $query = WalletTransaction::query()
            ->where('user_id', $customerId);

        $filterSlug = $this->normaliseType($type);
        if ($filterSlug !== null) {
            $query->where('transaction_type', $filterSlug);
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page, pageName: 'walletPage')
            ->through(fn (WalletTransaction $txn) => $this->mapTransaction($txn));

        return PaginatedResult::fromPaginator($paginator);
    }

    private function distinctTransactionTypes(int $customerId): array
    {
        $slugs = WalletTransaction::query()
            ->where('user_id', $customerId)
            ->whereNotNull('transaction_type')
            ->distinct()
            ->pluck('transaction_type')
            ->all();

        $rows = [];
        foreach ($slugs as $slug) {
            $rows[] = [
                'slug'  => (string) $slug,
                'label' => $this->labelFor((string) $slug),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $rows;
    }

    private function mapTransaction(WalletTransaction $txn): array
    {
        $credit    = (float) $txn->credit;
        $debit     = (float) $txn->debit;
        $direction = $credit >= $debit ? 'credit' : 'debit';
        $amount    = $direction === 'credit' ? $credit : $debit;
        $slug      = (string) ($txn->transaction_type ?? '');

        return [
            'id'            => (int) $txn->id,
            'transactionId' => (string) $txn->transaction_id,
            'type'          => $slug,
            'typeLabel'     => $this->labelFor($slug),
            'credit'        => $credit,
            'debit'         => $debit,
            'amount'        => $amount,
            'direction'     => $direction,
            'balance'       => (float) $txn->balance,
            'reference'     => $txn->reference !== null ? (string) $txn->reference : null,
            'date'          => $txn->created_at
                ? Carbon::parse($txn->created_at)->format('d M Y, h:iA')
                : '',
        ];
    }

    private function normaliseType(?string $type): ?string
    {
        if ($type === null) return null;
        $trimmed = trim($type);
        if ($trimmed === '' || strtolower($trimmed) === 'all') return null;

        return array_key_exists($trimmed, self::TYPE_LABELS) ? $trimmed : $trimmed;
    }

    private function labelFor(string $slug): string
    {
        if (isset(self::TYPE_LABELS[$slug])) {
            return self::TYPE_LABELS[$slug];
        }
        $clean = str_replace('_', ' ', $slug);
        return ucwords($clean);
    }

    public function initiateAddFund(
        int $customerId,
        float $amount,
        string $methodKey,
        string $paymentPlatform = 'web',
    ): array {
        if ($customerId <= 0) {
            return $this->addFundError('auth', 'Please sign in to add funds.');
        }
        if ($amount <= 0) {
            return $this->addFundError('amount', 'Please enter a valid amount greater than zero.');
        }

        $digital = Helpers::get_business_settings('digital_payment');
        if (!\is_array($digital) || (int) ($digital['status'] ?? 0) !== 1) {
            return $this->addFundError('digital_payment_disabled', 'Digital payment is currently unavailable.');
        }
        if ((int) Helpers::get_business_settings('wallet_status') !== 1) {
            return $this->addFundError('wallet_disabled', 'Wallet is currently disabled.');
        }
        if ((int) Helpers::get_business_settings('add_fund_status') !== 1) {
            return $this->addFundError('add_fund_disabled', 'Add fund is currently disabled.');
        }

        $methodKey = trim($methodKey);
        if ($methodKey === '') {
            return $this->addFundError('method', 'Please choose a payment method.');
        }
        $allowedKeys = array_column(
            $this->paymentMethodProvider->digitalPaymentMethods(),
            'key',
        );
        if (!\in_array($methodKey, $allowedKeys, true)) {
            return $this->addFundError('method', 'Selected payment method is not available.');
        }

        $customer = User::query()->find($customerId);
        if (!$customer) {
            return $this->addFundError('auth', 'Customer record could not be loaded.');
        }

        try {
            $walletPayment = new WalletPayment();
            $walletPayment->user_id        = $customer->id;
            $walletPayment->amount         = $amount;
            $walletPayment->payment_status = 'pending';
            $walletPayment->payment_method = $methodKey;
            $walletPayment->save();
        } catch (\Throwable $e) {
            return $this->addFundError('gateway_error', 'Could not start the transaction. Please try again.');
        }

        try {
            $callback = \url(\route('storefront.wallet.payment_callback', [
                'walletPaymentId' => $walletPayment->id,
            ], absolute: false));

            $payer = new Payer(
                trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')),
                (string) ($customer->email ?? ''),
                (string) ($customer->phone ?? ''),
                '',
            );

            $currency = (string) (BusinessSetting::query()->where('key', 'currency')->value('value') ?? 'USD');
            $logo     = BusinessSetting::query()->where('key', 'logo')->first();
            $additionalData = [
                'business_name' => (string) (BusinessSetting::query()->where('key', 'business_name')->value('value') ?? ''),
                'business_logo' => Helpers::get_full_url(
                    'business',
                    $logo?->value,
                    \is_array($logo?->storage ?? null) ? ($logo->storage[0]['value'] ?? 'public') : 'public',
                ),
            ];

            $paymentInfo = new PaymentInfo(
                success_hook:           'wallet_success',
                failure_hook:           'wallet_failed',
                currency_code:          $currency,
                payment_method:         $methodKey,
                payment_platform:       $paymentPlatform,
                payer_id:               $customer->id,
                receiver_id:            '100',
                additional_data:        $additionalData,
                payment_amount:         $amount,
                external_redirect_link: $callback,
                attribute:              'wallet_payments',
                attribute_id:           $walletPayment->id,
            );

            $receiver = new Receiver('receiver_name', 'example.png');

            $link = Payment::generate_link($payer, $paymentInfo, $receiver);
            $redirectUrl = \is_string($link) ? $link : null;
            if ($redirectUrl === null || $redirectUrl === '') {
                return $this->addFundError('gateway_error', 'Could not start the transaction. Please try again.');
            }

            return [
                'success'         => true,
                'walletPaymentId' => (int) $walletPayment->id,
                'paymentRedirect' => $redirectUrl,
                'errors'          => [],
            ];
        } catch (\Throwable $e) {
            return $this->addFundError('gateway_error', 'Could not start the transaction. Please try again.');
        }
    }

    public function addFundResult(int $walletPaymentId): ?array
    {
        $payment = WalletPayment::query()
            ->select(['id', 'amount', 'payment_method'])
            ->where('id', $walletPaymentId)
            ->first();

        if (!$payment) {
            return null;
        }

        return [
            'walletPaymentId' => (int) $payment->id,
            'amount'          => (float) $payment->amount,
            'method'          => (string) ($payment->payment_method ?? ''),
        ];
    }

    private function addFundError(string $code, string $message): array
    {
        return [
            'success'         => false,
            'walletPaymentId' => null,
            'paymentRedirect' => null,
            'errors'          => [['code' => $code, 'message' => $message]],
        ];
    }
}
