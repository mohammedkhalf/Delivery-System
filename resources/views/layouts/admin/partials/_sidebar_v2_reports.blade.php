{{--
    v2 Reports workspace sidebar.
    Sections per prototype: Transaction / Earning / Payout & Disbursement / Sales / Performance.
    Tax reports live under Finance > Tax & Compliance, NOT here.
--}}
@php
    use App\CentralLogics\Helpers;

    $req = request()->path();
    $is  = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };
    $any = function(array $pats) use ($req) { return \Illuminate\Support\Str::is($pats, $req); };
    $admin_user = auth('admin')->user();

    $rental_on = addon_published_status('Rental');
    $ride_on   = addon_published_status('RideShare');
    $service_on = addon_published_status('Service');
    $parcel_on = \App\Models\Module::where('module_type', 'parcel')->where('status', 1)->exists();

    $can_report        = Helpers::module_permission_check('report');
    $can_sales_report  = Helpers::module_permission_check('sales_report');
    $can_performance   = Helpers::module_permission_check('performance_report');
    $can_expense       = Helpers::module_permission_check('expense_report');
    $can_disb_report   = Helpers::module_permission_check('disbursement_report');
    $can_earning       = Helpers::module_permission_check('earning_report');

    $sec_visible = [
        'transaction' => $can_report,
        'earning'     => $can_earning,
        'payout'      => $can_disb_report || $can_expense,
        'sales'       => $can_sales_report,
        'performance' => $can_performance,
    ];

    // Match patterns include every inner-tab URL so sidebar stays active on sub-tabs.
    $earning_patterns = [
        'admin/transactions/report/admin-earning-report*',
        'admin/transactions/report/store-earning-report*',
        'admin/transactions/report/deliveryman-earning-report*',
        'admin/transactions/ride-share/report/admin-earning-report*',
        'admin/transactions/ride-share/report/rider-earning-report*',
    ];
    $payout_patterns = [
        'admin/transactions/report/disbursement*',
        'admin/transactions/report/expense-report*',
        'admin/transactions/report/parcel-expense-report*',
        'admin/transactions/report/rental-expense-report*',
        'admin/transactions/report/rideshare-expense-report*',
        'admin/transactions/report/service-expense-report*',
        'admin/transactions/report/other-expense-report*',
    ];
    $sales_patterns = [
        'admin/transactions/report/order-report*',
        'admin/transactions/report/parcel-report*',
        'admin/transactions/rental/report/trip-report*',
        'admin/transactions/service/report/booking-report*',
        'admin/transactions/ride-share/report/ride-report*',
        'admin/transactions/ride-share/report/earning*',
        'admin/transactions/ride-share/report/expense*',
    ];
    $performance_patterns = [
        'admin/transactions/report/store-wise-report*',
        'admin/transactions/report/store-wise-sales-report*',
        'admin/transactions/report/store-wise-order-report*',
        'admin/transactions/report/item-wise-report*',
        'admin/transactions/rental/report/provider-wise-report*',
        'admin/transactions/rental/report/provider-wise-sales-report*',
        'admin/transactions/rental/report/provider-wise-trip-report*',
        'admin/transactions/rental/report/vehicle*',
        'admin/transactions/service/report/provider-wise-report*',
        'admin/transactions/service/report/provider-wise-sales-report*',
        'admin/transactions/service/report/provider-wise-trip-report*',
        'admin/transactions/service/report/service-report*',
    ];
    $transaction_patterns = [
        'admin/transactions/report/day-wise-report*',
        'admin/transactions/report/parcel-transaction-report*',
        'admin/transactions/rental/report/transaction-report*',
        'admin/transactions/ride-share/transaction*',
        'admin/transactions/service/report/transaction-report*',
    ];

    $active_section = 'transaction';
    if      ($any($earning_patterns))     $active_section = 'earning';
    elseif  ($any($payout_patterns))      $active_section = 'payout';
    elseif  ($any($sales_patterns))       $active_section = 'sales';
    elseif  ($any($performance_patterns)) $active_section = 'performance';
    elseif  ($any($transaction_patterns)) $active_section = 'transaction';

    if (empty($sec_visible[$active_section])) {
        foreach ($sec_visible as $sec_key => $sec_on) {
            if ($sec_on) { $active_section = $sec_key; break; }
        }
    }
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="reports" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">REPORTS</div>
        <div class="v2-rail-btns">
            @if($sec_visible['transaction'])
            <button class="v2-rail-btn {{ $active_section==='transaction' ? 'is-active' : '' }}" data-section="transaction" data-label="{{ translate('Transaction Reports') }}" aria-label="{{ translate('Transaction Reports') }}">
                <i data-lucide="arrow-left-right"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sec_visible['earning'])
            <button class="v2-rail-btn {{ $active_section==='earning' ? 'is-active' : '' }}" data-section="earning" data-label="{{ translate('Earning Reports') }}" aria-label="{{ translate('Earning Reports') }}">
                <i data-lucide="trending-up"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sec_visible['payout'])
            <button class="v2-rail-btn {{ $active_section==='payout' ? 'is-active' : '' }}" data-section="payout" data-label="{{ translate('Payout & Disbursement') }}" aria-label="{{ translate('Payout & Disbursement') }}">
                <i data-lucide="send"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sec_visible['sales'])
            <button class="v2-rail-btn {{ $active_section==='sales' ? 'is-active' : '' }}" data-section="sales" data-label="{{ translate('Sales Reports') }}" aria-label="{{ translate('Sales Reports') }}">
                <i data-lucide="shopping-bag"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sec_visible['performance'])
            <button class="v2-rail-btn {{ $active_section==='performance' ? 'is-active' : '' }}" data-section="performance" data-label="{{ translate('Performance Reports') }}" aria-label="{{ translate('Performance Reports') }}">
                <i data-lucide="gauge"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ $admin_user->f_name ?? 'Admin' }}">
                <span class="v2-avatar">{{ strtoupper(substr($admin_user->f_name ?? 'A', 0, 1) . substr($admin_user->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        @if($sec_visible['transaction'])
        <div class="v2-panel-content" data-panel="transaction" @if($active_section!=='transaction') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Transaction Reports') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Transaction summaries by module') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'reports::transaction'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rt-by"><span>{{ translate('By module') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/report/day-wise-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.day-wise-report') }}" data-id="tr-order">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Order Transaction Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="tr-order" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($parcel_on && $can_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/report/parcel-transaction-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.parcel-transaction-report') }}" data-id="tr-parcel">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Parcel Transaction Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="tr-parcel" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($rental_on && $can_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/rental/report/transaction-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.rental.report.transaction-report') }}" data-id="tr-rental">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Rental Transaction Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="tr-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($ride_on && $can_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/ride-share/transaction*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.ride-share.transaction.index') }}" data-id="tr-ride">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Ride Transaction Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="tr-ride" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on && $can_report && Route::has('admin.transactions.service.report.transaction-report'))
                        <a class="v2-nav-item {{ $is('admin/transactions/service/report/transaction-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.service.report.transaction-report') }}" data-id="tr-service">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Service Transaction Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="tr-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sec_visible['earning'])
        <div class="v2-panel-content" data-panel="earning" @if($active_section!=='earning') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Earning Reports') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Earnings breakdown by admin, store, and deliveryman') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'reports::earning'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $any(['admin/transactions/report/admin-earning-report*', 'admin/transactions/ride-share/report/admin-earning-report*']) ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.admin-earning-report') }}" data-id="er-admin">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Admin_Earning_Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="er-admin" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/transactions/report/store-earning-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.store-earning-report') }}" data-id="er-store">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Vendor_Earning_Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="er-store" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $any(['admin/transactions/report/deliveryman-earning-report*', 'admin/transactions/ride-share/report/rider-earning-report*']) ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.deliveryman-earning-report') }}" data-id="er-dm">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Deliveryman_Earning_Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="er-dm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sec_visible['payout'])
        <div class="v2-panel-content" data-panel="payout" @if($active_section!=='payout') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Payout & Disbursement') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Disbursement and expense reports') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'reports::payout'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_disb_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/report/disbursement*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.disbursement_report') }}" data-id="py-disb">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.disbursement_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="py-disb" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_expense)
                        @php
                            $expense_patterns = [
                                'admin/transactions/report/expense-report*',
                                'admin/transactions/report/parcel-expense-report*',
                                'admin/transactions/report/rental-expense-report*',
                                'admin/transactions/report/rideshare-expense-report*',
                                'admin/transactions/report/service-expense-report*',
                                'admin/transactions/report/other-expense-report*',
                            ];
                            $expense_any_active = $any($expense_patterns);
                        @endphp
                        <button type="button" class="v2-nav-parent {{ $expense_any_active ? 'is-open is-active' : '' }}" data-parent-toggle="py-expenses">
                            <span class="v2-dot v2-dot--rose"></span>
                            <span class="v2-label">{{ translate('Expense Reports') }}</span>
                            <i data-lucide="chevron-right" class="v2-chev"></i>
                        </button>
                        <div class="v2-nav-children" @if(!$expense_any_active) hidden @endif>
                            <a class="v2-nav-item {{ $is('admin/transactions/report/expense-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.expense-report') }}" data-id="py-exp">
                                <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Order Expense Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="py-exp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            @if($parcel_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/report/parcel-expense-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.parcel-expense-report') }}" data-id="py-exp-parcel">
                                <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Parcel Expense Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="py-exp-parcel" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            @endif
                            @if($rental_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/report/rental-expense-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.rental-expense-report') }}" data-id="py-exp-rental">
                                <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Rental Expense Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="py-exp-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            @endif
                            @if($ride_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/report/rideshare-expense-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.rideshare-expense-report') }}" data-id="py-exp-ride">
                                <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Ride-Share Expense Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="py-exp-ride" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            @endif
                            @if($service_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/report/service-expense-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.service-expense-report') }}" data-id="py-exp-service">
                                <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Service Expense Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="py-exp-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            @endif
                            <a class="v2-nav-item {{ $is('admin/transactions/report/other-expense-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.other-expense-report') }}" data-id="py-exp-other">
                                <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Other Expense Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="py-exp-other" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sec_visible['sales'])
        <div class="v2-panel-content" data-panel="sales" @if($active_section!=='sales') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Sales Reports') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Sales totals broken down by module') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'reports::sales'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rs-by"><span>{{ translate('By module') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_sales_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/report/order-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.order-report') }}" data-id="sr-order">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.order_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="sr-order" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($parcel_on && $can_sales_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/report/parcel-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.parcel-report') }}" data-id="sr-parcel">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Parcel Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="sr-parcel" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($rental_on && $can_sales_report)
                        <a class="v2-nav-item {{ $is('admin/transactions/rental/report/trip-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.rental.report.trip-report') }}" data-id="sr-rental">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.trip_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="sr-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on && $can_sales_report && Route::has('admin.transactions.service.report.booking-report'))
                        <a class="v2-nav-item {{ $is('admin/transactions/service/report/booking-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.service.report.booking-report') }}" data-id="sr-service">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Booking Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="sr-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($ride_on && $can_sales_report && Route::has('admin.transactions.ride-share.report.ride-report'))
                        <a class="v2-nav-item {{ $is('admin/transactions/ride-share/report/ride-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.ride-share.report.ride-report') }}" data-id="sr-ride">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.ride_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="sr-ride" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sec_visible['performance'])
        <div class="v2-panel-content" data-panel="performance" @if($active_section!=='performance') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Performance Reports') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Performance analytics for stores, providers, items, and vehicles') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'reports::performance'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rp-by"><span>{{ translate('Entities') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_performance)
                        <a class="v2-nav-item {{ $any(['admin/transactions/report/store-wise-report*', 'admin/transactions/report/store-wise-sales-report*', 'admin/transactions/report/store-wise-order-report*']) ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.store-summary-report') }}" data-id="pr-store">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.store_wise_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="pr-store" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/transactions/report/item-wise-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.item-wise-report') }}" data-id="pr-item">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.item_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="pr-item" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($rental_on && $can_performance)
                        <a class="v2-nav-item {{ $any(['admin/transactions/rental/report/provider-wise-report*', 'admin/transactions/rental/report/provider-wise-sales-report*', 'admin/transactions/rental/report/provider-wise-trip-report*']) ? 'is-active' : '' }}" href="{{ route('admin.transactions.rental.report.provider-summary-report') }}" data-id="pr-prov">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.provider_wise_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="pr-prov" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($rental_on && $can_performance)
                        <a class="v2-nav-item {{ $is('admin/transactions/rental/report/vehicle*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.rental.report.vehicle-wise-report') }}" data-id="pr-veh">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.vehicle_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="pr-veh" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on && $can_performance && Route::has('admin.transactions.service.report.provider-summary-report'))
                        <a class="v2-nav-item {{ $any(['admin/transactions/service/report/provider-wise-report*', 'admin/transactions/service/report/provider-wise-sales-report*', 'admin/transactions/service/report/provider-wise-trip-report*']) ? 'is-active' : '' }}" href="{{ route('admin.transactions.service.report.provider-summary-report') }}" data-id="pr-sprov">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.service_provider_wise_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="pr-sprov" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Route::has('admin.transactions.service.report.service-wise-report'))
                        <a class="v2-nav-item {{ $is('admin/transactions/service/report/service-report*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.service.report.service-wise-report') }}" data-id="pr-svc">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.service_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="pr-svc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.admin.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
