@extends('layouts.admin.app')

@section('title', translate('messages.Subscription_Plan'))

@section('content')
@php
    $currentPlanId = $subscription?->plan_id;
    $isActive = $subscription && $subscription->status === 'active';
    $isCanceled = $subscription && $subscription->status === 'canceled';
    $statusBadgeMap = [
        'active'   => 'text-success bg-success bg-opacity-10',
        'expired'  => 'text-warning bg-warning bg-opacity-10',
        'canceled' => 'text-danger bg-danger bg-opacity-10',
    ];
    $statusBadge = $subscription ? ($statusBadgeMap[$subscription->status] ?? 'text-secondary bg-secondary bg-opacity-10') : '';
    $txnStatusBadgeMap = [
        'success'  => 'text-success bg-success bg-opacity-10',
        'pending'  => 'text-warning bg-warning bg-opacity-10',
        'failed'   => 'text-danger bg-danger bg-opacity-10',
        'refunded' => 'text-secondary bg-secondary bg-opacity-10',
    ];
@endphp

<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>{{ translate('messages.customer_id') }} <span class="gray-dark">#{{ $customer->id }}</span></span>
            </h1>
        </div>
    </div>

    @include('admin-views.customer.partials._tab_view')

    <div class="card mb-20">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-20">
                <div>
                    <h3 class="mb-1 fs-16">{{ translate('messages.Subscription_Plan') }}</h3>
                    <p class="mb-0 gray-dark fs-12">
                        {{ translate('messages.here_you_can_see_an_overview_of_subscription_plans') }}
                    </p>
                </div>
                @if ($subscription)
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if ($isActive)
                            <button type="button" class="btn btn--cancel py-1 h-40 text-nowrap px-3 form-alert"
                                data-id="pro-sub-cancel-{{ $subscription->id }}"
                                data-message="{{ translate('messages.Cancel_this_subscription') }}?">
                                {{ translate('messages.Cancel_Subscription') }}
                            </button>
                            <form action="{{ route('admin.pro-customer.subscription.cancel', $subscription->id) }}"
                                method="post" id="pro-sub-cancel-{{ $subscription->id }}">
                                @csrf
                            </form>
                        @endif
                        @if ($plans->count())
                            @if ($isCanceled)
                                <button type="button" class="btn btn--primary h-40 text-nowrap px-3" data-toggle="modal"
                                    data-target="#plan_subscribe_modal">
                                    {{ translate('messages.Subscribe_Now') }}
                                </button>
                            @else
                                <button type="button" class="btn btn--primary h-40 text-nowrap px-3" data-toggle="modal"
                                    data-target="#plan_modal_area">
                                    {{ translate('messages.Shift_or_Renew_Subscription') }}
                                </button>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            @if ($subscription)
                <div class="bg-light2 p-xxl-20 p-3">
                    <div class="row g-3">
                        {{-- Plan name & price --}}
                        <div class="col-md-12 col-lg-4">
                            <div class="subscription-plan__card">
                                <div>
                                    <div class="mb-2 d-flex gap-2 align-items-center justify-content-start">
                                        <div class="w-40px">
                                            <img width="40"
                                                src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}"
                                                alt="img" class="rounded-circle">
                                        </div>
                                        <h3 class="mb-0 fs-24 fw-medium lh-1">{{ $subscription->plan_name }}</h3>
                                    </div>
                                    @if ($subscription->plan_type === 'free_trial')
                                        <p class="mb-0 fs-32 font-semibold text-dark text-capitalize">
                                            {{ translate('messages.Free_Trial') }}
                                            <span class="fs-20 font-weight-light gray-dark">/ {{ optional($subscription->plan)->duration ?? '-' }} {{ translate('messages.days') }}</span>
                                        </p>
                                    @else
                                        <p class="mb-0 fs-32 font-semibold text-dark">
                                            {{ \App\CentralLogics\Helpers::format_currency((float) $subscription->plan_price) }}
                                            <span class="fs-20 font-weight-light gray-dark">/ {{ optional($subscription->plan)->duration ?? '-' }} {{ translate('messages.days') }}</span>
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Plan validity --}}
                        <div class="col-lg-4 col-md-6">
                            <div class="bg-white subscription-plan__card p-3 rounded">
                                <div class="mb-3 d-flex gap-2 align-items-center justify-content-start">
                                    <h3 class="mb-0 fs-14 fw-medium lh-1">{{ translate('messages.Plan_Validity') }}</h3>
                                    <span class="badge {{ $statusBadge }} px-2 rounded-pill fs-12 text-capitalize">
                                        {{ $subscription->status }}
                                    </span>
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="fs-14 min-w-90">{{ translate('messages.Start_Date') }}</span>
                                        <span>:</span>
                                        <span class="fs-14 text-dark">{{ $subscription->start_at ? \App\CentralLogics\Helpers::time_date_format($subscription->start_at) : '-' }}</span>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="fs-14 min-w-90">{{ translate('messages.Expire_Date') }}</span>
                                        <span>:</span>
                                        <span class="fs-14 text-dark">{{ $subscription->end_at ? \App\CentralLogics\Helpers::time_date_format($subscription->end_at) : '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Transaction --}}
                        <div class="col-lg-4 col-md-6">
                            <div class="bg-white subscription-plan__card p-3 rounded">
                                <div class="mb-3 d-flex gap-2 align-items-center justify-content-start flex-wrap">
                                    <h3 class="mb-0 fs-14 fw-medium lh-1">{{ translate('messages.Transaction') }}</h3>
                                    @if ($latestTransaction)
                                        <span class="fs-14 fw-medium text-dark">#{{ $latestTransaction->id }}</span>
                                        <span class="badge {{ $txnStatusBadgeMap[$latestTransaction->payment_status] ?? 'text-secondary bg-secondary bg-opacity-10' }} px-2 rounded-pill fs-12 text-capitalize">
                                            {{ $latestTransaction->payment_status }}
                                        </span>
                                    @else
                                        <span class="badge text-secondary bg-secondary bg-opacity-10 px-2 rounded-pill fs-12 text-capitalize">
                                            {{ translate('messages.N/A') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="fs-14 min-w-90">{{ translate('messages.Payment_Date') }}</span>
                                        <span>:</span>
                                        <span class="fs-14 text-dark">
                                            @if ($latestTransaction)
                                                {{ \App\CentralLogics\Helpers::time_date_format($latestTransaction->paid_at ?? $latestTransaction->created_at) }}
                                            @else
                                                -
                                            @endif
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="fs-14 min-w-90">{{ translate('messages.Paid_By') }}</span>
                                        <span>:</span>
                                        <span class="fs-14 text-dark text-capitalize">
                                            @if ($latestTransaction && $latestTransaction->payment_method)
                                                {{ str_replace('_', ' ', $latestTransaction->payment_method) }}
                                            @elseif ($subscription->plan_type === 'free_trial')
                                                {{ translate('messages.Free_Trial') }}
                                            @else
                                                {{ translate('messages.N/A') }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="empty--data text-center py-5 my-4">
                    <img src="{{ asset('public/assets/admin/img/empty.png') }}" alt="empty"
                        style="max-width:140px;height:auto;" class="mb-3">
                    <h5 class="fs-16 mb-1 text-capitalize">{{ translate('messages.No_Subscription_Found') }}</h5>
                    <p class="fs-12 gray-dark mb-3">
                        {{ translate('messages.this_customer_has_not_subscribed_to_any_pro_plan_yet') }}
                    </p>
                    @if ($plans->count())
                        <button type="button" class="btn btn--primary h-40 px-4 text-capitalize" data-toggle="modal"
                            data-target="#plan_subscribe_modal">
                            {{ translate('messages.Subscribe_Now') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Shift / Renew modal (existing subscription) --}}
@if ($subscription && !$isCanceled && $plans->count())
    <div class="modal fade" id="plan_modal_area" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <div class="mb-20">
                        <h3 class="mb-3 fs-20 text-center">{{ translate('messages.Shift_or_Renew_Subscription') }}</h3>
                        <div class="border rounded-10">
                            <div class="bg-plan rounded-10">
                                <div class="rounded-4 bg-plan-gradient p-3 text-center plan-pro-head">
                                    <div class="w-40px mx-auto mb-10px">
                                        <img width="40"
                                            src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}"
                                            alt="img" class="rounded-circle">
                                    </div>
                                    <h3 class="mb-1 fs-18 fw-medium lh-1">{{ $proBrand }}</h3>
                                    <p class="mb-0 fs-14">{{ translate('messages.Save_more_on_every_order') }}</p>
                                </div>
                                @include('admin-views.pro-customer.partials._active-benefits-list', ['benefitItems' => $benefitItems])
                            </div>
                            <div class="p-20">
                                <p class="fs-14 mb-10px">{{ translate('messages.Select_Duration') }}</p>
                                <select class="custom-select mb-10px js-plan-select">
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}"
                                            data-name="{{ $plan->plan_name }}"
                                            data-price="{{ (float) $plan->price }}"
                                            data-duration="{{ $plan->duration }}"
                                            data-type="{{ $plan->plan_type }}"
                                            {{ (int) $plan->id === (int) $currentPlanId ? 'selected' : '' }}>
                                            {{ $plan->duration }} {{ translate('messages.Days') }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="bg-plan-monthly d-flex p-3 rounded align-items-center gap-2 justify-content-between">
                                    <div class="mb-0 d-flex gap-2 align-items-center flex-wrap">
                                        <h3 class="mb-0 fs-14 fw-medium lh-1 js-modal-plan-name"></h3>
                                        <span class="badge {{ $statusBadge }} px-2 rounded-pill fs-12 text-capitalize js-modal-current-badge d-none">
                                            {{ $subscription->status }}
                                        </span>
                                    </div>
                                    <h3 class="m-0 fs-25 js-modal-plan-price"></h3>
                                </div>
                                <div class="mt-4 text-center d-flex flex-column gap-2 align-items-center">
                                    <button type="button"
                                        class="max-w-260px w-100 btn p-0 btn--primary renew-btn text-white py-2 px-3 js-do-renew d-none">
                                        {{ translate('messages.Renew_Subscription') }}
                                    </button>
                                    <button type="button"
                                        class="max-w-260px w-100 btn p-0 btn--primary renew-btn text-white py-2 px-3 js-do-shift d-none">
                                        {{ translate('messages.Shift_Subscription') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Renew confirm modal --}}
    <div class="modal fade" id="plan_renew_subscription" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <form method="post"
                        action="{{ route('admin.pro-customer.subscription.renew', $subscription->id) }}">
                        @csrf
                        <input type="hidden" name="plan_id" id="renew_plan_id" value="{{ $currentPlanId }}">
                        <h3 class="mb-3 fs-20 text-center text-capitalize">{{ translate('messages.Renew_Subscription') }}</h3>
                        <div class="border rounded-10 p-4">
                            <div class="bg-plan w-fit-content mx-auto subscription-plan__card rounded-10 mb-4">
                                <div class="d-flex align-items-center gap-3 rounded-0 bg-plan-gradient p-3 plan-pro-head">
                                    <div class="w-40px mb-0">
                                        <img width="40"
                                            src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}"
                                            alt="img" class="rounded-circle">
                                    </div>
                                    <div class="text-start">
                                        <h3 class="mb-1 fs-24 fw-medium lh-1" id="renew_plan_name_label">{{ $subscription->plan_name }}</h3>
                                        <p class="mb-0 fs-32 font-semibold text-dark">
                                            <span id="renew_plan_price_label">
                                                @if ($subscription->plan_type === 'free_trial')
                                                    <span class="fs-20 fw-medium text-capitalize">{{ translate('messages.Free_Trial') }}</span>
                                                @else
                                                    {{ \App\CentralLogics\Helpers::format_currency((float) $subscription->plan_price) }}
                                                @endif
                                            </span>
                                            <span class="fs-20 font-weight-light gray-dark" id="renew_plan_duration_label">/ {{ optional($subscription->plan)->duration ?? '-' }} {{ translate('messages.days') }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="js-renew-payment-wrapper">
                                @include('admin-views.pro-customer.partials._payment-method-radios', [
                                    'customer' => $customer,
                                    'price'    => (float) $subscription->plan_price,
                                    'idPrefix' => 'renew',
                                ])
                            </div>
                            <div class="text-center pt-2">
                                <p class="mb-3">{{ translate('messages.#Note_:_Ensure_payment_is_received_before_changing_or_renewing_the_subscription') }}</p>
                                <button type="submit"
                                    class="max-w-260px w-100 btn p-0 btn--primary renew-btn text-white py-2 px-3 text-capitalize">
                                    {{ translate('messages.Confirm_Renew') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Shift confirm modal --}}
    <div class="modal fade" id="plan_shift_subscription" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog max-w-850px mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <form method="post"
                        action="{{ route('admin.pro-customer.subscription.shift', $subscription->id) }}">
                        @csrf
                        <input type="hidden" name="plan_id" id="shift_plan_id" value="">
                        <h3 class="mb-3 fs-20 text-center text-capitalize">{{ translate('messages.Shift_Subscription') }}</h3>
                        <div class="border rounded-10 p-4">
                            <div class="p-xl-1 d-flex align-items-center mb-4 gap-2 justify-content-center flex-md-nowrap flex-wrap">
                                <div class="position-relative w-100 bg-light subscription-plan__card shift_subs-card rounded-10">
                                    <div class="d-flex align-items-center gap-2 p-3 plan-pro-head">
                                        <div class="w-40px mb-0">
                                            <img width="40"
                                                src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}"
                                                alt="img" class="rounded-circle">
                                        </div>
                                        <div class="text-start">
                                            <h3 class="mb-1 fs-24 fw-medium lh-1">{{ $subscription->plan_name }}</h3>
                                            <p class="mb-0 fs-32 font-semibold text-dark">
                                                @if ($subscription->plan_type === 'free_trial')
                                                    <span class="fs-20 fw-medium text-capitalize">{{ translate('messages.Free_Trial') }}</span>
                                                @else
                                                    {{ \App\CentralLogics\Helpers::format_currency((float) $subscription->plan_price) }}
                                                @endif
                                                <span class="fs-20 font-weight-light gray-dark">/ {{ optional($subscription->plan)->duration ?? '-' }} {{ translate('messages.days') }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <img width="26" src="{{ asset('public/assets/admin/img/convert-arrow.png') }}"
                                    alt="img" class="arrows d-md-block d-none">
                                <div class="position-relative w-100 subscription-plan__card shift_subs-card active rounded-10">
                                    <div class="d-flex align-items-center gap-2 p-3 plan-pro-head">
                                        <div class="w-40px mb-0">
                                            <img width="40"
                                                src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}"
                                                alt="img" class="rounded-circle">
                                        </div>
                                        <div class="text-start">
                                            <h3 class="mb-1 fs-24 fw-medium lh-1" id="shift_plan_name_label">-</h3>
                                            <p class="mb-0 fs-32 font-semibold text-dark">
                                                <span id="shift_plan_price_label">-</span>
                                                <span class="fs-20 font-weight-light gray-dark">/ <span id="shift_plan_duration_label">-</span> {{ translate('messages.days') }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="js-shift-payment-wrapper">
                                @include('admin-views.pro-customer.partials._payment-method-radios', [
                                    'customer' => $customer,
                                    'price'    => 0,
                                    'idPrefix' => 'shift',
                                ])
                            </div>
                            <div class="text-center pb-2 pt-2">
                                <p class="mb-3">{{ translate('messages.#Note_:_Ensure_payment_is_received_before_changing_or_renewing_the_subscription') }}</p>
                                <button type="submit"
                                    class="max-w-260px w-100 btn p-0 btn--primary renew-btn text-white py-2 px-3 text-capitalize">
                                    {{ translate('messages.Confirm_Shift') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Subscribe Now modal (no existing subscription) --}}
@if (($isCanceled || !$subscription) && $plans->count())
    <div class="modal fade" id="plan_subscribe_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <form method="post"
                        action="{{ route('admin.pro-customer.subscription.start', $customer->id) }}">
                        @csrf
                        <input type="hidden" name="plan_id" id="subscribe_plan_id"
                            value="{{ $plans->first()->id }}">
                        <h3 class="mb-3 fs-20 text-center text-capitalize">{{ translate('messages.Subscribe_Now') }}</h3>
                        <div class="border rounded-10">
                            <div class="bg-plan rounded-10">
                                <div class="rounded-4 bg-plan-gradient p-3 text-center plan-pro-head">
                                    <div class="w-40px mx-auto mb-10px">
                                        <img width="40"
                                            src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}"
                                            alt="img" class="rounded-circle">
                                    </div>
                                    <h3 class="mb-1 fs-18 fw-medium lh-1">{{ $proBrand }}</h3>
                                    <p class="mb-0 fs-14">{{ translate('messages.Save_more_on_every_order') }}</p>
                                </div>
                            </div>
                            <div class="p-20">
                                <p class="fs-14 mb-10px">{{ translate('messages.Select_Plan') }}</p>
                                <select class="custom-select mb-10px js-subscribe-plan-select">
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}"
                                            data-name="{{ $plan->plan_name }}"
                                            data-price="{{ (float) $plan->price }}"
                                            data-duration="{{ $plan->duration }}"
                                            data-type="{{ $plan->plan_type }}">
                                            {{ $plan->duration }} {{ translate('messages.Days') }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="bg-plan-monthly d-flex p-3 rounded align-items-center gap-2 justify-content-between mb-2">
                                    <div class="mb-0 d-flex gap-2 align-items-center flex-wrap">
                                        <h3 class="mb-0 fs-14 fw-medium lh-1 js-subscribe-plan-name"></h3>
                                        <span class="badge text-success bg-success bg-opacity-10 px-2 rounded-pill fs-12 js-subscribe-free-badge d-none">
                                            {{ translate('messages.Free_Trial') }}
                                        </span>
                                    </div>
                                    <h3 class="m-0 fs-25 js-subscribe-plan-price"></h3>
                                </div>
                                <div class="js-subscribe-payment-wrapper">
                                    @include('admin-views.pro-customer.partials._payment-method-radios', [
                                        'customer' => $customer,
                                        'price'    => (float) $plans->first()->price,
                                        'idPrefix' => 'subscribe',
                                    ])
                                </div>
                                <div class="text-center pt-2">
                                    <p class="mb-3">{{ translate('messages.#Note_:_Ensure_payment_is_received_before_starting_the_subscription') }}</p>
                                    <button type="submit"
                                        class="max-w-260px w-100 btn p-0 btn--primary renew-btn text-white py-2 px-3 text-capitalize">
                                        {{ translate('messages.Confirm_Subscription') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection

@push('script_2')
<script>
    "use strict";
    $(function () {
        var freeTrialLabel = "{{ translate('messages.Free_Trial') }}";
        var currencySymbol = "{{ \App\CentralLogics\Helpers::currency_symbol() }}";
        var currentPlanId = {{ (int) ($currentPlanId ?? 0) }};

        function formatPrice(planType, price) {
            if (planType === 'free_trial') {
                return '<span class="fs-16 fw-medium text-capitalize">' + freeTrialLabel + '</span>';
            }
            return currencySymbol + Number(price).toFixed(2);
        }

        function refreshPaymentMethod($wrapper, planType, planPrice) {
            var $section = $wrapper.find('.js-payment-method');
            if (!$section.length) return;

            if (planType === 'free_trial') {
                $wrapper.hide();
                $section.find('input[name="payment_method"]').prop('checked', false).prop('disabled', true);
                return;
            }
            $wrapper.show();

            var $wallet = $section.find('.js-pay-wallet');
            var $walletLabel = $section.find('.js-pay-wallet-label');
            var $manual = $section.find('input[value="manual"]');
            var $lowMsg = $section.find('.js-wallet-low-msg');

            // Re-enable manual in case it was disabled when a free_trial plan was previously selected
            $manual.prop('disabled', false);

            var balance = parseFloat($section.data('wallet-balance')) || 0;
            var hasEnough = balance >= parseFloat(planPrice || 0);

            $wallet.prop('disabled', !hasEnough);
            $walletLabel.toggleClass('opacity-50', !hasEnough);
            $lowMsg.toggleClass('d-none', hasEnough).toggleClass('d-block', !hasEnough);

            if (!hasEnough) {
                $wallet.prop('checked', false);
                $manual.prop('checked', true);
            } else if (!$wallet.is(':checked') && !$manual.is(':checked')) {
                $wallet.prop('checked', true);
            }
        }

        // Sync main modal UI from selected plan
        function syncMainModal() {
            var $opt = $('.js-plan-select option:selected');
            var planId = parseInt($opt.val());
            var planName = $opt.data('name');
            var planType = $opt.data('type');
            var planPrice = parseFloat($opt.data('price') || 0);
            var isCurrent = planId === currentPlanId;

            $('.js-modal-plan-name').text(planName);
            $('.js-modal-plan-price').html(formatPrice(planType, planPrice));
            $('.js-modal-current-badge').toggleClass('d-none', !isCurrent);

            if (isCurrent) {
                $('.js-do-renew').removeClass('d-none');
                $('.js-do-shift').addClass('d-none');
            } else {
                $('.js-do-renew').addClass('d-none');
                $('.js-do-shift').removeClass('d-none');
            }
        }

        $(document).on('change', '.js-plan-select', function () {
            syncMainModal();
        });

        $('#plan_modal_area').on('show.bs.modal', function () {
            syncMainModal();
        });

        // Renew button → open renew modal
        $(document).on('click', '.js-do-renew', function () {
            var $opt = $('.js-plan-select option:selected');
            var planId = parseInt($opt.val());
            var planType = $opt.data('type');
            var planPrice = parseFloat($opt.data('price') || 0);
            var planName = $opt.data('name');
            var planDuration = $opt.data('duration');

            $('#renew_plan_id').val(planId);
            $('#renew_plan_name_label').text(planName);
            $('#renew_plan_duration_label').text('/ ' + planDuration + ' {{ translate('messages.days') }}');
            $('#renew_plan_price_label').html(formatPrice(planType, planPrice));

            refreshPaymentMethod($('.js-renew-payment-wrapper'), planType, planPrice);
            $('#plan_modal_area').modal('hide');
            $('#plan_renew_subscription').modal('show');
        });

        // Shift button → open shift modal
        $(document).on('click', '.js-do-shift', function () {
            var $opt = $('.js-plan-select option:selected');
            var planId = parseInt($opt.val());
            var planName = $opt.data('name');
            var planPrice = parseFloat($opt.data('price') || 0);
            var planDuration = $opt.data('duration');
            var planType = $opt.data('type');

            $('#shift_plan_id').val(planId);
            $('#shift_plan_name_label').text(planName);
            $('#shift_plan_duration_label').text(planDuration);
            $('#shift_plan_price_label').html(formatPrice(planType, planPrice));

            refreshPaymentMethod($('.js-shift-payment-wrapper'), planType, planPrice);
            $('#plan_modal_area').modal('hide');
            $('#plan_shift_subscription').modal('show');
        });

        // Sync subscribe modal UI from selected plan
        function syncSubscribeModal() {
            var $opt = $('.js-subscribe-plan-select option:selected');
            var planId = parseInt($opt.val());
            var planName = $opt.data('name');
            var planType = $opt.data('type');
            var planPrice = parseFloat($opt.data('price') || 0);

            $('#subscribe_plan_id').val(planId);
            $('.js-subscribe-plan-name').text(planName);
            $('.js-subscribe-plan-price').html(formatPrice(planType, planPrice));
            $('.js-subscribe-free-badge').toggleClass('d-none', planType !== 'free_trial');
            refreshPaymentMethod($('.js-subscribe-payment-wrapper'), planType, planPrice);
        }

        $(document).on('change', '.js-subscribe-plan-select', function () {
            syncSubscribeModal();
        });

        $('#plan_subscribe_modal').on('show.bs.modal', function () {
            syncSubscribeModal();
        });

        // Init on load
        if ($('.js-plan-select').length) syncMainModal();
        if ($('.js-subscribe-plan-select').length) syncSubscribeModal();
    });
</script>
@endpush
