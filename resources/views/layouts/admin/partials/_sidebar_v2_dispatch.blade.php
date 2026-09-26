{{--
    v2 Dispatch workspace sidebar.
    One rail icon per non-rental module; each panel shows Unassigned + Ongoing orders.
--}}
@php
    use App\CentralLogics\Helpers;

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };

    $admin_user = auth('admin')->user();
    $can_order = Helpers::module_permission_check('dispatch');
    $modules = \App\Models\Module::whereNotIn('module_type', ['rental', 'ride-share'])
        ->when($admin_user?->zone_id, function ($q) use ($admin_user) {
            $q->whereHas('zones', function ($qq) use ($admin_user) { $qq->where('zone_id', $admin_user->zone_id); });
        })
        ->Active()
        ->get();

    $module_icon_map = [
        'grocery' => 'shopping-cart',
        'pharmacy'=> 'pill',
        'ecommerce'=>'store',
        'food'    => 'utensils',
        'parcel'  => 'package-2',
    ];

    $active_section = null;
    foreach ($modules as $m) {
        $is_parcel = $m->module_type === 'parcel';
        $pat = $is_parcel ? "admin/dispatch/parcel/list/{$m->id}*" : "admin/dispatch/list/{$m->id}*";
        if ($is($pat)) { $active_section = 'm-' . $m->id; break; }
    }
    if ($active_section === null) {
        $active_section = 'dashboard';
    }
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="dispatch" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">DISPATCH</div>
        <div class="v2-rail-btns">
            <button class="v2-rail-btn {{ $active_section === 'dashboard' ? 'is-active' : '' }}" data-section="dashboard" data-label="{{ translate('messages.dashboard') }}" aria-label="{{ translate('messages.dashboard') }}">
                <i data-lucide="layout-dashboard"></i>
                <span class="v2-pin-dot"></span>
            </button>
            @if($can_order)
                @foreach($modules as $m)
                    <button class="v2-rail-btn {{ $active_section === 'm-'.$m->id ? 'is-active' : '' }}" data-section="m-{{ $m->id }}" data-label="{{ $m->module_name }}" aria-label="{{ $m->module_name }}">
                        <i data-lucide="{{ $module_icon_map[$m->module_type] ?? 'route' }}"></i>
                        <span class="v2-pin-dot"></span>
                    </button>
                @endforeach
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ $admin_user->f_name ?? 'Admin' }}">
                <span class="v2-avatar">{{ strtoupper(substr($admin_user->f_name ?? 'A', 0, 1) . substr($admin_user->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        <div class="v2-panel-content" data-panel="dashboard" @if($active_section!=='dashboard') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.dispatch') }} {{ translate('messages.dashboard') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Live overview of dispatch operations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'dispatch::dashboard'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="dp-dash"><span>{{ translate('messages.overview') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/dispatch') ? 'is-active' : '' }}" href="{{ route('admin.dispatch.dashboard') }}" data-id="dp-dash">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.dashboard') }}</span>
                            <button type="button" class="v2-pin" data-pin="dp-dash" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @if($can_order)
        @foreach($modules as $m)
            @php
                $is_parcel_m = $m->module_type === 'parcel';
                if ($is_parcel_m) {
                    $unassigned = (int) \App\Models\Order::whereHas('module', fn($q) => $q->where('module_id', $m->id))->SearchingForDeliveryman()->OrderScheduledIn(30)->ParcelOrder()->count();
                    $ongoing    = (int) \App\Models\Order::whereHas('module', fn($q) => $q->where('module_id', $m->id))->Ongoing()->OrderScheduledIn(30)->ParcelOrder()->count();
                    $url_un  = route('admin.dispatch.parcel.list', [$m->id, 'searching_for_deliverymen']);
                    $url_on  = route('admin.dispatch.parcel.list', [$m->id, 'on_going']);
                    $pat_un  = "admin/dispatch/parcel/list/{$m->id}/searching_for_deliverymen";
                    $pat_on  = "admin/dispatch/parcel/list/{$m->id}/on_going";
                } else {
                    $unassigned = (int) \App\Models\Order::whereHas('module', fn($q) => $q->where('module_id', $m->id))->SearchingForDeliveryman()->OrderScheduledIn(30)->StoreOrder()->count();
                    $ongoing    = (int) \App\Models\Order::whereHas('module', fn($q) => $q->where('module_id', $m->id))->Ongoing()->OrderScheduledIn(30)->StoreOrder()->count();
                    $url_un  = route('admin.dispatch.list', [$m->id, 'searching_for_deliverymen']);
                    $url_on  = route('admin.dispatch.list', [$m->id, 'on_going']);
                    $pat_un  = "admin/dispatch/list/{$m->id}/searching_for_deliverymen";
                    $pat_on  = "admin/dispatch/list/{$m->id}/on_going";
                }
            @endphp
            <div class="v2-panel-content" data-panel="m-{{ $m->id }}" @if($active_section!=='m-'.$m->id) hidden @endif>
                <div class="v2-panel-header">
                    <div class="v2-panel-title"><span class="name">{{ $m->module_name }}</span></div>
                    <div class="v2-panel-subtitle">{{ translate('Unassigned and ongoing orders') }}</div>
                </div>
                <div class="v2-panel-body">
                    @include('layouts.admin.partials._v2_pinned_card', ['key' => 'dispatch::m-'.$m->id])
                    <div class="v2-group">
                        <button type="button" class="v2-group-header" data-group-toggle="dp-{{ $m->id }}"><span>{{ translate('Operations') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                        <div class="v2-group-items">
                            <a class="v2-nav-item {{ $is($pat_un) ? 'is-active' : '' }}" href="{{ $url_un }}" data-id="dp-{{ $m->id }}-un">
                                <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.unassigned_orders') }}</span>
                                <span class="v2-count">{{ $unassigned }}</span>
                                <button type="button" class="v2-pin" data-pin="dp-{{ $m->id }}-un" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            <a class="v2-nav-item {{ $is($pat_on) ? 'is-active' : '' }}" href="{{ $url_on }}" data-id="dp-{{ $m->id }}-on">
                                <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.ongoingOrders') }}</span>
                                <span class="v2-count">{{ $ongoing }}</span>
                                <button type="button" class="v2-pin" data-pin="dp-{{ $m->id }}-on" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @endif
    </aside>
</aside>

@include('layouts.admin.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
