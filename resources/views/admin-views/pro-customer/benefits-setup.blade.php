@extends('layouts.admin.app')

@section('title', translate('messages.Pro_Customer_Benefits_Setup'))
@section('pro_customer_benefits_setup', 'active')

@section('content')

<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>{{ translate('Pro Customer Benefits Setup') }}</span>
            </h1>
        </div>
    </div>

    <div class="info-notes-bg px-3 py-2 rounded fz-11 gap-2 align-items-center d-flex mb-3">
        <img src="{{ asset('public/assets/admin/img/info-idea.svg') }}" alt="">
        <span>{{ translate('Only one benefit can be enabled for Pro customers at a time.') }}</span>
    </div>

    <form action="{{ route('admin.pro-customer.benefits-setup.update') }}" method="post" id="pro-benefits-form">
        @csrf

        {{-- ========== DISCOUNT ========== --}}
        <div class="card mb-20 card-container">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-1 fs-16">{{ translate('messages.discount') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('messages.If enable this option pro customers will receive discount on every order') }}
                        </p>
                    </div>
                    <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-2">
                        <div class="fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1 js-view-btn {{ (int)($settings['discount_status'] ?? 0) ? 'active' : '' }}">
                            {{ translate('messages.view') }}
                            <i class="tio-chevron-down fs-22 {{ (int)($settings['discount_status'] ?? 0) ? 'rotate-180deg' : '' }}"></i>
                        </div>
                        <label class="toggle-switch toggle-switch-sm mb-0">
                            <input type="checkbox" name="discount_status" value="1"
                                class="toggle-switch-input js-benefit-toggle" data-benefit="discount"
                                {{ (int)($settings['discount_status'] ?? 0) ? 'checked' : '' }}>
                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                        </label>
                    </div>
                </div>

                <div class="card-details-body pt-3 js-card-details" style="{{ (int)($settings['discount_status'] ?? 0) ? '' : 'display:none' }}">

                    {{-- Central vs Individual toggle --}}
                    <div class="bg-light2 p-xl-20 p-3 rounded mb-3">
                        <div class="d-flex flex-md-nowrap gap-2 flex-wrap align-items-center justify-content-between">
                            <div class="max-w-595">
                                <h3 class="mb-1 fs-16">{{ translate('Discount setup') }}</h3>
                                <p class="mb-0 gray-dark fs-12">{{ translate('messages.configure discount logic for pro customer') }}</p>
                            </div>
                            <div class="resturant-type-group module_select-area max-w-542 w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                                <label class="form-check form--check w-100">
                                    <input class="form-check-input js-discount-mode" type="radio"
                                        value="central" name="discount_setup_mode"
                                        {{ ($settings['discount_setup_mode'] ?? 'central') === 'central' ? 'checked' : '' }}>
                                    <span class="form-check-label">{{ translate('messages.Central Setup') }}</span>
                                </label>
                                <label class="form-check form--check w-100">
                                    <input class="form-check-input js-discount-mode" type="radio"
                                        value="individual" name="discount_setup_mode"
                                        {{ ($settings['discount_setup_mode'] ?? 'central') === 'individual' ? 'checked' : '' }}>
                                    <span class="form-check-label">{{ translate('messages.Individual Setup') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Central config --}}
                    <div class="bg-light2 p-xl-20 p-3 rounded mb-3 js-central-panel" style="{{ ($settings['discount_setup_mode'] ?? 'central') === 'individual' ? 'display:none' : '' }}">
                        <div class="row g-3 align-items-center">
                            <div class="col-xxl-2">
                                <h3 class="mb-0 fs-16">{{ translate('Discount for All Modules') }}</h3>
                            </div>
                            <div class="col-xxl-10">
                                <div class="p-xxl-20 p-3 rounded bg-white">
                                    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
                                        <div class="flex-grow-1 flex-shrink-0">
                                            <div class="form-group mb-0">
                                                <label class="input-label text-capitalize fw-400 d-flex align-items-center gap-1">
                                                    {{ translate('messages.Discount (%)') }} <span class="text-danger">*</span>
                                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                        data-title="{{ translate('messages.Percentage discount pro customers receive on every order') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                </label>
                                                <input type="number" name="discount_central[percentage]"
                                                    class="form-control" min="{{ $minStep }}" step="{{ $minStep }}" max="100"
                                                    placeholder="{{ translate('messages.Ex: 5') }}"
                                                    value="{{ $discountConfig['central']['percentage'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 flex-shrink-0">
                                            <div class="form-group mb-0">
                                                <label class="input-label text-capitalize fw-400 d-flex align-items-center gap-1">
                                                    {{ translate('messages.Up_To_Discount_Amount') }} <span class="text-danger">*</span>
                                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                        data-title="{{ translate('messages.Maximum discount amount a customer can receive per order') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                </label>
                                                <input type="number" name="discount_central[max_amount]"
                                                    class="form-control" min="{{ $minStep }}" step="{{ $minStep }}"
                                                    placeholder="{{ translate('messages.Ex: 50') }}"
                                                    value="{{ $discountConfig['central']['max_amount'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 flex-shrink-0">
                                            <div class="form-group mb-0">
                                                <label class="input-label text-capitalize d-flex justify-content-between align-items-center gap-2 fw-400">
                                                    <span class="d-flex align-items-center gap-1">
                                                        {{ translate('messages.Minimum order amount') }} ({{ $currencySymbol }})
                                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                            data-title="{{ translate('messages.Minimum order, ride or trip total required to qualify for the discount') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                    </span>
                                                    <label class="toggle-switch toggle-switch-sm mb-0">
                                                        <input type="checkbox" name="discount_central[min_order_status]" value="1"
                                                            class="toggle-switch-input js-min-toggle"
                                                            {{ ($discountConfig['central']['min_order_status'] ?? 0) ? 'checked' : '' }}>
                                                        <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                                                    </label>
                                                </label>
                                                <input type="number" name="discount_central[min_order_amount]"
                                                    class="form-control js-min-field" min="{{ $minStep }}" step="{{ $minStep }}"
                                                    placeholder="{{ translate('messages.Ex: 100') }}"
                                                    value="{{ $discountConfig['central']['min_order_amount'] ?? '' }}"
                                                    {{ ($discountConfig['central']['min_order_status'] ?? 0) ? '' : 'disabled' }}>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Individual per-module config --}}
                    <div class="js-individual-panel" style="{{ ($settings['discount_setup_mode'] ?? 'central') === 'central' ? 'display:none' : '' }}">
                        @foreach ($discountModules as $mod)
                        <div class="bg-light2 p-xl-20 p-3 rounded {{ !$loop->last ? 'mb-3' : '' }}">
                            <div class="row g-3 align-items-center">
                                <div class="col-xxl-2">
                                    <h3 class="mb-0 fs-16">{{ $moduleLabels[$mod] ?? ucfirst($mod) }} {{ translate('messages.Module') }}</h3>
                                </div>
                                <div class="col-xxl-10">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
                                            <div class="flex-grow-1 flex-shrink-0">
                                                <div class="form-group mb-0">
                                                    <label class="input-label text-capitalize fw-400 d-flex align-items-center gap-1">
                                                        {{ translate('messages.Discount (%)') }} <span class="text-danger">*</span>
                                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                            data-title="{{ translate('messages.Percentage discount pro customers receive on orders in this module') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                    </label>
                                                    <input type="number" name="discount_individual[{{ $mod }}][percentage]"
                                                        class="form-control" min="{{ $minStep }}" step="{{ $minStep }}" max="100"
                                                        placeholder="{{ translate('messages.Ex: 5') }}"
                                                        value="{{ $discountConfig[$mod]['percentage'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 flex-shrink-0">
                                                <div class="form-group mb-0">
                                                    <label class="input-label text-capitalize fw-400 d-flex align-items-center gap-1">
                                                        {{ translate('messages.Up_To_Discount_Amount') }}
                                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                            data-title="{{ translate('messages.Maximum discount amount a customer can receive per order in this module') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                    </label>
                                                    <input type="number" name="discount_individual[{{ $mod }}][max_amount]"
                                                        class="form-control" min="{{ $minStep }}" step="{{ $minStep }}"
                                                        placeholder="{{ translate('messages.Ex: 50') }}"
                                                        value="{{ $discountConfig[$mod]['max_amount'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 flex-shrink-0">
                                                <div class="form-group mb-0">
                                                    <label class="input-label text-capitalize d-flex justify-content-between align-items-center gap-2 fw-400">
                                                        <span class="d-flex align-items-center gap-1">
                                                            {{ $minOrderLabels[$mod] ?? translate('messages.Minimum order amount') }} ({{ $currencySymbol }})
                                                            <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                                data-title="{{ $minOrderTooltips[$mod] ?? translate('messages.Minimum order total required to qualify for the discount in this module') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                        </span>
                                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                                            <input type="checkbox" name="discount_individual[{{ $mod }}][min_order_status]" value="1"
                                                                class="toggle-switch-input js-min-toggle"
                                                                {{ ($discountConfig[$mod]['min_order_status'] ?? 0) ? 'checked' : '' }}>
                                                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                                                        </label>
                                                    </label>
                                                    <input type="number" name="discount_individual[{{ $mod }}][min_order_amount]"
                                                        class="form-control js-min-field" min="{{ $minStep }}" step="{{ $minStep }}"
                                                        placeholder="{{ translate('messages.Ex: 100') }}"
                                                        value="{{ $discountConfig[$mod]['min_order_amount'] ?? '' }}"
                                                        {{ ($discountConfig[$mod]['min_order_status'] ?? 0) ? '' : 'disabled' }}>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                </div>
            </div>
        </div>

        {{-- ========== COUPON ========== --}}
        <div class="card py-3 px-xxl-4 px-3 mb-20">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="mb-1 fs-16">{{ translate('messages.coupon') }}</h3>
                    <p class="mb-0 gray-dark fs-12">
                        {{ translate('Create special coupons from') }}
                        <a href="{{ route('admin.coupon.add-new') }}" class="text-underline text-info font-weight-medium">{{ translate('messages.coupons') }}</a>
                        {{ translate('and choose \'Pro Customer\' as the coupon type.') }}
                    </p>
                </div>
                <div>
                    <label class="toggle-switch toggle-switch-sm mb-0">
                        <input type="checkbox" name="coupon_status" value="1"
                            class="toggle-switch-input js-benefit-toggle" data-benefit="coupon"
                            {{ (int)($settings['coupon_status'] ?? 0) ? 'checked' : '' }}>
                        <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ========== DELIVERY FEE ========== --}}
        <div class="card mb-20 card-container">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-1 fs-16">{{ translate('messages.Delivery_Fee') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('messages.If enable this option pro customers will receive free delivery') }}
                        </p>
                    </div>
                    <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-2">
                        <div class="fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1 js-view-btn {{ (int)($settings['delivery_fee_status'] ?? 0) ? 'active' : '' }}">
                            {{ translate('messages.view') }}
                            <i class="tio-chevron-down fs-22 {{ (int)($settings['delivery_fee_status'] ?? 0) ? 'rotate-180deg' : '' }}"></i>
                        </div>
                        <label class="toggle-switch toggle-switch-sm mb-0">
                            <input type="checkbox" name="delivery_fee_status" value="1"
                                class="toggle-switch-input js-benefit-toggle" data-benefit="delivery_fee"
                                {{ (int)($settings['delivery_fee_status'] ?? 0) ? 'checked' : '' }}>
                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                        </label>
                    </div>
                </div>

                <div class="card-details-body pt-3 js-card-details" style="{{ (int)($settings['delivery_fee_status'] ?? 0) ? '' : 'display:none' }}">
                    <div class="d-flex gap-3 flex-column">

                        @foreach ($deliveryFeeModules as $mod)
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xxl-2">
                                    <h3 class="mb-0 fs-16">{{ $moduleLabels[$mod] ?? ucfirst($mod) }} {{ translate('messages.Module') }}</h3>
                                </div>
                                <div class="col-xxl-10">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">

                                            {{-- Delivery type: hidden for parcel (always partial_free) --}}
                                            @if ($mod !== 'parcel')
                                            <div class="flex-grow-1 flex-shrink-0">
                                                <div class="form-group mb-0">
                                                    <label class="input-label text-capitalize fw-400 d-flex align-items-center gap-1">
                                                        {{ translate('messages.Delivery Type') }}
                                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                            data-title="{{ translate('messages.Full Free: 100% off delivery fee. Partial Free: percentage off delivery fee') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                    </label>
                                                    <div class="resturant-type-group module_select-area w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                                                        <label class="form-check form--check w-100">
                                                            <input class="form-check-input js-delivery-type" type="radio"
                                                                value="full_free" name="delivery_fee[{{ $mod }}][offer_type]"
                                                                data-mod="{{ $mod }}"
                                                                {{ ($deliveryFeeConfig[$mod]['offer_type'] ?? 'full_free') === 'full_free' ? 'checked' : '' }}>
                                                            <span class="form-check-label">{{ translate('messages.Full Free') }}</span>
                                                        </label>
                                                        <label class="form-check form--check w-100">
                                                            <input class="form-check-input js-delivery-type" type="radio"
                                                                value="partial_free" name="delivery_fee[{{ $mod }}][offer_type]"
                                                                data-mod="{{ $mod }}"
                                                                {{ ($deliveryFeeConfig[$mod]['offer_type'] ?? 'full_free') === 'partial_free' ? 'checked' : '' }}>
                                                            <span class="form-check-label">{{ translate('messages.Partial Free') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            @else
                                            <input type="hidden" name="delivery_fee[parcel][offer_type]" value="partial_free">
                                            @endif

                                            {{-- Charge discount: always visible for parcel; toggled for others --}}
                                            <div class="flex-grow-1 flex-shrink-0 js-partial-field-{{ $mod }}"
                                                style="{{ $mod !== 'parcel' && ($deliveryFeeConfig[$mod]['offer_type'] ?? 'full_free') !== 'partial_free' ? 'display:none' : '' }}">
                                                <div class="form-group mb-0">
                                                    <label class="input-label text-capitalize fw-400 d-flex align-items-center gap-1">
                                                        {{ translate('messages.Discount (%)') }} <span class="text-danger">*</span>
                                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                            data-title="{{ translate('messages.Percentage of delivery fee that will be discounted for pro customers') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                    </label>
                                                    <input type="number" name="delivery_fee[{{ $mod }}][charge_discount]"
                                                        class="form-control" min="{{ $minStep }}" step="{{ $minStep }}" max="100"
                                                        placeholder="{{ translate('messages.Ex: 20') }}"
                                                        value="{{ $deliveryFeeConfig[$mod]['charge_discount'] ?? '' }}"
                                                        {{ $mod !== 'parcel' && ($deliveryFeeConfig[$mod]['offer_type'] ?? 'full_free') !== 'partial_free' ? 'disabled' : '' }}>
                                                </div>
                                            </div>

                                            {{-- Minimum order amount: always visible for all modules --}}
                                            <div class="flex-grow-1 flex-shrink-0">
                                                <div class="form-group mb-0">
                                                    <label class="input-label text-capitalize d-flex justify-content-between align-items-center gap-2 fw-400">
                                                        <span class="d-flex align-items-center gap-1">
                                                            {{ $minOrderLabels[$mod] ?? translate('messages.Minimum order amount') }} ({{ $currencySymbol }})
                                                            <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                                data-title="{{ $mod === 'parcel' ? translate('messages.Minimum delivery charge required to qualify for this delivery benefit') : translate('messages.Minimum order total required to qualify for this delivery benefit') }}"><i class="tio-info text-muted fs-16"></i></span>
                                                        </span>
                                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                                            <input type="checkbox" name="delivery_fee[{{ $mod }}][min_order_status]" value="1"
                                                                class="toggle-switch-input js-min-toggle"
                                                                {{ ($deliveryFeeConfig[$mod]['min_order_status'] ?? 0) ? 'checked' : '' }}>
                                                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                                                        </label>
                                                    </label>
                                                    <input type="number" name="delivery_fee[{{ $mod }}][min_order_amount]"
                                                        class="form-control js-min-field" min="{{ $minStep }}" step="{{ $minStep }}"
                                                        placeholder="{{ translate('messages.Ex: 100') }}"
                                                        value="{{ $deliveryFeeConfig[$mod]['min_order_amount'] ?? '' }}"
                                                        {{ ($deliveryFeeConfig[$mod]['min_order_status'] ?? 0) ? '' : 'disabled' }}>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach

                    </div>
                </div>
            </div>
        </div>

        @include('admin-views.partials._floating-submit-button')
    </form>
</div>

@endsection

@push('script_2')
<script>
(function () {

    function getCardBody($el) {
        return $el.closest('.card-body');
    }

    function syncViewBtn($cb, open) {
        var $btn = $cb.find('.js-view-btn');
        $btn.toggleClass('active', open);
        $btn.find('i').toggleClass('rotate-180deg', open);
    }

    // View button — toggles card details open/closed
    $(document).on('click', '.js-view-btn', function (e) {
        e.stopPropagation();
        var $btn     = $(this);
        var $cb      = getCardBody($btn);
        var $details = $cb.find('.js-card-details');
        var willOpen = !$btn.hasClass('active');
        syncViewBtn($cb, willOpen);
        $details.stop(true, true);
        if (willOpen) $details.slideDown(200);
        else          $details.slideUp(200);
    });

    // Benefit toggle switch — shows/hides card details and enforces mutual exclusivity
    $(document).on('change', '.js-benefit-toggle', function () {
        var checked  = this.checked;
        var $cb      = getCardBody($(this));
        syncViewBtn($cb, checked);
        if (checked) {
            $cb.find('.js-card-details').slideDown(200);
            $('.js-benefit-toggle').not(this).each(function () {
                this.checked = false;
                var $oCb = getCardBody($(this));
                syncViewBtn($oCb, false);
                $oCb.find('.js-card-details').slideUp(200);
            });
        } else {
            $cb.find('.js-card-details').slideUp(200);
        }
    });

    // Discount mode — central shows the "All Modules" panel, individual shows per-module rows
    $(document).on('change', '.js-discount-mode', function () {
        if ($(this).val() === 'central') {
            $('.js-central-panel').show();
            $('.js-individual-panel').hide();
        } else {
            $('.js-central-panel').hide();
            $('.js-individual-panel').show();
        }
    });

    // Min-order amount field enable/disable
    $(document).on('change', '.js-min-toggle', function () {
        var $field = $(this).closest('.form-group').find('.js-min-field');
        $field.prop('disabled', !this.checked);
        if (!this.checked) $field.val('');
    });

    // Delivery fee type — only charge_discount column toggles; min-order always visible
    $(document).on('change', '.js-delivery-type', function () {
        var mod       = $(this).data('mod');
        var isPartial = $(this).val() === 'partial_free';
        $('.js-partial-field-' + mod).toggle(isPartial)
            .find('input').prop('disabled', !isPartial);
    });

    // ── Frontend validation ──────────────────────────────────────────────────
    var discountModules    = @json($discountModules);
    var deliveryFeeModules = @json($deliveryFeeModules);

    function markField($input, valid) {
        $input.toggleClass('is-invalid', !valid);
    }

    function checkVal($input) {
        var ok = $.trim($input.val()) !== '';
        markField($input, ok);
        return ok;
    }

    // Clear is-invalid on fix
    $(document).on('input change', '.is-invalid', function () {
        if ($.trim($(this).val()) !== '') $(this).removeClass('is-invalid');
    });

    $('#pro-benefits-form').on('submit', function (e) {
        var valid      = true;
        var $firstErr  = null;

        function fail($el) {
            valid = false;
            if (!$firstErr) $firstErr = $el;
        }

        if ($('.js-benefit-toggle:checked').length === 0) {
            e.preventDefault();
            toastr.error('{{ translate('messages.at_least_one_pro_customer_benefit_must_be_enabled') }}');
            return;
        }

        var discountOn = $('[name="discount_status"]').is(':checked');
        var deliveryOn = $('[name="delivery_fee_status"]').is(':checked');

        if (discountOn) {
            var mode = $('[name="discount_setup_mode"]:checked').val() || 'central';
            if (mode === 'central') {
                var $pct = $('[name="discount_central[percentage]"]');
                var $max = $('[name="discount_central[max_amount]"]');
                if (!checkVal($pct)) fail($pct);
                if (!checkVal($max)) fail($max);
                if ($('[name="discount_central[min_order_status]"]').is(':checked')) {
                    var $min = $('[name="discount_central[min_order_amount]"]');
                    if (!checkVal($min)) fail($min);
                }
            } else {
                discountModules.forEach(function (mod) {
                    var $pct = $('[name="discount_individual[' + mod + '][percentage]"]');
                    var $max = $('[name="discount_individual[' + mod + '][max_amount]"]');
                    if (!checkVal($pct)) fail($pct);
                    if (!checkVal($max)) fail($max);
                    var $tog = $('[name="discount_individual[' + mod + '][min_order_status]"]');
                    if ($tog.is(':checked')) {
                        var $min = $('[name="discount_individual[' + mod + '][min_order_amount]"]');
                        if (!checkVal($min)) fail($min);
                    }
                });
            }
        }

        if (deliveryOn) {
            deliveryFeeModules.forEach(function (mod) {
                var offerType = mod === 'parcel'
                    ? 'partial_free'
                    : ($('[name="delivery_fee[' + mod + '][offer_type]"]:checked').val() || 'full_free');
                if (offerType === 'partial_free') {
                    var $cd = $('[name="delivery_fee[' + mod + '][charge_discount]"]');
                    if (!checkVal($cd)) fail($cd);
                }
                var $tog = $('[name="delivery_fee[' + mod + '][min_order_status]"]');
                if ($tog.is(':checked')) {
                    var $min = $('[name="delivery_fee[' + mod + '][min_order_amount]"]');
                    if (!checkVal($min)) fail($min);
                }
            });
        }

        if (!valid) {
            e.preventDefault();
            if ($firstErr) {
                $firstErr[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $firstErr.focus();
            }
        }
    });

}());
</script>
@endpush
