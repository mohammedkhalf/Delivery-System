@extends('layouts.admin.app')

@section('title', translate('messages.Pro_Customer_Transactions'))
@section('pro_customer_transactions', 'active')

@section('content')
<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize fs-24">
                <span>{{ translate('messages.Pro_Customer_Transactions') }}</span>
            </h1>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-block border-0">
            <div class="search--button-wrapper gap-2 justify-content-lg-end">
                <form class="search-form flex-grow-1" method="get" action="{{ route('admin.pro-customer.transactions') }}">
                    <div class="input-group input--group max-w-280">
                        <input id="datatableSearch" type="search" name="search" value="{{ request('search') }}"
                            class="form-control" placeholder="{{ translate('messages.Search_by_ID_customer_or_plan') }}" aria-label="Search">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>

                <a class="btn btn-outline-primary btn-white filter-button-show h--40px px-4 w-max-content offcanvas-trigger position-relative"
                    data-target="#pro-tx-filter-offcanvas" href="javascript:;">
                    <i class="tio-tune-horizontal mr-1"></i> {{ translate('messages.Filter') }}
                    @if($filtered)
                        <span class="badge badge-success badge-pill position-absolute" style="top:-6px;right:-6px;min-width:16px;height:16px;font-size:10px;padding:2px 4px;">&nbsp;</span>
                    @endif
                </a>

                <div class="hs-unfold">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                        data-hs-unfold-options='{"target":"#proTxExportDropdown","type":"css-animation"}'>
                        <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                    </a>
                    <div id="proTxExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                        <a class="dropdown-item" href="{{ route('admin.pro-customer.transaction.export', array_merge(request()->all(), ['type' => 'excel'])) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                            {{ translate('messages.excel') }}
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.pro-customer.transaction.export', array_merge(request()->all(), ['type' => 'csv'])) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                            {{ translate('messages.csv') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-xxl-20 px-3">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-dark">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('messages.SL') }}</th>
                            <th class="border-0">{{ translate('messages.Transaction_ID') }}</th>
                            <th class="border-0">{{ translate('messages.Transaction_Date') }}</th>
                            <th class="border-0">{{ translate('messages.Customer_Info') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Plan_Name') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Plan_Price') }}</th>
                            <th class="border-0">{{ translate('messages.Plan_Validity') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Payment_Method') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $k => $tx)
                            @php($user = $tx->user)
                            <tr>
                                <td>{{ $k + $transactions->firstItem() }}</td>
                                <td>
                                    <span class="text-dark fw-500">#{{ $tx->id }}</span>
                                </td>
                                <td>
                                    <div>{{ $tx->paid_at ? \App\CentralLogics\Helpers::date_format($tx->paid_at) : '-' }}</div>
                                    <div class="fs-12 text-body-light">{{ $tx->paid_at ? \App\CentralLogics\Helpers::time_format($tx->paid_at) : '' }}</div>
                                </td>
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
                                            @php($fullName = $user ? trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')) : '')
                                            <a href="{{ route('admin.users.customer.view', $user?->id) }}"
                                                class="text-dark fw-500 text-hover-primary max-w-215px text-wrap line--limit-1"
                                                title="{{ $fullName ?: translate('messages.N/A') }}">
                                                {{ $fullName ? \Illuminate\Support\Str::limit($fullName, 25) : translate('messages.N/A') }}
                                            </a>
                                            <div class="fs-12 text-body-light" title="{{ $user?->email }}">{{ \Illuminate\Support\Str::limit($user?->email ?? '', 25) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">{{ $tx->plan_name }}</td>
                                <td class="text-center">
                                    @if($tx->plan_type === 'free_trial')
                                        {{ translate('messages.free') }}
                                    @else
                                        {{ \App\CentralLogics\Helpers::format_currency($tx->plan_price) }}
                                    @endif
                                </td>
                                <td>
                                    <div class="fs-12">
                                        <div>{{ $tx->start_at ? \App\CentralLogics\Helpers::date_format($tx->start_at) : '-' }}</div>
                                        <div>{{ $tx->end_at ? \App\CentralLogics\Helpers::date_format($tx->end_at) : '-' }}</div>
                                    </div>
                                </td>
                                <td class="text-center text-capitalize">{{ str_replace('_', ' ', $tx->payment_method ?? '-') }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $tx->payment_status === 'success' ? 'badge-soft-success' : ($tx->payment_status === 'pending' ? 'badge-soft-warning' : 'badge-soft-danger') }} text-capitalize">
                                        {{ translate('messages.' . $tx->payment_status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($transactions->isEmpty())
            <div class="empty--data text-center py-5 my-4 bg-light2 rounded mx-3">
                <img src="{{ asset('public/assets/admin/img/empty.png') }}" alt="empty" style="max-width:140px;" class="mb-3">
                <h5 class="fs-16 mb-1 text-capitalize">{{ translate('messages.No_Data_Found') }}</h5>
            </div>
        @endif

        <div class="page-area mt-3 px-3 pb-3">{!! $transactions->links() !!}</div>
    </div>
</div>

{{-- Filter Offcanvas --}}
<div id="pro-tx-filter-offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 480px">
    <div>
        <form id="pro-tx-filter-form" action="{{ route('admin.pro-customer.transactions') }}" method="GET">
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                <div class="px-3 py-3 d-flex justify-content-between w-100">
                    <h2 class="mb-0 fs-18 text-title font-medium text-capitalize">{{ translate('messages.Transaction_Filter') }}</h2>
                    <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-20">
                <div class="d-flex flex-column gap-20px">
                    <div class="bg-light p-xxl-20 p-3 rounded">
                        <div class="mb-20">
                            <label class="form-label fw-400 text-capitalize">{{ translate('messages.Transaction_Date_Range') }}</label>
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
                            <label class="form-label fw-400 text-capitalize">{{ translate('messages.Plan_Type') }}</label>
                            <select name="plan_type" class="form-control js-select2-custom">
                                <option value="">{{ translate('messages.All') }}</option>
                                <option value="paid" {{ request('plan_type') === 'paid' ? 'selected' : '' }}>{{ translate('messages.Paid') }}</option>
                                <option value="free_trial" {{ request('plan_type') === 'free_trial' ? 'selected' : '' }}>{{ translate('messages.Free_Trial') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center offcanvas-footer p-3 position-sticky">
        <a href="{{ route('admin.pro-customer.transactions') }}" class="btn w-100 btn--reset offcanvas-close text-capitalize">{{ translate('messages.Reset') }}</a>
        <button type="submit" form="pro-tx-filter-form" class="btn w-100 btn--primary text-capitalize">{{ translate('messages.Apply') }}</button>
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
