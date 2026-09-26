@php
    $selectorGetUrl       = $getUrl  ?? '';
    $selectorSetUrl       = $setUrl  ?? '';
    $selectorZoneId       = $zoneId  ?? '';
    $selectorModuleId     = $moduleId ?? '';
    $selectorStoreId      = $storeId ?? '';
    $selectorStoreTime    = $storeDeliveryTime ?? '';
    $selectorCurrencySym  = \App\CentralLogics\Helpers::currency_symbol();
    $selectorCurrencyPos  = \App\CentralLogics\Helpers::get_business_settings('currency_symbol_position') ?? 'left';
    $selectorRoundDigit   = (int) (config('round_up_to_digit') ?? 2);
@endphp

<div class="bg-light delivery-type-section d-none"
     id="delivery_type_section"
     data-get-url="{{ $selectorGetUrl }}"
     data-set-url="{{ $selectorSetUrl }}"
     data-zone-id="{{ $selectorZoneId }}"
     data-module-id="{{ $selectorModuleId }}"
     data-store-id="{{ $selectorStoreId }}"
     data-store-delivery-time="{{ $selectorStoreTime }}"
     data-currency-symbol="{{ $selectorCurrencySym }}"
     data-currency-position="{{ $selectorCurrencyPos }}"
     data-round-digit="{{ $selectorRoundDigit }}"
     data-csrf="{{ csrf_token() }}">

    <p class="delivery-type-section__title">{{ translate('messages.delivery_type') }}</p>

    <div class="delivery-type-options" id="delivery_type_options"></div>

    <div class="delivery-type-note d-none" id="delivery_type_note_address">
        <span class="delivery-type-note__icon"><i class="tio-info"></i></span>
        <span>{{ translate('messages.select_delivery_address_first') }}</span>
    </div>

    <div class="delivery-type-note d-none" id="delivery_type_note_free">
        <span class="delivery-type-note__icon"><i class="tio-info"></i></span>
        <span>{{ translate('messages.free_delivery_applies_to_order') }}</span>
    </div>

    <input type="hidden" name="delivery_type"        value="{{ session('delivery_type', '') }}">
    <input type="hidden" name="delivery_type_charge" value="{{ session('delivery_type_charge', 0) }}">
</div>
