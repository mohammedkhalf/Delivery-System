@extends('layouts.admin.app')

@section('title', translate('Ride-Share Expense Report'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/report.png') }}" class="w--22" alt="">
                </span>
                <span>
                    {{ translate('Ride-Share Expense Report') }}
                </span>
            </h1>
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <h4 class="mb-3">{{ translate('Filter Data') }}</h4>
                <form action="{{ route('admin.transactions.report.set-date') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id">
                                <option value="all">{{ translate('messages.All_Zones') }}</option>
                                @foreach (\App\Models\Zone::orderBy('name')->get() as $z)
                                    <option value="{{ $z['id'] }}"
                                        {{ isset($zone) && $zone->id == $z['id'] ? 'selected' : '' }}>
                                        {{ $z['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select name="customer_id"
                                data-placeholder="{{ translate('messages.select_customer') }}"
                                class="js-data-example-ajax-2 form-control set-filter" data-url="{{ url()->full() }}" data-filter="customer_id">
                                @if (isset($customer))
                                    <option value="{{ $customer->id }}" selected>{{ $customer->f_name . ' ' .$customer->l_name }}</option>
                                @else
                                    <option value="all" selected>{{ translate('messages.all_customers') }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="type" name="type">
                                <option value="all" {{ isset($type) && $type == 'all' ? 'selected' : '' }}>{{ translate('messages.All Type') }}</option>
                                @php
                                    // Created in Modules/RideShare/Traits/TransactionManagement/TransactionTrait.php
                                    // (ride_id set). RideShare does NOT generate CashBack/referral.
                                    $rideshareTypes = [
                                        'coupon_discount'   => 'messages.coupon_discount',
                                        'discount_on_ride'  => 'messages.discount_on_ride',
                                        'pro_discount'      => 'messages.pro_discount',
                                    ];
                                @endphp
                                @foreach ($rideshareTypes as $value => $label)
                                    <option value="{{ $value }}" {{ ($type ?? '') === $value ? 'selected' : '' }}>{{ translate($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="filter" name="filter">
                                <option value="all_time" {{ isset($filter) && $filter == 'all_time' ? 'selected' : '' }}>{{ translate('messages.All Time') }}</option>
                                <option value="this_year" {{ isset($filter) && $filter == 'this_year' ? 'selected' : '' }}>{{ translate('messages.This Year') }}</option>
                                <option value="previous_year" {{ isset($filter) && $filter == 'previous_year' ? 'selected' : '' }}>{{ translate('messages.Previous Year') }}</option>
                                <option value="this_month" {{ isset($filter) && $filter == 'this_month' ? 'selected' : '' }}>{{ translate('messages.This Month') }}</option>
                                <option value="this_week" {{ isset($filter) && $filter == 'this_week' ? 'selected' : '' }}>{{ translate('messages.This Week') }}</option>
                                <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>{{ translate('messages.Custom') }}</option>
                            </select>
                        </div>
                        @if (isset($filter) && $filter == 'custom')
                            <div class="col-sm-6 col-md-3">
                                <input type="date" name="from" id="from_date" class="form-control" placeholder="{{ translate('Start Date') }}" {{ session()->has('from_date') ? 'value=' . session('from_date') : '' }} required>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <input type="date" name="to" id="to_date" class="form-control" placeholder="{{ translate('End Date') }}" {{ session()->has('to_date') ? 'value=' . session('to_date') : '' }} required>
                            </div>
                        @endif
                        <div class="col-sm-6 col-md-3 ml-auto">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn--primary h--45px min-w-100px">{{ translate('Filter') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h3 class="card-title d-flex align-items-center gap-2">
                        {{ translate('messages.expense_lists') }}
                        <span class="badge badge-soft-secondary" id="countItems">{{ $expense->total() }}</span>
                    </h3>
                    <form class="search-form theme-style">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input name="search" type="search" value="{{ request()?->search ?? null}}" class="form-control" placeholder="{{ translate('Search by Ride ID') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @if(request()->input('search'))
                        <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}">{{translate('messages.reset')}}</button>
                    @endif
                    <div class="hs-unfold ml-3">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                            href="javascript:;"
                            data-hs-unfold-options="{ &quot;target&quot;: &quot;#usersExportDropdown&quot;, &quot;type&quot;: &quot;css-animation&quot; }"
                            data-hs-unfold-target="#usersExportDropdown" data-hs-unfold-invoker="">
                            <i class="tio-download-to mr-1"></i> {{ translate('export') }}
                        </a>
                        <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right hs-unfold-content-initialized hs-unfold-css-animation animated hs-unfold-reverse-y hs-unfold-hidden">
                            <span class="dropdown-header">{{ translate('download_options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.transactions.report.rideshare-expense-export', ['export_type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="Image Description">
                                {{ translate('messages.excel') }}
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.transactions.report.rideshare-expense-export', ['export_type'=>'csv',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="Image Description">
                                {{ translate('messages.csv') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless middle-align __txt-14px">
                        <thead class="thead-light white--space-false">
                            <tr>
                                <th class="border-0">{{translate('sl')}}</th>
                                <th class="border-0">{{translate('ride_id')}}</th>
                                <th class="border-0">{{translate('Date & Time')}}</th>
                                <th class="border-0">{{ translate('Expense Type') }}</th>
                                <th class="text-center">{{ translate('Customer Name') }}</th>
                                <th class="border-0 text-right pr-xl-5">
                                    <div class="pr-xl-5">{{translate('expense amount')}}</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($expense as $key => $exp)
                            <tr>
                                <td scope="row">{{$key+$expense->firstItem()}}</td>
                                <td>
                                    @if ($exp->ride)
                                        <div>
                                            <a class="text-dark" href="{{ route('admin.ride-share.ride.show', $exp->ride->ref_id) }}">{{ $exp->ride->ref_id }}</a>
                                        </div>
                                    @else
                                        <label class="badge badge-primary">{{translate('messages.Other_Expenses')}}</label>
                                    @endif
                                </td>
                                <td>{{date('Y-m-d '.config('timeformat'),strtotime($exp->created_at))}}</td>
                                <td><label>{{ucwords(translate("messages.{$exp['type']}"))}}</label></td>
                                <td class="text-center">
                                    @if ($exp?->ride?->customer)
                                        {{ $exp?->ride?->customer?->f_name . ' ' . $exp?->ride?->customer?->l_name }}
                                    @else
                                        <label class="badge badge-danger">{{translate('messages.invalid_customer_data')}}</label>
                                    @endif
                                </td>
                                <td class="text-right pr-xl-5">
                                    <div class="pr-xl-5">{{\App\CentralLogics\Helpers::format_currency($exp['amount'])}}</div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($expense) !== 0)
                    <hr>
                    <div class="page-area">
                        {!! $expense->withQueryString()->links() !!}
                    </div>
                @endif
                @if (count($expense) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                        <h5>{{ translate('no_data_found') }}</h5>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function() {
            $('.js-data-example-ajax-2').select2({
                ajax: {
                    url: '{{ route('admin.users.customer.select-list') }}',
                    data: function(params) {
                        return {
                            q: params.term,
                            @if (isset($zone))
                                zone_ids: [{{ $zone->id }}],
                            @endif
                            page: params.page
                        };
                    },
                    processResults: function(data) { return { results: data }; }
                }
            });
        });
    </script>
@endpush
