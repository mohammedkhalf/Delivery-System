{{--
    v2 Settings workspace sidebar.
    Section structure mirrors the prototype's 12 settings categories.
    Each section/subsection is gated by its own Helpers::module_permission_check() key
    (see custom-role form for the full key list) plus addon_published_status() where applicable.
--}}
@php
    use App\CentralLogics\Helpers;

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };
    $admin_user = auth('admin')->user();

    $can_settings = Helpers::module_permission_check('settings');
    $can_zone     = Helpers::module_permission_check('settings');
    $can_module   = Helpers::module_permission_check('module');
    $can_sub      = Helpers::module_permission_check('subscription');
    $can_pro      = Helpers::module_permission_check('pro_customer_subscription');
    $can_customer = Helpers::module_permission_check('customer_management');
    $can_sys_tax  = Helpers::module_permission_check('system_tax');
    $can_pages    = (Helpers::module_permission_check('social_media') || Helpers::module_permission_check('landing_pages') || Helpers::module_permission_check('business_pages') || Helpers::module_permission_check('seo'));
    $can_sys_cfg  = Helpers::module_permission_check('system_config');
    $can_apps     = Helpers::module_permission_check('system_config');
    $can_sys_addons = Helpers::module_permission_check('system_config');
    $can_login    = Helpers::module_permission_check('login_setup');
    $can_email    = (Helpers::module_permission_check('email_setups') || Helpers::module_permission_check('notification_setup'));
    $can_3rd_party = Helpers::module_permission_check('third_party-ms');
    $can_ride_settings = Helpers::module_permission_check('ride_settings');
    $can_service_mgmt = Helpers::module_permission_check('service_settings');
    $can_gallery  = Helpers::module_permission_check('gallery');
    $can_clean_db = Helpers::module_permission_check('clean_database');
    $rental_on    = addon_published_status('Rental');
    $ride_on      = addon_published_status('RideShare');
    $service_on   = addon_published_status('Service');
    $tax_on       = addon_published_status('TaxModule');

    $active_section = 'biz';
    if ($is('admin/business-settings/module*'))                   $active_section = 'mods';
    elseif ($is('admin/business-settings/subscription*') || $is('admin/pro-customer*'))         $active_section = 'subs';
    elseif ($is('taxvat/*'))                                      $active_section = 'fin';
    elseif ($is('admin/business-settings/pages/*') || $is('admin/business-settings/seo-settings*')) $active_section = 'pages';
    elseif ($is('admin/business-settings/file-manager*'))         $active_section = 'media';
    elseif ($is('admin/business-settings/login-settings*') || $is('admin/business-settings/login-url-setup*'))       $active_section = 'auth';
    elseif ($is('admin/business-settings/email-setup*') || $is('admin/business-settings/rental-email-setup*') || $is('admin/business-settings/service-email-setup*') || $is('admin/business-settings/notification-setup*') || $is('admin/business-settings/fcm*')) $active_section = 'comm';
    elseif ($is('admin/business-settings/third-party*') || $is('admin/business-settings/offline-payment*') || $is('admin/business-settings/marketing*') || $is('admin/business-settings/open-ai*') || $is('admin/payment/configuration*') || $is('admin/sms/configuration*')) $active_section = 'int';
    elseif ($is('admin/business-settings/safety-precaution*') || $is('admin/business-settings/ride-fare*') || $is('admin/business-settings/ride-share*')) $active_section = 'safety';
    elseif ($is('admin/business-settings/service*'))             $active_section = 'service';
    elseif ($is('admin/business-settings/db-index*'))             $active_section = 'maint';
    elseif ($is('admin/business-settings/language*') || $is('admin/business-settings/app-settings*') || $is('admin/business-settings/websocket*') || $is('admin/business-settings/addon-activation*') || $is('admin/business-settings/system-addon*')) $active_section = 'sys';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="settings" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">SETTINGS</div>
        <div class="v2-rail-btns">
            @if($can_settings || $can_zone)
            <button class="v2-rail-btn {{ $active_section==='biz' ? 'is-active' : '' }}" data-section="biz" data-label="{{ translate('Business Setup') }}" aria-label="{{ translate('Business Setup') }}">
                <i data-lucide="briefcase"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_module)
            <button class="v2-rail-btn {{ $active_section==='mods' ? 'is-active' : '' }}" data-section="mods" data-label="{{ translate('Business Modules') }}" aria-label="{{ translate('Business Modules') }}">
                <i data-lucide="boxes"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_sub || $can_pro || $can_customer)
            <button class="v2-rail-btn {{ $active_section==='subs' ? 'is-active' : '' }}" data-section="subs" data-label="{{ translate('Subscription Management') }}" aria-label="{{ translate('Subscription Management') }}">
                <i data-lucide="credit-card"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_sys_tax && $tax_on)
            <button class="v2-rail-btn {{ $active_section==='fin' ? 'is-active' : '' }}" data-section="fin" data-label="{{ translate('Finance & Tax') }}" aria-label="{{ translate('Finance & Tax') }}">
                <i data-lucide="receipt"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_pages)
            <button class="v2-rail-btn {{ $active_section==='pages' ? 'is-active' : '' }}" data-section="pages" data-label="{{ translate('Website, Pages & Content') }}" aria-label="{{ translate('Website, Pages & Content') }}">
                <i data-lucide="file-text"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_sys_cfg || $can_apps || $can_sys_addons)
            <button class="v2-rail-btn {{ $active_section==='sys' ? 'is-active' : '' }}" data-section="sys" data-label="{{ translate('System Configuration') }}" aria-label="{{ translate('System Configuration') }}">
                <i data-lucide="cog"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_login)
            <button class="v2-rail-btn {{ $active_section==='auth' ? 'is-active' : '' }}" data-section="auth" data-label="{{ translate('Authentication & Access') }}" aria-label="{{ translate('Authentication & Access') }}">
                <i data-lucide="lock"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_email)
            <button class="v2-rail-btn {{ $active_section==='comm' ? 'is-active' : '' }}" data-section="comm" data-label="{{ translate('Communication Setup') }}" aria-label="{{ translate('Communication Setup') }}">
                <i data-lucide="mail"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_3rd_party)
            <button class="v2-rail-btn {{ $active_section==='int' ? 'is-active' : '' }}" data-section="int" data-label="{{ translate('Integrations & Third-Party') }}" aria-label="{{ translate('Integrations & Third-Party') }}">
                <i data-lucide="plug"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($ride_on && $can_ride_settings)
            <button class="v2-rail-btn {{ $active_section==='safety' ? 'is-active' : '' }}" data-section="safety" data-label="{{ translate('Ride Share Settings') }}" aria-label="{{ translate('Ride Share Settings') }}">
                <i data-lucide="car-front"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($service_on && $can_service_mgmt)
            <button class="v2-rail-btn {{ $active_section==='service' ? 'is-active' : '' }}" data-section="service" data-label="{{ translate('Service Module Settings') }}" aria-label="{{ translate('Service Module Settings') }}">
                <i data-lucide="wrench"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_gallery)
            <button class="v2-rail-btn {{ $active_section==='media' ? 'is-active' : '' }}" data-section="media" data-label="{{ translate('Media & File Management') }}" aria-label="{{ translate('Media & File Management') }}">
                <i data-lucide="image"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_clean_db)
            <button class="v2-rail-btn {{ $active_section==='maint' ? 'is-active' : '' }}" data-section="maint" data-label="{{ translate('Maintenance & Database') }}" aria-label="{{ translate('Maintenance & Database') }}">
                <i data-lucide="database"></i><span class="v2-pin-dot"></span>
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
        @if($can_settings || $can_zone)
        <div class="v2-panel-content" data-panel="biz" @if($active_section!=='biz') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Business Setup') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Core business configuration and zones') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::biz'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_settings)
                        <a class="v2-nav-item {{ $is('admin/business-settings/business-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.business-setup') }}" data-id="biz-info">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Business Settings') }}</span>
                            <button type="button" class="v2-pin" data-pin="biz-info" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_zone)
                        <a class="v2-nav-item {{ $is('admin/business-settings/zone*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.home') }}" data-id="biz-zone">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Zone Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="biz-zone" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_module)
        <div class="v2-panel-content" data-panel="mods" @if($active_section!=='mods') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Business Modules') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Module creation and management') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::mods'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/module/store*') || $is('admin/business-settings/module/create*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.module.create') }}" data-id="mod-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Add New Module') }}</span>
                            <button type="button" class="v2-pin" data-pin="mod-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('admin/business-settings/module') || $is('admin/business-settings/module/edit/*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.module.index') }}" data-id="mod-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Manage Modules') }}</span>
                            <button type="button" class="v2-pin" data-pin="mod-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_sub || $can_pro || $can_customer)
        <div class="v2-panel-content" data-panel="subs" @if($active_section!=='subs') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Subscription Management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Subscription packages, subscribers, and settings') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::subs'])
                @if($can_sub)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sub-vendor"><span>{{ translate('Vendor Subscription') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_sub)
                        <a class="v2-nav-item {{ $is('admin/business-settings/subscription/subscriptionackage*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.index') }}" data-id="sub-pkg">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Subscription Packages') }}</span>
                            <button type="button" class="v2-pin" data-pin="sub-pkg" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/subscription/subscriber*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberList') }}" data-id="sub-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Subscribers') }}</span>
                            <button type="button" class="v2-pin" data-pin="sub-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_sub)
                        <a class="v2-nav-item {{ $is('admin/business-settings/subscription/settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.settings') }}" data-id="sub-set">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('Subscription Settings') }}</span>
                            <button type="button" class="v2-pin" data-pin="sub-set" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if (Helpers::get_business_settings('pro_member_status') == 1 && ($can_pro || $can_customer))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sub-pro"><span>{{ translate('messages.Pro_Customer_Management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if ($can_customer)
                        <a class="v2-nav-item {{ $is('admin/pro-customer/list*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.list') }}" data-id="pro-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Pro_Customer_List') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if ($can_pro)
                        <a class="v2-nav-item {{ $is('admin/pro-customer/benefits-setup*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.benefits-setup') }}" data-id="pro-ben">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Pro_Customer_Benefits_Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-ben" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/pro-customer/price-setup*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.price-setup') }}" data-id="pro-price">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.Price_Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-price" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/pro-customer/additional-setup*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.additional-setup') }}" data-id="pro-add">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.Additional_Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/pro-customer/transactions*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.transactions') }}" data-id="pro-tx">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.Transactions') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-tx" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_sys_tax && $tax_on)
        <div class="v2-panel-content" data-panel="fin" @if($active_section!=='fin') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Finance & Tax') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Charges, penalties, and financial configurations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::fin'])
                @if($tax_on)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fin-tax"><span>{{ translate('Tax Configuration') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ \Illuminate\Support\Str::is(['taxvat/get-taxvat-data*', 'taxvat/add-taxvat-data*', 'taxvat/update-taxvat-data*', 'taxvat/export-taxvat*'], $req) ? 'is-active' : '' }}" href="{{ route('taxvat.index') }}" data-id="tax-create">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Create_Taxes') }}</span>
                            <button type="button" class="v2-pin" data-pin="tax-create" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('taxvat/system-taxvat*') ? 'is-active' : '' }}" href="{{ route('taxvat.systemTaxvat', ['type' => 'vendor']) }}" data-id="tax-setup">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Setup_Taxes') }}</span>
                            <button type="button" class="v2-pin" data-pin="tax-setup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_pages)
        <div class="v2-panel-content" data-panel="pages" @if($active_section!=='pages') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Website, Pages & Content') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Public-facing pages, policies, and branding') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::pages'])

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-soc"><span>{{ translate('Social & Branding') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/social-media*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.social-media.index') }}" data-id="pg-soc-link">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Social Media Links') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-soc-link" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-land"><span>{{ translate('Landing pages') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/admin-landing-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.admin-landing-page-settings', 'setup') }}" data-id="pg-adm">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Admin Landing Page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-adm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/react-landing-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.react-landing-page-settings', 'header') }}" data-id="pg-rea">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('React Landing Page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-rea" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if (addon_published_status('RideShare') == 1)
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/react-ride-share-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.react-ride-share-page-settings', 'hero') }}" data-id="pg-rea-ride">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.react_ride_share_page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-rea-ride" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        {{-- <a class="v2-nav-item {{ $is('admin/business-settings/pages/flutter-landing-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.flutter-landing-page-settings', 'fixed-data') }}" data-id="pg-flu">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Flutter Landing Page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-flu" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a> --}}
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-leg"><span>{{ translate('Business pages') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/terms-and-conditions*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.terms-and-conditions') }}" data-id="bp-tc">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Terms & Conditions') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-tc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/privacy-policy*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.privacy-policy') }}" data-id="bp-pp">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Privacy Policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-pp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/about-us*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.about-us') }}" data-id="bp-ab">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('About Us') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-ab" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/refund*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.refund') }}" data-id="bp-rf">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Refund Policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-rf" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/cancelation*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.cancelation') }}" data-id="bp-cn">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Cancellation Policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-cn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/shipping-policy*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.shipping-policy') }}" data-id="bp-sh">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Shipping Policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-sh" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-seo"><span>{{ translate('SEO & Metadata') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/seo-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.seo-settings.pageMetaData') }}" data-id="pg-meta">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Page Meta Data (SEO)') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-meta" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_sys_cfg || $can_apps || $can_sys_addons)
        <div class="v2-panel-content" data-panel="sys" @if($active_section!=='sys') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('System Configuration') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Platform-wide technical settings') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::sys'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_sys_cfg)
                        <a class="v2-nav-item {{ $is('admin/business-settings/language*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.language.index') }}" data-id="sys-lang">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Language Management') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-lang" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_apps)
                        <a class="v2-nav-item {{ $is('admin/business-settings/app-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.app-settings') }}" data-id="sys-app">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('App Settings') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-app" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_sys_cfg)
                        <a class="v2-nav-item {{ $is('admin/business-settings/websocket*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.websocket') }}" data-id="sys-ws">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('WebSocket Configuration') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-ws" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/addon-activation*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.addon-activation.index') }}" data-id="sys-add">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Addon Activation') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_sys_addons)
                        <a class="v2-nav-item {{ $is('admin/business-settings/system-addon*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.system-addon.index') }}" data-id="sys-sa">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('System Addons') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-sa" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_login)
        <div class="v2-panel-content" data-panel="auth" @if($active_section!=='auth') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Authentication & Access') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Login systems and access settings') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::auth'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/business-settings/login-settings*') || $is('admin/business-settings/login-url-setup*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.login-settings.index') }}" data-id="auth-login">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Login & Authentication Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="auth-login" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_email)
        <div class="v2-panel-content" data-panel="comm" @if($active_section!=='comm') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Communication Setup') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Email, notifications, and push') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::comm'])

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="comm-email"><span>{{ translate('Email configuration') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/email-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.email-setup', ['admin', 'forgot-password']) }}" data-id="em-all">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('All Modules Email Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="em-all" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($rental_on)
                        <a class="v2-nav-item {{ $is('admin/business-settings/rental-email-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.rental-email-setup', ['admin', 'provider-registration']) }}" data-id="em-ren">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Rental Module Email Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="em-ren" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on)
                        <a class="v2-nav-item {{ $is('admin/business-settings/service-email-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.service-email-setup', ['admin', 'provider-registration']) }}" data-id="em-serv">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Service Module Email Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="em-serv" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="comm-notif"><span>{{ translate('Notifications') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/business-settings/notification-setup*') && !str_contains(request()->fullUrl(), 'module=rental') && !str_contains(request()->fullUrl(), 'module=service')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.notification_setup') }}" data-id="sn-all">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('All Modules Notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="sn-all" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($rental_on)
                        <a class="v2-nav-item {{ ($is('admin/business-settings/notification-setup*') && str_contains(request()->fullUrl(), 'module=rental')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.notification_setup', ['module' => 'rental']) }}" data-id="sn-rental">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Rental Module Notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="sn-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on)
                        <a class="v2-nav-item {{ ($is('admin/business-settings/notification-setup*') && str_contains(request()->fullUrl(), 'module=service')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.notification_setup', ['module' => 'service']) }}" data-id="sn-service">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Service Module Notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="sn-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        <a class="v2-nav-item {{ $is('admin/business-settings/fcm*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.fcm-index') }}" data-id="fcm">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Firebase Notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="fcm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_3rd_party)
        <div class="v2-panel-content" data-panel="int" @if($active_section!=='int') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Integrations & Third-Party') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('External tools, payment, AI, and analytics') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::int'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @php
                            $int_third_party_active = $is('admin/business-settings/third-party/sms-module*')
                                || $is('admin/business-settings/third-party/mail-config*')
                                || $is('admin/business-settings/third-party/test-mail*')
                                || $is('admin/business-settings/third-party/config-setup*')
                                || $is('admin/business-settings/third-party/social-login*')
                                || $is('admin/business-settings/third-party/recaptcha*')
                                || $is('admin/business-settings/third-party/firebase-otp*')
                                || $is('admin/business-settings/third-party/storage-connection*')
                                || $is('admin/sms/configuration*');
                        @endphp
                        <a class="v2-nav-item {{ $int_third_party_active ? 'is-active' : '' }}" href="{{ route('admin.business-settings.third-party.sms-module') }}" data-id="int-sms">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('3rd Party & External Services') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-sms" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('admin/business-settings/third-party/payment-method*') || $is('admin/business-settings/offline-payment*') || $is('admin/payment/configuration*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.third-party.payment-method') }}" data-id="int-pay">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Payment Methods') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-pay" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/marketing*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.marketing.analytic') }}" data-id="int-an">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Analytics & Tracking Scripts') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-an" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Route::has('admin.business-settings.openAI'))
                        <a class="v2-nav-item {{ $is('admin/business-settings/open-ai*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.openAI') }}" data-id="int-ai">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('AI Configuration') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-ai" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($ride_on && $can_ride_settings)
        <div class="v2-panel-content" data-panel="safety" @if($active_section!=='safety') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Ride Share Settings') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Fare, penalties, and safety configurations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::safety'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/business-settings/ride-fare*') || $is('admin/business-settings/ride-share*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.ride-fare.penalty') }}" data-id="fin-fare">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Ride Fare Penalty & Charges') }}</span>
                            <button type="button" class="v2-pin" data-pin="fin-fare" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Route::has('admin.business-settings.safety-precaution.index') && defined('SAFETY_ALERT'))
                        <a class="v2-nav-item {{ $is('admin/business-settings/safety-precaution*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.safety-precaution.index', SAFETY_ALERT) }}" data-id="safety-alerts">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Safety Alerts & Precautions') }}</span>
                            <button type="button" class="v2-pin" data-pin="safety-alerts" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($service_on && $can_service_mgmt)
        <div class="v2-panel-content" data-panel="service" @if($active_section!=='service') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Service Module Settings') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Booking, provider and serviceman configurations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::service'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/service/booking*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.service.booking') }}" data-id="svc-booking">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Bookings') }}</span>
                            <button type="button" class="v2-pin" data-pin="svc-booking" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/service/provider-serviceman*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.service.provider-serviceman') }}" data-id="svc-provider">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Providers & Serviceman') }}</span>
                            <button type="button" class="v2-pin" data-pin="svc-provider" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_gallery)
        <div class="v2-panel-content" data-panel="media" @if($active_section!=='media') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Media & File Management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Files, assets, and gallery') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::media'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/file-manager*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.file-manager.index') }}" data-id="media-gal">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Gallery / File Manager') }}</span>
                            <button type="button" class="v2-pin" data-pin="media-gal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_clean_db)
        <div class="v2-panel-content" data-panel="maint" @if($active_section!=='maint') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Maintenance & Database') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('System cleanup and maintenance') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::maint'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/db-index*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.db-index') }}" data-id="maint-clean">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Clean Database') }}</span>
                            <button type="button" class="v2-pin" data-pin="maint-clean" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.admin.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
