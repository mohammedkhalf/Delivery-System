@php
    $idPrefix = $idPrefix ?? 'pm';
    $balance = (float) ($customer->wallet_balance ?? 0);
    $price = (float) ($price ?? 0);
    $hasEnough = $balance >= $price;
@endphp
<div class="js-payment-method pt-3" data-wallet-balance="{{ $balance }}">
    <div class="d-flex align-items-stretch mb-4 pb-xxl-2 gap-20px flex-sm-nowrap flex-wrap justify-content-center">
        <div class="w-100">
            <div class="d-flex align-items-center rounded py-3 px-3 border justify-content-between gap-1 h-100 js-pay-wallet-label {{ $hasEnough ? '' : 'opacity-50' }}">
                <label class="form-check form--check mb-0 flex-grow-1">
                    <input class="form-check-input js-pay-wallet" type="radio" name="payment_method" value="wallet"
                        id="{{ $idPrefix }}_pay_wallet" {{ $hasEnough ? 'checked' : 'disabled' }}>
                    <span class="form-check-label text-nowrap">{{ translate('messages.Pay_via_Wallet') }}</span>
                </label>
                <img width="22" src="{{ asset('public/assets/admin/img/wallet-in.svg') }}" alt="wallet" class="flex-shrink-0">
            </div>
            <small class="text-danger d-{{ $hasEnough ? 'none' : 'block' }} mt-1 js-wallet-low-msg">
                {{ translate('messages.Insufficient_wallet_balance') }}
            </small>
        </div>
        <div class="w-100">
            <div class="d-flex align-items-center rounded py-3 px-3 border justify-content-between gap-1 h-100">
                <label class="form-check form--check mb-0">
                    <input class="form-check-input" type="radio" name="payment_method" value="manual"
                        id="{{ $idPrefix }}_pay_manual" {{ $hasEnough ? '' : 'checked' }}>
                    <span class="form-check-label">{{ translate('messages.Manually_Pay') }}</span>
                </label>
            </div>
        </div>
    </div>
</div>
