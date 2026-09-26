@extends('layouts.admin.app')

@section('title', translate('messages.Price_Setup'))
@section('pro_customer_price_setup', 'active')

@section('content')
<div class="content container-fluid">
    <div class="page-header mb-1">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize fs-24">
                <span>{{ translate('messages.Price_Setup') }}</span>
            </h1>
        </div>
    </div>

    @if($plans->isNotEmpty())
    <div class="card mb-20">
        <div class="card-body">
            <div class="d-flex gap-2 align-items-center justify-content-between mb-20">
                <div>
                    <h3 class="mb-1 fs-16">{{ translate('messages.subscription_price') }}</h3>
                    <p class="mb-0 gray-dark fs-12">{{ translate('messages.Manage subscription packages here') }}</p>
                </div>
                <button type="button" class="btn btn--primary text-nowrap px-3 offcanvas-trigger" data-target="#offcanvas__createplan">
                    <i class="tio-add-circle"></i> {{ translate('messages.add_plan') }}
                </button>
            </div>


                <div class="row g-3">
                    @foreach($plans as $plan)
                        <div class="col-md-6 col-lg-4">
                            <div class="bg-light2 subscription-plan__card p-xl-20 p-3 rounded h-100">
                                <div class="d-flex gap-2 align-items-center justify-content-between">
                                    <div class="w-40px d-flex align-items-center justify-content-center">
                                        @if($plan->plan_type === 'free_trial')
                                            <img width="40" height="40" src="{{ asset('public/assets/admin/img/for-free.png') }}" alt="free trial">
                                        @else
                                            <img width="40" height="40" src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}" alt="paid plan" class="rounded-circle">
                                        @endif
                                    </div>
                                    <div class="bg-white rounded p-2 d-flex align-items-center gap-4">
                                        <a href="javascript:" class="btn outline-none border-0 p-0 pe--12 text-danger form-alert"
                                            data-id="pro-plan-delete-{{ $plan->id }}"
                                            data-message="{{ translate('messages.want_to_delete_this_plan') }}?"
                                            title="{{ translate('messages.delete') }}">
                                            <img src="{{ asset('public/assets/admin/img/trash-stroke.svg') }}" alt="img" class="svg">
                                        </a>
                                        <a href="javascript:" class="btn outline-none border-0 p-0 text-primary pro-plan-edit-trigger"
                                            data-target="#offcanvas__editplan-{{ $plan->id }}"
                                            title="{{ translate('messages.edit') }}">
                                            <img src="{{ asset('public/assets/admin/img/bx-edit.svg') }}" alt="img" class="svg">
                                        </a>
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox" class="toggle-switch-input pro-plan-status-toggle"
                                                data-url-on="{{ route('admin.pro-customer.plan.status', [$plan->id, 1]) }}"
                                                data-url-off="{{ route('admin.pro-customer.plan.status', [$plan->id, 0]) }}"
                                                {{ $plan->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <form action="{{ route('admin.pro-customer.plan.delete', $plan->id) }}" method="post"
                                    id="pro-plan-delete-{{ $plan->id }}">
                                    @csrf @method('delete')
                                </form>
                                <div class="pt-4 mt-1">
                                    <span class="badge {{ $plan->plan_type === 'free_trial' ? 'badge-soft-success' : 'badge-soft-primary' }} mb-2">
                                        {{ $plan->plan_type === 'free_trial' ? translate('messages.Free_Trial') : translate('messages.Paid') }}
                                    </span>
                                    <h3 class="mb-2 fs-24 fw-500 lh-1">{{ $plan->plan_name }}</h3>
                                    <p class="mb-0 fs-32 font-semibold text-dark">
                                        @if($plan->plan_type === 'free_trial')
                                            {{ translate('messages.free') }}
                                        @else
                                            {{ \App\CentralLogics\Helpers::format_currency($plan->price) }}
                                        @endif
                                        <span class="fs-20 fw-400 gray-dark">/{{ $plan->duration }} {{ translate('messages.days') }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        @include('admin-views.pro-customer.partials._plan-edit-offcanvas', ['plan' => $plan, 'language' => $language])
                    @endforeach
                </div>
                @if($plans->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {!! $plans->links() !!}
                    </div>
                @endif
            </div>
        </div>
        @else
            <div class="card py-4">
                <div class="card-body py-5 my-5">
                    <div class="text-center py-5">
                        <div class="mb-20">
                            <div class="w-40px mx-auto mb-20">
                                <img width="40" src="{{ asset('public/assets/admin/img/subscription-win-badge.png') }}" alt="img" class="rounded-circle">
                            </div>
                            <h3 class="mb-1 fs-16">{{ translate('messages.add_subscription_plan') }}</h3>
                            <p class="mb-0 gray-dark fs-12">{{ translate('messages.No subscription plans added yet') }}</p>
                        </div>
                        <button type="button" class="btn btn--primary text-nowrap px-3 offcanvas-trigger" data-target="#offcanvas__createplan">
                            <i class="tio-add-circle"></i> {{ translate('messages.add_plan') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
</div>

@include('admin-views.pro-customer.partials._plan-create-offcanvas')

<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
<script>
    "use strict";

    function bindPlanTypeToggle(scope) {
        $(scope).find('.pro-plan-type').off('change.proPlan').on('change.proPlan', function () {
            var $form = $(this).closest('form');
            var $price = $form.find('input[name="price"]');
            if ($(this).val() === 'free_trial') {
                $price.prop('readonly', true).prop('required', false).val(0);
            } else {
                $price.prop('readonly', false).prop('required', true);
            }
        });
        $(scope).find('.pro-plan-type:checked').trigger('change.proPlan');
    }

    $(function () {
        bindPlanTypeToggle('#offcanvas__createplan');
        $('[id^="offcanvas__editplan-"]').each(function () {
            bindPlanTypeToggle(this);
        });
    });

    $(document).on('click', '.pro-plan-edit-reset', function () {
        var $offcanvas = $(this).closest('.custom-offcanvas');
        $offcanvas.find('form')[0].reset();
        bindPlanTypeToggle($offcanvas[0]);
        $offcanvas.find('input[name="plan_name[]"]').trigger('input.proPlanName');
    });

    $(document).on('change', '.pro-plan-status-toggle', function () {
        window.location.href = this.checked ? $(this).data('url-on') : $(this).data('url-off');
    });

    $(document).on('click', '.pro-plan-edit-trigger', function () {
        var target = $(this).data('target');
        $(target).addClass('open');
        $('#offcanvasOverlay').addClass('show');
    });

    function bindPlanNameCounter(scope) {
        $(scope).find('input[name="plan_name[]"]').off('input.proPlanName').on('input.proPlanName', function () {
            var max = parseInt($(this).attr('maxlength'), 10) || 70;
            var len = $(this).val().length;
            $(this).closest('.lang_form, .form-group').find('.pro-plan-name-counter').text(len + ' / ' + max);
        }).trigger('input.proPlanName');
    }

    $(function () {
        bindPlanNameCounter('#offcanvas__createplan');
        $('[id^="offcanvas__editplan-"]').each(function () {
            bindPlanNameCounter(this);
        });
    });

    $(document).on('submit', '#pro-plan-create-form, form[id^="pro-plan-edit-form-"]', function (e) {
        var $form = $(this);
        var $default = $form.find('.plan-name-default');
        if ($default.length && $default.val().trim() === '') {
            e.preventDefault();
            var $scope = $form.closest('.custom-offcanvas');
            $scope.find('.lang_link').removeClass('active').first().addClass('active');
            $scope.find('.lang_form').addClass('d-none').first().removeClass('d-none');
            if (window.toastr) {
                toastr.error("{{ translate('messages.default_plan_name_is_required') }}");
            }
            $default.trigger('focus');
        }
    });
</script>
@endpush
