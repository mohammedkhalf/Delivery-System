{{--
    v2 Finance workspace sidebar.
    Per prototype: Withdraw Management / Auto Disbursements / Cash Operations / Tax & Compliance.
    Reports live in a SEPARATE workspace (_sidebar_v2_reports.blade.php).
--}}
@php
    use App\CentralLogics\Helpers;

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };
    $admin_user = auth('admin')->user();

    $rental_on = addon_published_status('Rental');
    $ride_on   = addon_published_status('RideShare');
    $service_on = addon_published_status('Service');

    $can_report       = Helpers::module_permission_check('report');
    $can_admin_tax    = Helpers::module_permission_check('admin_text_module');
    $can_vendor_vat   = Helpers::module_permission_check('vendor_vat_report');
    $can_trip_tax     = Helpers::module_permission_check('admin_text_module');
    $can_provider_vat = Helpers::module_permission_check('vendor_vat_report');
    $can_tax_section  = $can_report || $can_admin_tax || $can_vendor_vat
        || ($rental_on && ($can_trip_tax || $can_provider_vat))
        || ($ride_on && $can_admin_tax);

    $active_section = 'withdraws';
    if ($is('admin/transactions/store-disbursement*') || $is('admin/transactions/dm-disbursement*') || $is('admin/transactions/rider-disbursement*')) $active_section = 'disbursements';
    elseif ($is('admin/transactions/account-transaction*') || $is('admin/transactions/provide-deliveryman-earnings*') || $is('admin/transactions/provide-rider-earnings*') || $is('admin/transactions/withdraw-method*')) $active_section = 'cash';
    elseif ($is('admin/transactions/report/*tax*') || $is('admin/transactions/rental/report/*tax*') || $is('admin/transactions/service/report/*tax*') || $is('admin/transactions/ride-share/report/*tax*') || $is('taxvat/*')) $active_section = 'tax';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="finance" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">FINANCE</div>
        <div class="v2-rail-btns">
            @if(Helpers::module_permission_check('withdraw_list'))
                <button class="v2-rail-btn {{ $active_section==='withdraws' ? 'is-active' : '' }}" data-section="withdraws" data-label="{{ translate('Withdraw Management') }}" aria-label="{{ translate('Withdraw Management') }}">
                    <i data-lucide="hand-coins"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('disbursement'))
                <button class="v2-rail-btn {{ $active_section==='disbursements' ? 'is-active' : '' }}" data-section="disbursements" data-label="{{ translate('Auto Disbursements') }}" aria-label="{{ translate('Auto Disbursements') }}">
                    <i data-lucide="send"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('collect_cash') || Helpers::module_permission_check('provide_dm_earning') || Helpers::module_permission_check('withdraw_method'))
                <button class="v2-rail-btn {{ $active_section==='cash' ? 'is-active' : '' }}" data-section="cash" data-label="{{ translate('Cash Operations') }}" aria-label="{{ translate('Cash Operations') }}">
                    <i data-lucide="banknote"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if($can_tax_section)
                <button class="v2-rail-btn {{ $active_section==='tax' ? 'is-active' : '' }}" data-section="tax" data-label="{{ translate('Tax & Compliance') }}" aria-label="{{ translate('Tax & Compliance') }}">
                    <i data-lucide="receipt"></i><span class="v2-pin-dot"></span>
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
        @if(Helpers::module_permission_check('withdraw_list'))
        <div class="v2-panel-content" data-panel="withdraws" @if($active_section!=='withdraws') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Withdraw Management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Vendor, deliveryman, and rider withdraw requests') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'finance::withdraws'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fw-req"><span>{{ translate('Withdraw requests') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/transactions/store/withdraw_list*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.store.withdraw_list') }}" data-id="wd-vendor">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Vendor Withdraw Requests') }}</span>
                            <button type="button" class="v2-pin" data-pin="wd-vendor" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/transactions/delivery-man/withdraw_list*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.delivery-man.withdraw_list') }}" data-id="wd-dm">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Deliveryman Withdraw Requests') }}</span>
                            <button type="button" class="v2-pin" data-pin="wd-dm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($ride_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/rider/withdraw_list*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.rider.withdraw_list') }}" data-id="wd-rd">
                                <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Rider Withdraw Requests') }}</span>
                                <button type="button" class="v2-pin" data-pin="wd-rd" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(Helpers::module_permission_check('disbursement'))
        <div class="v2-panel-content" data-panel="disbursements" @if($active_section!=='disbursements') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Auto Disbursements') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Automated payouts to vendors, delivery men, and riders') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'finance::disbursements'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fd-list"><span>{{ translate('Auto disbursement') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/transactions/store-disbursement*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.store-disbursement.list', ['status' => 'all']) }}" data-id="ad-vendor">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Vendor Auto Disbursement') }}</span>
                            <button type="button" class="v2-pin" data-pin="ad-vendor" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/transactions/dm-disbursement*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.dm-disbursement.list', ['status' => 'all']) }}" data-id="ad-dm">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Deliveryman Auto Disbursement') }}</span>
                            <button type="button" class="v2-pin" data-pin="ad-dm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($ride_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/rider-disbursement*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.rider-disbursement.list', ['status' => 'all']) }}" data-id="ad-rd">
                                <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Rider Auto Disbursement') }}</span>
                                <button type="button" class="v2-pin" data-pin="ad-rd" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(Helpers::module_permission_check('collect_cash') || Helpers::module_permission_check('provide_dm_earning') || Helpers::module_permission_check('withdraw_method'))
        <div class="v2-panel-content" data-panel="cash" @if($active_section!=='cash') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Cash Operations') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Cash collection, earnings payouts, and methods') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'finance::cash'])

                @if(Helpers::module_permission_check('collect_cash'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fc-coll"><span>{{ translate('Cash collection') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/transactions/account-transaction*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.account-transaction.index') }}" data-id="cc-coll">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Collect Cash') }}</span>
                            <button type="button" class="v2-pin" data-pin="cc-coll" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('provide_dm_earning'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fc-pay"><span>{{ translate('Pay earnings') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/transactions/provide-deliveryman-earnings*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.provide-deliveryman-earnings.index') }}" data-id="pe-dm">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Deliveryman Payment') }}</span>
                            <button type="button" class="v2-pin" data-pin="pe-dm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($ride_on)
                            <a class="v2-nav-item {{ $is('admin/transactions/provide-rider-earnings*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.provide-rider-earnings.index') }}" data-id="pe-rd">
                                <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Rider Payment') }}</span>
                                <button type="button" class="v2-pin" data-pin="pe-rd" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('withdraw_method'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fc-mtd"><span>{{ translate('Withdraw methods') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/transactions/withdraw-method*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.withdraw-method.list') }}" data-id="wm-list">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.withdraw_method') }}</span>
                            <button type="button" class="v2-pin" data-pin="wm-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_tax_section)
        <div class="v2-panel-content" data-panel="tax" @if($active_section!=='tax') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Tax & Compliance') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Admin tax and vendor VAT reports by module') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'finance::tax'])

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ft-admin"><span>{{ translate('Admin Tax report') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        {{-- URIs use kebab-case despite camelCase route names. Active patterns match the URI tree. --}}
                        @if($can_admin_tax)
                        <a class="v2-nav-item {{ ($is('admin/transactions/report/get-tax-*') || $is('admin/transactions/report/admin-tax-*') || $is('admin/transactions/report/tax-*')) ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.getTaxReport') }}" data-id="tax-admin-order">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Order module') }}</span>
                            <button type="button" class="v2-pin" data-pin="tax-admin-order" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/transactions/report/parcel-wise-tax*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.parcel-wise-taxes') }}" data-id="tax-admin-parcel">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Parcel module') }}</span>
                            <button type="button" class="v2-pin" data-pin="tax-admin-parcel" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($rental_on && $can_trip_tax && Route::has('admin.transactions.rental.report.getTaxReport'))
                            <a class="v2-nav-item {{ ($is('admin/transactions/rental/report/get-tax-*') || $is('admin/transactions/rental/report/admin-tax-*') || $is('admin/transactions/rental/report/tax-*')) ? 'is-active' : '' }}" href="{{ route('admin.transactions.rental.report.getTaxReport') }}" data-id="tax-admin-rental">
                                <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Rental module') }}</span>
                                <button type="button" class="v2-pin" data-pin="tax-admin-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                        @if($ride_on && $can_admin_tax && Route::has('admin.transactions.ride-share.report.ride-wise-taxes'))
                            <a class="v2-nav-item {{ $is('admin/transactions/ride-share/report/ride-wise-tax*') ? 'is-active' : '' }}" href="{{ route('admin.transactions.ride-share.report.ride-wise-taxes') }}" data-id="tax-admin-ride">
                                <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Ride Share module') }}</span>
                                <button type="button" class="v2-pin" data-pin="tax-admin-ride" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                        @if($service_on && $can_report && Route::has('admin.transactions.service.report.getTaxReport'))
                            <a class="v2-nav-item {{ ($is('admin/transactions/service/report/get-tax-*') || $is('admin/transactions/service/report/admin-tax-*') || $is('admin/transactions/service/report/tax-*')) ? 'is-active' : '' }}" href="{{ route('admin.transactions.service.report.getTaxReport') }}" data-id="tax-admin-service">
                                <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Service module') }}</span>
                                <button type="button" class="v2-pin" data-pin="tax-admin-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ft-vendor"><span>{{ translate('Vendor Tax Report') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_vendor_vat)
                        <a class="v2-nav-item {{ ($is('admin/transactions/report/vendor-wise-tax*') || $is('admin/transactions/report/vendor-tax*')) ? 'is-active' : '' }}" href="{{ route('admin.transactions.report.vendorWiseTaxes') }}" data-id="vat-store">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Vendor Tax Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="vat-store" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($rental_on && $can_provider_vat && Route::has('admin.transactions.rental.report.providerWiseTaxes'))
                            <a class="v2-nav-item {{ ($is('admin/transactions/rental/report/provider-wise-tax*') || $is('admin/transactions/rental/report/provider-tax*')) ? 'is-active' : '' }}" href="{{ route('admin.transactions.rental.report.providerWiseTaxes') }}" data-id="vat-rental">
                                <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Provider Tax Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="vat-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                        @if($service_on && $can_report && Route::has('admin.transactions.service.report.providerWiseTaxes'))
                            <a class="v2-nav-item {{ ($is('admin/transactions/service/report/provider-wise-tax*') || $is('admin/transactions/service/report/provider-tax*')) ? 'is-active' : '' }}" href="{{ route('admin.transactions.service.report.providerWiseTaxes') }}" data-id="vat-service">
                                <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Service Provider Tax Report') }}</span>
                                <button type="button" class="v2-pin" data-pin="vat-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
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
