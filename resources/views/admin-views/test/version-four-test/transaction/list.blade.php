@extends('layouts.admin.app')

@section('title', translate('messages.Transactions_List'))

@section('content')


<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>
                    {{ translate('Transactions') }}
                </span>
            </h1>
        </div>
    </div>


    <!-- Card -->
    <div class="card">
        <!-- Header -->
        <div class="card-header d-block border-0">
            <div class="search--button-wrapper gap-2 justify-content-lg-end">
                <form class="search-form flex-grow-1">
                    <!-- Search -->
                    <div class="input-group input--group max-w-280">
                        <input id="datatableSearch" type="search" name="search"  value="{{ request()?->search ?? null }}" class="form-control" placeholder="{{ translate('messages.Search_here') }}" aria-label="Search here">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                    <!-- End Search -->
                </form>
                <div class="hs-unfold mr-2">
                    <a class="btn btn-outline-primary btn-white filter-button-show h--40px px-4 w-max-content offcanvas-trigger"
                                data-target="#customer_list_offcanvas" href="javascript:;">
                        <i class="tio-tune-horizontal mr-1"></i>
                        {{ translate('messages.Filter') }}
                    </a>
                </div>

                <!-- Unfold -->
                <div class="hs-unfold mr-2">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                        data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                        <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                    </a>

                    <div id="usersExportDropdown"
                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                        <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                        <a id="export-excel" class="dropdown-item" href="
                            {{ route('admin.campaign.basic_campaign_export', ['type' => 'excel', request()->getQueryString()]) }}
                            ">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                alt="Image Description">
                            {{ translate('messages.excel') }}
                        </a>
                        <a id="export-csv" class="dropdown-item" href="
                        {{ route('admin.campaign.basic_campaign_export', ['type' => 'csv', request()->getQueryString()]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                alt="Image Description">
                            {{ translate('messages.csv') }}
                        </a>
                    </div>
                </div>                                
            </div>
        </div>
        <!-- End Header -->

        <!-- Table -->
        <div class="px-xxl-20 px-3">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-dark">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">
                                {{ translate('SL') }}
                            </th>
                            <th class="border-0">{{ translate('Transaction ID') }}</th>
                            <th class="border-0">{{ translate('Transaction Date') }}</th>
                            <th class="border-0">{{ translate('Customer Info') }}</th>
                            <th class="border-0 text-center">{{ translate('plan name') }}</th>
                            <th class="border-0 text-center">{{ translate('plan price') }}</th>
                            <th class="border-0">{{ translate('plan validity') }}</th>
                            <th class="border-0 text-center">{{ translate('Payment by') }}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                        <tr>
                            <td>
                                1
                            </td>
                            <td>
                                <a href="#" class="text-dark text-hover-primary">#48548</a>
                            </td>
                            <td>
                                28 Dec 2024 <br> 11:09 pm 
                            </td>
                            <td class="table-column-pl-0">
                                <div class="d-flex align-items-center gap-2 min-w-220 max-w--220px">
                                    <img class="rounded-circle aspect-1-1 object-cover" width="40"
                                        src="{{asset('public/assets/admin/img/placeholder.png') }}" alt="Image Description">

                                    <div>
                                        <a href=""
                                            class="text-dark fw-500 text-hover-primary max-w-215px min-w-135px text-wrap line--limit-1">
                                            Robert Henri
                                        </a>
                                        <div>
                                            <div>
                                                <a href="" class="text-light-gray fs-12 text-hover-primary max-w-215px min-w-170px text-wrap line--limit-1">
                                                    Abcd@gmail.com
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                Monthly
                            </td>
                            <td class="text-center">
                                $ 20.00
                            </td>
                            <td>
                                <div>
                                    28 Nov 2024 11:00 pm - 
                                </div>
                                <div>
                                    28 Dec 2024 11:00 pm 
                                </div>
                            </td>
                            <td class="text-center">
                                SSL Commerz
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="empty--data">
            <img src="{{asset('public/assets/admin/img/empty.png') }}" alt="public">
            <h5>
                {{ translate('no_data_found') }}
            </h5>
        </div>
    </div>
</div>

    <!-- Filter Offcanvas -->
    <div id="customer_list_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between"
        style="--offcanvas-width: 500px">
            <div>
                <form id="filterForm" action="" method="GET">
                <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                    <div class="px-3 py-3 d-flex justify-content-between w-100">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h2 class="mb-0 fs-18 text-title font-medium">{{ translate('Transaction Filter') }}</h2>

                        </div>
                        <button type="button"
                            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                            aria-label="Close">&times;
                        </button>
                    </div>
                </div>
                <div class="custom-offcanvas-body p-20">
                    <div class="d-flex flex-column gap-20px">
                        <div class="bg-light p-xxl-20 p-3 rounded">
                            <div class="mb-20">
                                <div class="d-flex align-items-center text-dark gap-1 fs-14 mb-2">
                                    {{ translate('Subscription Plan') }}
                                </div>
                                <div class="position-relative bg-white rounded">
                                    <i class="tio-calendar-month icon-absolute-on-right"></i>
                                    <input type="text" class="form-control h-45 position-relative bg-transparent"
                                            name="dates" placeholder="{{ translate('messages.Select_Date') }}">
                                </div>
                            </div>
                            <div class="mb-20">
                                <label class="form-label fw-400">{{ translate('plan') }}</label>
                                <select name="filter" data-placeholder="{{ translate('messages.Select_Plan') }}"
                                    class="form-control js-select2-custom ">
                                    <option value="" selected disabled>
                                        {{ translate('messages.Select_Plan') }}
                                    </option>
                                    <option value="">test</option>
                                </select>
                            </div>
                            <div class="mb-20">
                                <label class="form-label fw-400">{{ translate('Customer') }}</label>
                                <select name="filter" data-placeholder="{{ translate('messages.Select_Customer') }}"
                                    class="form-control js-select2-custom ">
                                    <option value="" selected disabled>
                                        {{ translate('messages.Select_Customer') }}
                                    </option>
                                    <option {{ request()->input('filter') == 'all' ? 'selected' : '' }} value="all">
                                        {{ translate('messages.All_Customers') }}</option>
                                    <option {{ request()->input('filter') == 'active' ? 'selected' : '' }}
                                        value="active">
                                        {{ translate('messages.Active_Customers') }}</option>
                                    <option {{ request()->input('filter') == 'blocked' ? 'selected' : '' }}
                                        value="blocked">
                                        {{ translate('messages.Inactive_Customers') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label fw-400">{{ translate('Payment_Method') }}</label>
                                <select name="filter" data-placeholder="{{ translate('messages.Select_Payment_Method') }}"
                                    class="form-control js-select2-custom ">
                                    <option value="" selected disabled>
                                        {{ translate('messages.Select_Payment_Method') }}
                                    </option>
                                   <option value="">test</option>
                                </select>
                            </div>                                
                        </div>
                    </div>
                </div>
            </div>
            <div  class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center offcanvas-footer p-3 position-sticky">
                <a href="{{ route('admin.users.customer.list') }}"
                    class="btn w-100 btn--reset offcanvas-close">{{ translate('Reset') }}</a>
                <button type="submit" id="apply_filter" class="btn w-100 btn--primary">{{ translate('Apply') }}</button>
            </form>
            </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
    <!-- Filter Offcanvas End -->

@endsection

@push('script_2')

@endpush