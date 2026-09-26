@extends('layouts.admin.app')

@section('title', translate('messages.Pro_Customer'))
@section('pro_customer_list', 'active')


@section('content')

@php
    $filtered = request()->hasAny(['plan_id', 'subscription_status', 'dates']);
@endphp

<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize fs-24">
                <span>{{ translate('messages.Pro_Customer') }}</span>
            </h1>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="card mb-3">
        <div class="card-body p-10px">
            <div class="row g-2">
                {{-- Total --}}
                <div class="col-lg-3 col-sm-6">
                    <div class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-info w-100 position-relative">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img width="20" src="{{ asset('public/assets/admin/img/customer-g.png') }}" alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">{{ $stats['total'] }}</h4>
                            <span class="subtitle fs-14 text-dark fw-normal text-capitalize">{{ translate('messages.Total_Subscriber') }}</span>
                        </div>
                        <button type="button" class="btn text-info text-left position-absolute right-0 top-0 m-2 p-0"
                            data-toggle="tooltip" data-placement="top" data-html="true"
                            data-title="<div class='text-start'>{{ translate('messages.Plan_Wise_Subscribers') }}<br>@foreach($plans as $plan)<span class='d-flex align-items-center gap-1'>{{ $plan->plan_name }}: <b>{{ (int)($stats['plan_wise']['total'][$plan->id] ?? 0) }}</b></span>@endforeach</div>">
                            <i class="tio-info fs-14"></i>
                        </button>
                    </div>
                </div>
                {{-- Active --}}
                <div class="col-lg-3 col-sm-6">
                    <div class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-success w-100 position-relative">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img width="20" src="{{ asset('public/assets/admin/img/customer-a.png') }}" alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">{{ $stats['active'] }}</h4>
                            <span class="subtitle fs-14 text-dark fw-normal text-capitalize">{{ translate('messages.Active_Subscriber') }}</span>
                        </div>
                        <button type="button" class="btn text-info text-left position-absolute right-0 top-0 m-2 p-0"
                            data-toggle="tooltip" data-placement="top" data-html="true"
                            data-title="<div class='text-start'>{{ translate('messages.Plan_Wise_Subscribers') }}<br>@foreach($plans as $plan)<span class='d-flex align-items-center gap-1'>{{ $plan->plan_name }}: <b>{{ (int)($stats['plan_wise']['active'][$plan->id] ?? 0) }}</b></span>@endforeach</div>">
                            <i class="tio-info fs-14"></i>
                        </button>
                    </div>
                </div>
                {{-- Inactive --}}
                <div class="col-lg-3 col-sm-6">
                    <div class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-danger w-100 position-relative">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img width="20" src="{{ asset('public/assets/admin/img/customer-inactive.png') }}" alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">{{ $stats['inactive'] }}</h4>
                            <span class="subtitle fs-14 text-dark fw-normal text-capitalize">{{ translate('messages.Inactive_Subscriber') }}</span>
                        </div>
                        <button type="button" class="btn text-info text-left position-absolute right-0 top-0 m-2 p-0"
                            data-toggle="tooltip" data-placement="top" data-html="true"
                            data-title="<div class='text-start'>{{ translate('messages.Plan_Wise_Subscribers') }}<br>@foreach($plans as $plan)<span class='d-flex align-items-center gap-1'>{{ $plan->plan_name }}: <b>{{ (int)($stats['plan_wise']['inactive'][$plan->id] ?? 0) }}</b></span>@endforeach</div>">
                            <i class="tio-info fs-14"></i>
                        </button>
                    </div>
                </div>
                {{-- New --}}
                <div class="col-lg-3 col-sm-6">
                    <div class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-warning w-100 position-relative">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img width="20" src="{{ asset('public/assets/admin/img/customer-add.png') }}" alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">{{ $stats['new'] }}</h4>
                            <span class="subtitle fs-14 text-dark fw-normal text-capitalize">{{ translate('messages.New_Subscriber') }}</span>
                        </div>
                        <span class="text-info position-absolute right-0 top-0 m-2"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Active_customers_who_joined_in_the_last_2_months') }}">
                            <i class="tio-info fs-14"></i>
                        </span>
                    </div>
                </div>
                {{-- Total Earned --}}
                <div class="col-lg-6 col-sm-6">
                    <div class="bg--F6F6F6 shadow-effect-hover rounded d-flex align-items-center justify-content-between gap-2 flex-wrap px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                                <img width="20" src="{{ asset('public/assets/admin/img/bank-hand.png') }}" alt="img">
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <h4 class="title fs-14 fw-500 text-dark mb-0 text-capitalize">{{ translate('messages.Total_Earned') }}</h4>
                                <button type="button" class="btn text-info text-left p-0"
                                    data-toggle="tooltip" data-placement="top" data-html="true"
                                    data-title="<div class='text-start'>{{ translate('messages.Plan_Wise_Total_Earned') }}<br>@foreach($plans as $plan)<span class='d-flex align-items-center gap-1'>{{ $plan->plan_name }}: <b>{{ \App\CentralLogics\Helpers::format_currency((float)($stats['plan_wise']['earned'][$plan->id] ?? 0)) }}</b></span>@endforeach</div>">
                                    <i class="tio-info fs-14"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-20px">
                            <h4 class="mb-0">{{ \App\CentralLogics\Helpers::format_currency($stats['total_earned']) }}</h4>
                            <img width="20" src="{{ asset('public/assets/admin/img/arrow-trend-up.png') }}" alt="img">
                        </div>
                    </div>
                </div>
                {{-- Earned Last 30 Days --}}
                <div class="col-lg-6 col-sm-6">
                    <div class="bg--F6F6F6 shadow-effect-hover rounded d-flex align-items-center justify-content-between gap-2 flex-wrap px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                                <img width="20" src="{{ asset('public/assets/admin/img/days-calender.png') }}" alt="img">
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <h4 class="title fs-14 fw-500 text-dark mb-0 text-capitalize">{{ translate('messages.Earned_Last_30_days') }}</h4>
                                <button type="button" class="btn text-info text-left p-0"
                                    data-toggle="tooltip" data-placement="top" data-html="true"
                                    data-title="<div class='text-start'>{{ translate('messages.Plan_Wise_Earned_Last_30_days') }}<br>@foreach($plans as $plan)<span class='d-flex align-items-center gap-1'>{{ $plan->plan_name }}: <b>{{ \App\CentralLogics\Helpers::format_currency((float)($stats['plan_wise']['earned_last_30'][$plan->id] ?? 0)) }}</b></span>@endforeach</div>">
                                    <i class="tio-info fs-14"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-20px">
                            <h4 class="mb-0">{{ \App\CentralLogics\Helpers::format_currency($stats['earned_last_30']) }}</h4>
                            <img width="20" src="{{ asset('public/assets/admin/img/arrow-trend-up.png') }}" alt="img">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="card">
        <div class="card-header w-100 gap-2 justify-content-between flex-wrap pt-4 border-0">
            {{-- Tabs --}}
            <div class="js-nav-scroller hs-nav-scroller-horizontal mb-0">
                <ul class="nav nav-tabs border-0 nav--tabs nav--pills nav--theme-version">
                    <li class="nav-item">
                        <a class="nav-link text-capitalize {{ !request('tab') || request('tab') === 'all' ? 'active' : '' }}"
                            href="{{ route('admin.pro-customer.list', array_merge(request()->except('tab', 'page'), ['tab' => 'all'])) }}">
                            {{ translate('messages.All') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-capitalize {{ request('tab') === 'active' ? 'active' : '' }}"
                            href="{{ route('admin.pro-customer.list', array_merge(request()->except('tab', 'page'), ['tab' => 'active'])) }}">
                            {{ translate('messages.Active') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-capitalize {{ request('tab') === 'expired' ? 'active' : '' }}"
                            href="{{ route('admin.pro-customer.list', array_merge(request()->except('tab', 'page'), ['tab' => 'expired'])) }}">
                            {{ translate('messages.Expired') }} / {{ translate('messages.Canceled') }}
                        </a>
                    </li>
                </ul>
            </div>

            <div class="search--button-wrapper gap-2 justify-content-lg-end">
                <form class="search-form" method="get" action="{{ route('admin.pro-customer.list') }}">
                    @foreach(request()->except('search', 'page') as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? implode(',', $v) : $v }}">
                    @endforeach
                    <div class="input-group input--group">
                        <input id="datatableSearch" type="search" name="search" value="{{ request('search') }}"
                            class="form-control" placeholder="{{ translate('messages.Search_customer_or_plan') }}" aria-label="Search">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>

                <a class="btn btn-outline-primary btn-white filter-button-show h--40px px-4 w-max-content offcanvas-trigger position-relative"
                    data-target="#pro-customer-list-offcanvas" href="javascript:;">
                    <i class="tio-tune-horizontal mr-1"></i> {{ translate('messages.Filter') }}
                    @if($filtered)
                        <span class="badge badge-success badge-pill position-absolute" style="top:-6px;right:-6px;min-width:16px;height:16px;font-size:10px;padding:2px 4px;">&nbsp;</span>
                    @endif
                </a>

                <!-- Unfold -->
                <div class="hs-unfold">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                        data-hs-unfold-options='{
                                "target": "#proCustomerExportDropdown",
                                "type": "css-animation"
                            }'>
                        <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                    </a>

                    <div id="proCustomerExportDropdown"
                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                        <a id="export-excel" class="dropdown-item" href="{{ route('admin.pro-customer.export', ['type' => 'excel', request()->getQueryString()]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                alt="Image Description">
                            {{ translate('messages.excel') }}
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{ route('admin.pro-customer.export', ['type' => 'csv', request()->getQueryString()]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                alt="Image Description">
                            {{ translate('messages.csv') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-xxl-20 px-3">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0 text-capitalize">{{ translate('messages.SL') }}</th>
                            <th class="border-0 text-capitalize">{{ translate('messages.Customer_Info') }}</th>
                            <th class="border-0 text-capitalize">{{ translate('messages.Plan_Validity') }}</th>
                            <th class="border-0 text-capitalize">{{ translate('messages.Plan_Name') }}</th>
                            <th class="border-0 text-capitalize">{{ translate('messages.Plan_Price') }}</th>
                            <th class="border-0 text-center text-capitalize">{{ translate('messages.Status') }}</th>
                            <th class="border-0 text-center text-capitalize">
                                <div class="d-flex align-items-center gap-1 justify-content-center">
                                    {{ translate('messages.Total_Orders') }}
                                    <span data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.Orders_placed_during_this_subscription') }}">
                                        <i class="tio-info fs-14 text-muted"></i>
                                    </span>
                                </div>
                            </th>
                            <th class="border-0 text-center text-capitalize">{{ translate('messages.Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscriptions as $k => $sub)
                            @php($user = $sub->user)
                            <tr>
                                <td>{{ $k + $subscriptions->firstItem() }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 min-w-220 max-w-220px">
                                        @include('partials._user-avatar', [
                                            'imageUrl'    => $user?->image_full_url,
                                            'proStatus'   => $user?->pro_status ?? true,
                                            'size'        => 40,
                                            'placeholder' => asset('public/assets/admin/img/placeholder.png'),
                                            'alt'         => $user?->f_name,
                                        ])
                                        <div>
                                            @if($user)
                                                @php($fullName = trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')))
                                                <a href="{{ route('admin.users.customer.subscription-plan', $user->id) }}"
                                                    class="text-dark fw-500 text-hover-primary max-w-215px min-w-135px text-wrap line--limit-1"
                                                    title="{{ $fullName ?: translate('messages.N/A') }}">
                                                    {{ $fullName ? \Illuminate\Support\Str::limit($fullName, 25) : translate('messages.N/A') }}
                                                </a>
                                                <div class="fs-12 text-body-light">
                                                    <a class="gray-dark text-hover-primary" href="mailto:{{ $user->email }}" title="{{ $user->email }}">{{ \Illuminate\Support\Str::limit($user->email, 25) }}</a>
                                                </div>
                                                <div class="fs-12 text-body-light">{{ $user->phone }}</div>
                                            @else
                                                <span class="text-muted">{{ translate('messages.N/A') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark fs-12">
                                        <div>{{ $sub->start_at ? \App\CentralLogics\Helpers::time_date_format($sub->start_at) : '-' }}</div>
                                        <div>{{ $sub->end_at ? \App\CentralLogics\Helpers::time_date_format($sub->end_at) : '-' }}</div>
                                    </div>
                                </td>
                                <td class="text-dark">{{ $sub->plan_name }}</td>
                                <td class="text-dark">
                                    @if($sub->plan_type === 'free_trial')
                                        <span class="badge badge-soft-success">{{ translate('messages.Free_Trial') }}</span>
                                    @else
                                        {{ \App\CentralLogics\Helpers::format_currency($sub->plan_price) }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $sub->status === 'active' ? 'badge-soft-success' : ($sub->status === 'expired' ? 'badge-soft-danger' : 'badge-soft-secondary') }} text-capitalize">
                                        {{ translate('messages.' . $sub->status) }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $sub->total_orders ?? 0 }}</td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center">
                                        @if($user)
                                            <a class="btn action-btn btn--primary btn-outline-primary"
                                                href="{{ route('admin.users.customer.subscription-plan', $user->id) }}"
                                                title="{{ translate('messages.View_Subscription_Plan') }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                        @endif
                                        @if($sub->status === 'active' || $sub->status === 'expired')
                                            <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                                                href="javascript:"
                                                data-id="pro-sub-cancel-{{ $sub->id }}"
                                                data-message="{{ translate('messages.Want_to_cancel_this_subscription') }}?"
                                                title="{{ translate('messages.Cancel_Subscription') }}">
                                                <i class="tio-clear"></i>
                                            </a>
                                            <form action="{{ route('admin.pro-customer.subscription.cancel', $sub->id) }}"
                                                method="post" id="pro-sub-cancel-{{ $sub->id }}">
                                                @csrf
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($subscriptions->isEmpty())
            <div class="empty--data text-center py-5 my-4 bg-light2 rounded mx-3">
                <img src="{{ asset('public/assets/admin/img/empty.png') }}" alt="empty" style="max-width:140px;" class="mb-3">
                <h5 class="fs-16 mb-1 text-capitalize">{{ translate('messages.No_Data_Found') }}</h5>
            </div>
        @endif

        <div class="page-area mt-3 px-3 pb-3">{!! $subscriptions->withQueryString()->links() !!}</div>
    </div>
</div>

{{-- Filter Offcanvas --}}
<div id="pro-customer-list-offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 480px">
    <div>
        <form id="pro-sub-filter-form" action="{{ route('admin.pro-customer.list') }}" method="GET">
            @if(request('tab'))<input type="hidden" name="tab" value="{{ request('tab') }}">@endif
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                <div class="px-3 py-3 d-flex justify-content-between w-100">
                    <h2 class="mb-0 fs-18 text-title font-medium text-capitalize">{{ translate('messages.Filter') }}</h2>
                    <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-20">
                <div class="d-flex flex-column gap-20px">
                    <div class="bg-light p-xxl-20 p-3 rounded">
                        <div class="mb-20">
                            <label class="form-label fw-400 text-capitalize">{{ translate('messages.Validity_Date_Range') }}</label>
                            <div class="position-relative bg-white rounded">
                                <i class="tio-calendar-month icon-absolute-on-right"></i>
                                <input type="text" class="form-control h-45 position-relative bg-transparent"
                                    name="dates" value="{{ request('dates') }}" placeholder="{{ translate('messages.Select_Date') }}"
                                    data-no-global-daterangepicker>
                            </div>
                        </div>
                        <div class="mb-20">
                            <label class="form-label fw-400 text-capitalize">{{ translate('messages.Plan') }}</label>
                            <select name="plan_id" class="form-control js-select2-custom">
                                <option value="">{{ translate('messages.All_Plans') }}</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ request('plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->plan_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label fw-400 text-capitalize">{{ translate('messages.Subscription_Status') }}</label>
                            <select name="subscription_status" class="form-control js-select2-custom">
                                <option value="">{{ translate('messages.All') }}</option>
                                <option value="active" {{ request('subscription_status') === 'active' ? 'selected' : '' }}>{{ translate('messages.Active') }}</option>
                                <option value="expired" {{ request('subscription_status') === 'expired' ? 'selected' : '' }}>{{ translate('messages.Expired') }}</option>
                                <option value="canceled" {{ request('subscription_status') === 'canceled' ? 'selected' : '' }}>{{ translate('messages.Canceled') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center offcanvas-footer p-3 position-sticky">
        <a href="{{ route('admin.pro-customer.list') }}" class="btn w-100 btn--reset offcanvas-close text-capitalize">{{ translate('messages.Reset') }}</a>
        <button type="submit" form="pro-sub-filter-form" class="btn w-100 btn--primary text-capitalize">{{ translate('messages.Apply') }}</button>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
<script>"use strict";
$(function () {
    if ($.fn.daterangepicker) {
        $('input[name="dates"]').daterangepicker({
            autoUpdateInput: false,
            opens: 'left',
            locale: { format: 'MM/DD/YYYY', cancelLabel: 'Clear' }
        });
        $('input[name="dates"]').on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
        });
        $('input[name="dates"]').on('cancel.daterangepicker', function () {
            $(this).val('');
        });
    }
});
</script>
@endpush
