<?php

namespace App\Builder;

use App\CentralLogics\CustomerLogic;
use App\Models\BusinessSetting;
use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\LoyaltyPointProvider as LoyaltyPointProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\LoyaltySummaryDTO;

class LoyaltyPointProvider implements LoyaltyPointProviderContract
{
    private const TYPE_LABELS = [
        'point_to_wallet' => 'Point To Wallet',
        'order_place'     => 'Order Place',
        'trip_booking'    => 'Trip Booking',
    ];

    private const SELECT = ['id', 'loyalty_point', 'wallet_balance'];

    private ?array $settingsCache = null;

    public function summary(int $customerId): LoyaltySummaryDTO
    {
        $settings = $this->loyaltySettings();

        if ($customerId <= 0) {
            return LoyaltySummaryDTO::fromArray([
                'balance'           => 0.0,
                'walletBalance'     => 0.0,
                'exchangeRate'      => (float) ($settings['loyalty_point_exchange_rate'] ?? 0),
                'minimumPoint'      => (int) ($settings['loyalty_point_minimum_point'] ?? 0),
                'itemPurchasePoint' => (float) ($settings['loyalty_point_item_purchase_point'] ?? 0),
                'status'            => (bool) (int) ($settings['loyalty_point_status'] ?? 0),
            ]);
        }

        $user = User::query()->select(self::SELECT)->find($customerId);

        return LoyaltySummaryDTO::fromArray([
            'balance'           => (float) ($user->loyalty_point ?? 0),
            'walletBalance'     => (float) ($user->wallet_balance ?? 0),
            'exchangeRate'      => (float) ($settings['loyalty_point_exchange_rate'] ?? 0),
            'minimumPoint'      => (int) ($settings['loyalty_point_minimum_point'] ?? 0),
            'itemPurchasePoint' => (float) ($settings['loyalty_point_item_purchase_point'] ?? 0),
            'status'            => (bool) (int) ($settings['loyalty_point_status'] ?? 0),
        ]);
    }

    public function transactions(int $customerId, int $perPage, int $page): PaginatedResult
    {
        if ($customerId <= 0) {
            return PaginatedResult::fromPaginator(new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $perPage,
                currentPage: $page,
                options: ['pageName' => 'loyaltyPage'],
            ));
        }

        $paginator = LoyaltyPointTransaction::query()
            ->where('user_id', $customerId)
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page, pageName: 'loyaltyPage')
            ->through(fn (LoyaltyPointTransaction $txn) => $this->mapTransaction($txn));

        return PaginatedResult::fromPaginator($paginator);
    }

    public function convertToWallet(int $customerId, int $point): array
    {
        if ($customerId <= 0) {
            return $this->convertError('auth', 'Please sign in to convert points.');
        }

        $settings = $this->loyaltySettings();

        if ((int) ($settings['loyalty_point_status'] ?? 0) !== 1) {
            return $this->convertError('loyalty_disabled', 'Loyalty point feature is currently disabled.');
        }

        $minimum = (int) ($settings['loyalty_point_minimum_point'] ?? 0);
        $rate    = (int) ($settings['loyalty_point_exchange_rate'] ?? 0);
        if ($rate <= 0) {
            return $this->convertError('exchange_rate', 'Exchange rate is not configured. Please contact support.');
        }

        if ($point <= 0) {
            return $this->convertError('point', 'Please enter a valid point amount.');
        }
        if ($point < $minimum) {
            return $this->convertError('point', "Minimum {$minimum} points required to convert.");
        }

        $user = User::query()->find($customerId);
        if (!$user) {
            return $this->convertError('auth', 'Customer record could not be loaded.');
        }
        if ((int) $user->loyalty_point < $point) {
            return $this->convertError('point', 'Insufficient loyalty point balance.');
        }

        try {
            $walletTransaction = null;

            DB::transaction(function () use ($user, $point, &$walletTransaction) {
                $locked = User::query()->lockForUpdate()->find($user->id);

                if ((int) $locked->loyalty_point < $point) {
                    throw new \RuntimeException('insufficient_point');
                }

                $walletTransaction = CustomerLogic::create_wallet_transaction($locked->id, (float) $point, 'loyalty_point', null);
                if (!$walletTransaction) {
                    throw new \RuntimeException('wallet_transaction_failed');
                }

                CustomerLogic::create_loyalty_point_transaction($locked->id, $walletTransaction->transaction_id, $point, 'point_to_wallet');
            });

            $fresh = User::query()->select(self::SELECT)->find($user->id);
            $credit = $rate > 0 ? (int) ($point / $rate) : 0;

            return [
                'success'          => true,
                'pointsConverted'  => $point,
                'walletCredit'     => (float) $credit,
                'newBalance'       => (float) ($fresh->loyalty_point ?? 0),
                'newWalletBalance' => (float) ($fresh->wallet_balance ?? 0),
                'errors'           => [],
            ];
        } catch (\Throwable $e) {
            $code    = $e->getMessage() === 'insufficient_point' ? 'point' : 'transfer_failed';
            $message = $code === 'point'
                ? 'Insufficient loyalty point balance.'
                : 'Failed to convert. Please try again.';
            return $this->convertError($code, $message);
        }
    }

    private function loyaltySettings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        $rows = BusinessSetting::query()
            ->whereIn('key', [
                'loyalty_point_status',
                'loyalty_point_exchange_rate',
                'loyalty_point_minimum_point',
                'loyalty_point_item_purchase_point',
            ])
            ->pluck('value', 'key')
            ->all();

        return $this->settingsCache = $rows;
    }

    private function mapTransaction(LoyaltyPointTransaction $txn): array
    {
        $credit    = (float) $txn->credit;
        $debit     = (float) $txn->debit;
        $direction = $credit >= $debit ? 'credit' : 'debit';
        $amount    = $direction === 'credit' ? $credit : $debit;
        $slug      = (string) ($txn->transaction_type ?? '');

        $rawCreatedAt = $txn->getAttribute('created_at');
        $date = '';
        if ($rawCreatedAt) {
            try {
                $date = Carbon::parse($rawCreatedAt)->format('d M Y, h:iA');
            } catch (\Throwable) {
                $date = '';
            }
        }

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
            'date'          => $date,
        ];
    }

    private function labelFor(string $slug): string
    {
        if (isset(self::TYPE_LABELS[$slug])) {
            return self::TYPE_LABELS[$slug];
        }
        $clean = str_replace('_', ' ', $slug);
        return ucwords($clean);
    }

    private function convertError(string $code, string $message): array
    {
        return [
            'success'          => false,
            'pointsConverted'  => null,
            'walletCredit'     => null,
            'newBalance'       => null,
            'newWalletBalance' => null,
            'errors'           => [['code' => $code, 'message' => $message]],
        ];
    }
}
