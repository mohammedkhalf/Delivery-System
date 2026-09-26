{{--
    v2 Users workspace sidebar.
    Sections: Customers / Delivery Men / Riders / Staff & Roles
    Routes mirror legacy _sidebar_users.blade.php with permission gates preserved.
--}}
@php
    use App\CentralLogics\Helpers;

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };

    $pending_dm  = (int) \App\Models\DeliveryMan::where('application_status', 'pending')->count();
    $pending_rd  = 0;
    if (class_exists(\Modules\RideShare\Entities\RideManagement\RiderManagement\Rider::class)) {
        $pending_rd = (int) \Modules\RideShare\Entities\RideManagement\RiderManagement\Rider::where('application_status', 'pending')->count();
    }

    $active_section = 'overview';
    if ($is('admin/users/customer*') || $is('admin/users/contact*') || $is('admin/users/cashback*')) $active_section = 'customers';
    elseif ($is('admin/users/delivery-man*')) $active_section = 'delivery';
    elseif ($is('admin/users/rider*')) $active_section = 'riders';
    elseif ($is('admin/users/custom-role*') || $is('admin/users/employee*')) $active_section = 'staff';
    elseif ($is('admin/users/serviceman*')) $active_section = 'serviceman';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="users" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">USERS</div>
        <div class="v2-rail-btns">
            @if(Helpers::module_permission_check('user_overview'))
            <button class="v2-rail-btn {{ $active_section==='overview' ? 'is-active' : '' }}" data-section="overview" data-label="{{ translate('User Overview') }}" aria-label="{{ translate('User Overview') }}">
                <i data-lucide="gauge"></i>
                <span class="v2-pin-dot"></span>
            </button>
            @endif
            @if(Helpers::module_permission_check('customer_management') || Helpers::module_permission_check('customer_wallet') || Helpers::module_permission_check('customer_loyalty_point') || Helpers::module_permission_check('cashback'))
                <button class="v2-rail-btn {{ $active_section==='customers' ? 'is-active' : '' }}" data-section="customers" data-label="{{ translate('messages.customers') }}" aria-label="{{ translate('messages.customers') }}">
                    <i data-lucide="user-round"></i>
                    <span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('deliveryman'))
                <button class="v2-rail-btn {{ $active_section==='delivery' ? 'is-active' : '' }}" data-section="delivery" data-label="{{ translate('messages.deliveryman_management') }}" aria-label="{{ translate('messages.deliveryman_management') }}">
                    <i data-lucide="bike"></i>
                    <span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(addon_published_status('RideShare') && (Helpers::module_permission_check('rider') || Helpers::module_permission_check('rider_level') || Helpers::module_permission_check('rider_review') || Helpers::module_permission_check('ride_vehicle')))
                <button class="v2-rail-btn {{ $active_section==='riders' ? 'is-active' : '' }}" data-section="riders" data-label="{{ translate('messages.rider') }}" aria-label="{{ translate('messages.rider') }}">
                    <i data-lucide="user-round-cog"></i>
                    <span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('employee') || Helpers::module_permission_check('employee'))
                <button class="v2-rail-btn {{ $active_section==='staff' ? 'is-active' : '' }}" data-section="staff" data-label="{{ translate('messages.employee') }}" aria-label="{{ translate('messages.employee') }}">
                    <i data-lucide="users"></i>
                    <span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(addon_published_status('Service') && Helpers::module_permission_check('service_provider'))
                <button class="v2-rail-btn {{ $active_section==='serviceman' ? 'is-active' : '' }}" data-section="serviceman" data-label="{{ translate('messages.Service Man') }}" aria-label="{{ translate('messages.Service Man') }}">
                    <i data-lucide="wrench"></i>
                    <span class="v2-pin-dot"></span>
                </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ auth('admin')->user()->f_name ?? 'Admin' }}">
                <span class="v2-avatar">{{ strtoupper(substr(auth('admin')->user()->f_name ?? 'A', 0, 1) . substr(auth('admin')->user()->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        {{-- Overview panel --}}
        @if(Helpers::module_permission_check('user_overview'))
        <div class="v2-panel-content" data-panel="overview" @if($active_section!=='overview') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('User Overview') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Users workspace overview, key metrics, and quick links') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'users::overview'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users') ? 'is-active' : '' }}" href="{{ route('admin.users.dashboard') }}" data-id="us-ov">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('User Overview') }}</span>
                            <button type="button" class="v2-pin" data-pin="us-ov" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @endif

        {{-- Customers panel --}}
        @if(Helpers::module_permission_check('customer_management') || Helpers::module_permission_check('customer_wallet') || Helpers::module_permission_check('customer_loyalty_point') || Helpers::module_permission_check('cashback'))
        <div class="v2-panel-content" data-panel="customers" @if($active_section!=='customers') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.customer_management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Customer accounts, wallet, loyalty, and rewards') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'users::customers'])

                @if(Helpers::module_permission_check('customer_management') || Helpers::module_permission_check('customer_management'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="cu-acc"><span>{{ translate('Customer accounts') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/customer/list') ? 'is-active' : '' }}" href="{{ route('admin.users.customer.list') }}" data-id="cu-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.customers') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/customer/subscribed*') ? 'is-active' : '' }}" href="{{ route('admin.users.customer.subscribed') }}" data-id="cu-sub">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.subscribed_mail_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-sub" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Helpers::module_permission_check('customer_management'))
                        <a class="v2-nav-item {{ $is('admin/users/contact/contact-list*') ? 'is-active' : '' }}" href="{{ route('admin.users.contact.contact-list') }}" data-id="cu-cont">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.contact_messages') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-cont" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>

                @endif

                @if(Helpers::module_permission_check('customer_wallet'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="cu-wal"><span>{{ translate('messages.customer_wallet') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/customer/wallet/add-fund*') ? 'is-active' : '' }}" href="{{ route('admin.users.customer.wallet.add-fund') }}" data-id="cu-wal-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_fund') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-wal-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/customer/wallet/report*') ? 'is-active' : '' }}" href="{{ route('admin.users.customer.wallet.report') }}" data-id="cu-wal-rep">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.report') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-wal-rep" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/customer/wallet/bonus*') ? 'is-active' : '' }}" href="{{ route('admin.users.customer.wallet.bonus.add-new') }}" data-id="cu-wal-bon">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.bonus') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-wal-bon" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>

                @endif

                @if(Helpers::module_permission_check('customer_loyalty_point'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="cu-loy"><span>{{ translate('messages.customer_loyalty_point') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/customer/loyalty-point/report*') ? 'is-active' : '' }}" href="{{ route('admin.users.customer.loyalty-point.report') }}" data-id="cu-loy-rep">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.report') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-loy-rep" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>

                @endif

                @if(Helpers::module_permission_check('cashback'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="cu-promo"><span>{{ translate('messages.Promotion_management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/cashback*') ? 'is-active' : '' }}" href="{{ route('admin.users.cashback.add-new') }}" data-id="cu-cash">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.cashback') }}</span>
                            <button type="button" class="v2-pin" data-pin="cu-cash" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Delivery Men panel --}}
        @if(Helpers::module_permission_check('deliveryman'))
        <div class="v2-panel-content" data-panel="delivery" @if($active_section!=='delivery') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.deliveryman_management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Delivery men, vehicle categories, reviews') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'users::delivery'])

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="dl-dm"><span>{{ translate('messages.deliveryman_management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/users/delivery-man') || $is('admin/users/delivery-man/preview/*') || $is('admin/users/delivery-man/edit/*')) ? 'is-active' : '' }}" href="{{ route('admin.users.delivery-man.list') }}" data-id="dm-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.deliveryman_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/delivery-man/add*') ? 'is-active' : '' }}" href="{{ route('admin.users.delivery-man.add') }}" data-id="dm-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_delivery_man') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/delivery-man/new*') ? 'is-active' : '' }}" href="{{ route('admin.users.delivery-man.new') }}" data-id="dm-new">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.new_delivery_man') }}</span>
                            @if($pending_dm > 0)<span class="v2-count">{{ $pending_dm }}</span>@endif
                            <button type="button" class="v2-pin" data-pin="dm-new" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/delivery-man/reviews*') ? 'is-active' : '' }}" href="{{ route('admin.users.delivery-man.reviews.list') }}" data-id="dm-rev">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.reviews') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-rev" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Helpers::module_permission_check('deliveryman'))
                        <a class="v2-nav-item {{ $is('admin/users/delivery-man/vehicle*') ? 'is-active' : '' }}" href="{{ route('admin.users.delivery-man.vehicle.list') }}" data-id="dm-veh">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.vehicles_category') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-veh" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Riders panel --}}
        @if(addon_published_status('RideShare') && (Helpers::module_permission_check('rider') || Helpers::module_permission_check('rider_level') || Helpers::module_permission_check('rider_review') || Helpers::module_permission_check('ride_vehicle')))
        <div class="v2-panel-content" data-panel="riders" @if($active_section!=='riders') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.rider') }} {{ translate('management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Riders, levels, reviews, vehicles') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'users::riders'])

                @if(Helpers::module_permission_check('rider'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rd-list"><span>{{ translate('messages.rider') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/users/rider') || $is('admin/users/rider/preview/*') || $is('admin/users/rider/edit/*')) ? 'is-active' : '' }}" href="{{ route('admin.users.rider.list') }}" data-id="rd-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Rider_List') }}</span>
                            <button type="button" class="v2-pin" data-pin="rd-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/rider/add*') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.add') }}" data-id="rd-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Add_New_Rider') }}</span>
                            <button type="button" class="v2-pin" data-pin="rd-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/rider/new*') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.new') }}" data-id="rd-new">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.New_Rider') }}</span>
                            @if($pending_rd > 0)<span class="v2-count">{{ $pending_rd }}</span>@endif
                            <button type="button" class="v2-pin" data-pin="rd-new" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('rider_level'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rd-lvl-grp"><span>{{ translate('messages.rider_level') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/users/rider/level') || $is('admin/users/rider/level/edit*')) ? 'is-active' : '' }}" href="{{ route('admin.users.rider.level.index') }}" data-id="rd-lvl">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.rider_level') }}</span>
                            <button type="button" class="v2-pin" data-pin="rd-lvl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/rider/level/create') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.level.create') }}" data-id="rd-lvl-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_rider_level') }}</span>
                            <button type="button" class="v2-pin" data-pin="rd-lvl-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('rider_review'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rd-rev-grp"><span>{{ translate('messages.reviews') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/rider/reviews*') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.reviews.list') }}" data-id="rd-rev">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.reviews') }}</span>
                            <button type="button" class="v2-pin" data-pin="rd-rev" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('ride_vehicle'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rd-veh"><span>{{ translate('messages.Vehicle_Management') ?? 'Rider vehicles' }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/rider/vehicle/brand*') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.vehicle.brand.index') }}" data-id="rv-attr">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.Attribute_Setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="rv-attr" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/rider/vehicle/create*') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.vehicle.create') }}" data-id="rv-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Add_Vehicle') }}</span>
                            <button type="button" class="v2-pin" data-pin="rv-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('admin/users/rider/vehicle') || $is('admin/users/rider/vehicle/edit/*') || $is('admin/users/rider/vehicle/show/*')) ? 'is-active' : '' }}" href="{{ route('admin.users.rider.vehicle.index') }}" data-id="rv-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Vehicle_List') }}</span>
                            <button type="button" class="v2-pin" data-pin="rv-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/rider/vehicle/request*') ? 'is-active' : '' }}" href="{{ route('admin.users.rider.vehicle.request.list', ['status' => 'pending']) }}" data-id="rv-req">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.Vehicle_Request') }}</span>
                            <button type="button" class="v2-pin" data-pin="rv-req" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Staff panel --}}
        @if(Helpers::module_permission_check('employee') || Helpers::module_permission_check('employee'))
        <div class="v2-panel-content" data-panel="staff" @if($active_section!=='staff') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.employee') }} {{ translate('management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Internal team and role-based permissions') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'users::staff'])

                @if(Helpers::module_permission_check('employee'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="tm-roles"><span>{{ translate('messages.employee_Role') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/custom-role*') ? 'is-active' : '' }}" href="{{ route('admin.users.custom-role.create') }}" data-id="tm-role">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.employee_Role') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-role" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('employee'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="tm-mem"><span>{{ translate('messages.employee') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/users/employee') || $is('admin/users/employee/edit/*')) ? 'is-active' : '' }}" href="{{ route('admin.users.employee.list') }}" data-id="tm-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Employee_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/employee/store*') ? 'is-active' : '' }}" href="{{ route('admin.users.employee.add-new') }}" data-id="tm-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_new_Employee') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Service Man panel --}}
        @if(addon_published_status('Service') && Helpers::module_permission_check('service_provider'))
        <div class="v2-panel-content" data-panel="serviceman" @if($active_section!=='serviceman') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.Service Man Management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Manage servicemen, onboarding, and assignments') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'users::serviceman'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sm-manage"><span>{{ translate('messages.Service Man') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/users/serviceman') ? 'is-active' : '' }}" href="{{ route('admin.users.serviceman.list') }}" data-id="sm-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Service Man List') }}</span>
                            <button type="button" class="v2-pin" data-pin="sm-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/users/serviceman/create*') ? 'is-active' : '' }}" href="{{ route('admin.users.serviceman.create') }}" data-id="sm-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Add Service Man') }}</span>
                            <button type="button" class="v2-pin" data-pin="sm-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
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
