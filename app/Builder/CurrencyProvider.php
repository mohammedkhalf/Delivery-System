<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use Modules\Builder\Contracts\CurrencyProvider as CurrencyProviderContract;

class CurrencyProvider implements CurrencyProviderContract
{
    public function getCurrencySettings(): array
    {
        return [
            'symbol'   => Helpers::currency_symbol() ?? '$',
            'position' => Helpers::get_business_settings('currency_symbol_position') ?? 'left',
            'decimals' => (int) (Helpers::get_business_settings('digit_after_decimal_point') ?? 2),
        ];
    }

    /** 6amMart is single-currency — no switcher, nothing to switch between. */
    public function availableCurrencies(): array
    {
        return [];
    }

    public function currentCurrencyCode(): ?string
    {
        return null;
    }

    public function setCurrentCurrency(string $code): void
    {
        // No-op: single platform currency, nothing to persist.
    }
}
