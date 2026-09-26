{{-- $store->verified_seller routes through Helpers::get_verified_seller_status(), which is
     module-aware: service providers use service_provider_verified_badge, others use the core
     verified_seller_badge — so this single check gates the badge correctly for every module. --}}
@if (($store?->verified_seller ?? 0) == 1)
    {{-- Tooltip context auto-detected from the store's module:
         rental/service → "Verified Provider", anything else → "Verified Store". --}}
    <i class="tio-verified text-success" data-toggle="tooltip" data-placement="top"
        title="{{ translate('Verified') }} {{ translate(in_array($store?->module_type ?? null, ['rental', 'service']) ? 'Provider' : 'Store') }}"></i>
@endif
