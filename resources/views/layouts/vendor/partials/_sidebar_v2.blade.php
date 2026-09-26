
@php
    use App\CentralLogics\Helpers;

    $store_data = Helpers::get_store_data();
    $module_type = $store_data?->module?->module_type ?? '';
    $is_food      = $module_type === 'food';
    $is_grocery   = $module_type === 'grocery';
    $is_pharmacy  = $module_type === 'pharmacy';
    $is_ecommerce = $module_type === 'ecommerce';
    $is_parcel    = $module_type === 'parcel';
    $needs_catalog_extras = in_array($module_type, ['grocery', 'ecommerce'], true);

    $vendor_user = Helpers::get_loggedin_user();
    $store_id = Helpers::get_store_id();

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };

    $can_pos        = Helpers::employee_module_permission_check('pos');
    $can_order      = Helpers::employee_module_permission_check('order');
    $can_item       = Helpers::employee_module_permission_check('item');
    $can_addon      = Helpers::employee_module_permission_check('addon');
    $can_category   = Helpers::employee_module_permission_check('category');
    $can_campaign   = Helpers::employee_module_permission_check('campaign');
    $can_coupon     = Helpers::employee_module_permission_check('coupon');
    $can_banner     = Helpers::employee_module_permission_check('banner');
    $can_ad         = Helpers::employee_module_permission_check('advertisement');
    $can_ad_list    = Helpers::employee_module_permission_check('advertisement_list');
    $can_dm         = Helpers::employee_module_permission_check('deliveryman');
    $can_dm_list    = Helpers::employee_module_permission_check('deliveryman_list');
    $can_wallet     = Helpers::employee_module_permission_check('wallet');
    $can_wal_method = Helpers::employee_module_permission_check('wallet_method');
    $can_role       = Helpers::employee_module_permission_check('employee');
    $can_employee   = Helpers::employee_module_permission_check('employee');
    $can_exp_rep    = Helpers::employee_module_permission_check('expense_report');
    $can_vat_rep    = Helpers::employee_module_permission_check('vat_report');
    $can_disb_rep   = Helpers::employee_module_permission_check('disbursement_report');
    $can_store_setup = Helpers::employee_module_permission_check('store_setup');
    $can_notif_setup = Helpers::employee_module_permission_check('notification_setup');
    $can_my_shop     = Helpers::employee_module_permission_check('my_shop');
    $can_subscription= Helpers::employee_module_permission_check('business_plan');
    $can_reviews     = Helpers::employee_module_permission_check('reviews');
    $can_chat        = Helpers::employee_module_permission_check('chat');

    $reels_enabled = addon_published_status('ReelsModule')
        && Helpers::get_business_settings('vendor_can_upload_reels')
        && Helpers::employee_module_permission_check('reels')
        && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType($module_type);

    $count_all       = \App\Models\Order::where('store_id', $store_id)->where(function ($q) {
        $q->whereNotIn('order_status',
            (config('order_confirmation_model') == 'store' || ($store_data->sub_self_delivery ?? false))
                ? ['failed', 'canceled', 'refund_requested', 'refunded']
                : ['pending', 'failed', 'canceled', 'refund_requested', 'refunded'])
          ->orWhere(function ($q2) { $q2->where('order_status','pending')->where('order_type','take_away'); });
    })->StoreOrder()->NotDigitalOrder()->count();
    $count_pending   = (config('order_confirmation_model') == 'store' || ($store_data->sub_self_delivery ?? false))
        ? \App\Models\Order::where(['order_status'=>'pending', 'store_id'=>$store_id])->StoreOrder()->OrderScheduledIn(30)->NotDigitalOrder()->count()
        : \App\Models\Order::where(['order_status'=>'pending', 'store_id'=>$store_id, 'order_type'=>'take_away'])->StoreOrder()->OrderScheduledIn(30)->NotDigitalOrder()->count();
    $count_confirmed = \App\Models\Order::whereIn('order_status', ['confirmed','accepted'])->StoreOrder()->whereNotNull('confirmed')->where('store_id', $store_id)->OrderScheduledIn(30)->NotDigitalOrder()->count();
    $count_processing= \App\Models\Order::where(['order_status'=>'processing', 'store_id'=>$store_id])->StoreOrder()->NotDigitalOrder()->count();
    $count_handover  = \App\Models\Order::where(['order_status'=>'handover', 'store_id'=>$store_id])->StoreOrder()->NotDigitalOrder()->count();
    $count_picked_up = \App\Models\Order::where(['order_status'=>'picked_up', 'store_id'=>$store_id])->StoreOrder()->NotDigitalOrder()->count();
    $count_delivered = \App\Models\Order::where(['order_status'=>'delivered', 'store_id'=>$store_id])->StoreOrder()->NotDigitalOrder()->count();
    $count_refunded  = \App\Models\Order::Refunded()->where(['store_id'=>$store_id])->StoreOrder()->NotDigitalOrder()->count();
    $count_scheduled = \App\Models\Order::where('store_id', $store_id)->StoreOrder()->Scheduled()->where(function ($q) use ($store_data) {
        if (config('order_confirmation_model') == 'store' || ($store_data->sub_self_delivery ?? false)) {
            $q->whereNotIn('order_status', ['failed','canceled','refund_requested','refunded']);
        } else {
            $q->whereNotIn('order_status', ['pending','failed','canceled','refund_requested','refunded'])
              ->orWhere(function ($q2) { $q2->where('order_status','pending')->where('order_type','take_away'); });
        }
    })->count();

    $active_section = 'dashboard';
    if     ($is('vendor-panel/pos*') || $is('vendor-panel/order*')) $active_section = 'sales';
    elseif (($is('vendor-panel/item*') && !$is('vendor-panel/item/flash-sale*')) || $is('vendor-panel/addon*') || $is('vendor-panel/category*') || $is('vendor-panel/store-category*') || $is('vendor-panel/attribute*') || $is('vendor-panel/unit*'))               $active_section = 'catalog';
    elseif ($is('vendor-panel/campaign*') || $is('vendor-panel/coupon*') || $is('vendor-panel/banner*') || $is('vendor-panel/advertisement*') || $is('vendor-panel/reels*') || $is('vendor-panel/item/flash-sale*'))      $active_section = 'marketing';
    elseif ($is('vendor-panel/delivery-man*'))                                                                                                                                     $active_section = 'ops';
    elseif ($is('vendor-panel/wallet*') || $is('vendor-panel/withdraw-method*') || $is('vendor-panel/wallet-method*'))                                                            $active_section = 'finance';
    elseif ($is('vendor-panel/custom-role*') || $is('vendor-panel/employee*'))                                                                                                     $active_section = 'team';
    elseif ($is('vendor-panel/report*') || $is('vendor-panel/expense*'))                                                                                                           $active_section = 'reports';
    elseif ($is('vendor-panel/business-settings*') || $is('vendor-panel/store/*') || $is('vendor-panel/subscription*') || $is('vendor-panel/reviews*') || $is('vendor-panel/message*')) $active_section = 'settings';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="vendor::{{ $module_type }}" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail v2-rail--module" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">{{ strtoupper($module_type ?: 'STORE') }}</div>
        <div class="v2-rail-btns">
            <button class="v2-rail-btn {{ $active_section==='dashboard' ? 'is-active' : '' }}" data-section="dashboard" data-label="{{ translate('messages.dashboard') }}" aria-label="{{ translate('messages.dashboard') }}">
                <i data-lucide="layout-dashboard"></i><span class="v2-pin-dot"></span>
            </button>
            @if($can_pos || $can_order)
            <button class="v2-rail-btn {{ $active_section==='sales' ? 'is-active' : '' }}" data-section="sales" data-label="{{ translate('Sales') }}" aria-label="{{ translate('Sales') }}">
                <i data-lucide="shopping-cart"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_item || $can_addon || $can_category)
            <button class="v2-rail-btn {{ $active_section==='catalog' ? 'is-active' : '' }}" data-section="catalog" data-label="{{ translate('Catalog') }}" aria-label="{{ translate('Catalog') }}">
                <i data-lucide="package"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_campaign || $can_coupon || $can_banner || $can_ad || $can_ad_list || $reels_enabled || ($needs_catalog_extras && $can_item))
            <button class="v2-rail-btn {{ $active_section==='marketing' ? 'is-active' : '' }}" data-section="marketing" data-label="{{ translate('Marketing') }}" aria-label="{{ translate('Marketing') }}">
                <i data-lucide="megaphone"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_dm || $can_dm_list)
            <button class="v2-rail-btn {{ $active_section==='ops' ? 'is-active' : '' }}" data-section="ops" data-label="{{ translate('messages.deliveryman_section') }}" aria-label="{{ translate('messages.deliveryman_section') }}">
                <i data-lucide="bike"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_wallet || $can_wal_method)
            <button class="v2-rail-btn {{ $active_section==='finance' ? 'is-active' : '' }}" data-section="finance" data-label="{{ translate('messages.Wallet Management') }}" aria-label="{{ translate('messages.Wallet Management') }}">
                <i data-lucide="wallet"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_role || $can_employee)
            <button class="v2-rail-btn {{ $active_section==='team' ? 'is-active' : '' }}" data-section="team" data-label="{{ translate('messages.employee_section') }}" aria-label="{{ translate('messages.employee_section') }}">
                <i data-lucide="users"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_exp_rep || $can_vat_rep || $can_disb_rep)
            <button class="v2-rail-btn {{ $active_section==='reports' ? 'is-active' : '' }}" data-section="reports" data-label="{{ translate('messages.Report_section') }}" aria-label="{{ translate('messages.Report_section') }}">
                <i data-lucide="bar-chart-3"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_store_setup || $can_notif_setup || $can_my_shop || $can_subscription || $can_reviews || $can_chat)
            <button class="v2-rail-btn {{ $active_section==='settings' ? 'is-active' : '' }}" data-section="settings" data-label="{{ translate('messages.business_section') }}" aria-label="{{ translate('messages.business_section') }}">
                <i data-lucide="settings-2"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ $vendor_user->f_name ?? 'Vendor' }}">
                <span class="v2-avatar">{{ strtoupper(substr($vendor_user->f_name ?? 'V', 0, 1) . substr($vendor_user->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        
        <div class="v2-panel-content" data-panel="dashboard" @if($active_section!=='dashboard') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ $store_data->name ?? translate('messages.dashboard') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Live overview of your store') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::dashboard'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="dh-over"><span>{{ translate('messages.overview') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel') ? 'is-active' : '' }}" href="{{ route('vendor.dashboard') }}" data-id="dh-home">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.dashboard') }}</span>
                            <button type="button" class="v2-pin" data-pin="dh-home" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if($can_pos || $can_order)
        <div class="v2-panel-content" data-panel="sales" @if($active_section!=='sales') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Sales') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('POS and orders') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::sales'])

                @if($can_pos)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sl-pos"><span>{{ translate('messages.pos') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/pos*') ? 'is-active' : '' }}" href="{{ route('vendor.pos.index') }}" data-id="sl-pos">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.pos') }}</span>
                            <button type="button" class="v2-pin" data-pin="sl-pos" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($can_order)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sl-ord"><span>{{ translate('messages.orders') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/all') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['all']) }}" data-id="ord-all">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.all') }}</span>
                            <span class="v2-count">{{ $count_all }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-all" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/pending') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['pending']) }}" data-id="ord-pen">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.pending') }}</span>
                            <span class="v2-count">{{ $count_pending }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-pen" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/confirmed') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['confirmed']) }}" data-id="ord-con">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.confirmed') }}</span>
                            <span class="v2-count">{{ $count_confirmed }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-con" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/cooking') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['cooking']) }}" data-id="ord-proc">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ $is_food ? translate('messages.cooking') : translate('messages.processing') }}</span>
                            <span class="v2-count">{{ $count_processing }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-proc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/ready_for_delivery') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['ready_for_delivery']) }}" data-id="ord-rfd">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.ready_for_delivery') }}</span>
                            <span class="v2-count">{{ $count_handover }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-rfd" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/item_on_the_way') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['item_on_the_way']) }}" data-id="ord-otw">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.item_on_the_way') }}</span>
                            <span class="v2-count">{{ $count_picked_up }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-otw" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/delivered') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['delivered']) }}" data-id="ord-del">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.delivered') }}</span>
                            <span class="v2-count">{{ $count_delivered }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-del" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/refunded') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['refunded']) }}" data-id="ord-ref">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.refunded') }}</span>
                            <span class="v2-count">{{ $count_refunded }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-ref" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/order/list/scheduled') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['scheduled']) }}" data-id="ord-sch">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.scheduled') }}</span>
                            <span class="v2-count">{{ $count_scheduled }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-sch" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_item || $can_addon || $can_category)
        <div class="v2-panel-content" data-panel="catalog" @if($active_section!=='catalog') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Catalog') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('messages.item_management') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::catalog'])

                @if($can_item)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-itm"><span>{{ translate('messages.items') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/item/add-new') ? 'is-active' : '' }}" href="{{ route('vendor.item.add-new') }}" data-id="it-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_new') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('vendor-panel/item/list*') || $is('vendor-panel/item/edit/*') || $is('vendor-panel/item/view/*')) ? 'is-active' : '' }}" href="{{ route('vendor.item.list') }}" data-id="it-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.list') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Helpers::get_mail_status('product_approval'))
                        <a class="v2-nav-item {{ ($is('vendor-panel/item/pending/item/list*') || $is('vendor-panel/item/requested/item/view/*')) ? 'is-active' : '' }}" href="{{ route('vendor.item.pending_item_list') }}" data-id="it-pen">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.pending_item_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-pen" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if(Helpers::get_mail_status('product_gallery'))
                        <a class="v2-nav-item {{ $is('vendor-panel/item/product-gallery*') ? 'is-active' : '' }}" href="{{ route('vendor.item.product_gallery') }}" data-id="it-gal">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.Product_Gallery') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-gal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if(!$is_food)
                        <a class="v2-nav-item {{ $is('vendor-panel/item/stock-limit-list*') ? 'is-active' : '' }}" href="{{ route('vendor.item.stock-limit-list') }}" data-id="it-low">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.Low_stock_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-low" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($store_data?->item_section)
                        <a class="v2-nav-item {{ $is('vendor-panel/item/bulk-import*') ? 'is-active' : '' }}" href="{{ route('vendor.item.bulk-import') }}" data-id="it-imp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_import') }}</span>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/item/bulk-export*') ? 'is-active' : '' }}" href="{{ route('vendor.item.bulk-export-index') }}" data-id="it-exp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_export') }}</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can_addon)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-add"><span>{{ translate('messages.addons') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/addon*') ? 'is-active' : '' }}" href="{{ route('vendor.addon.add-new') }}" data-id="ad-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.addons') }}</span>
                            <button type="button" class="v2-pin" data-pin="ad-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($can_category)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-cat"><span>{{ translate('messages.categories') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/category/list*') ? 'is-active' : '' }}" href="{{ route('vendor.category.add') }}" data-id="cat-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Main_Category') }}</span>
                            <button type="button" class="v2-pin" data-pin="cat-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/category/sub-category-list*') ? 'is-active' : '' }}" href="{{ route('vendor.category.add-sub-category') }}" data-id="cat-sub">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.Main_Sub_Category') }}</span>
                            <button type="button" class="v2-pin" data-pin="cat-sub" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(\App\CentralLogics\Helpers::storeCategoryStatus() && \App\CentralLogics\Helpers::employee_module_permission_check('category'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-mycat"><span>{{ translate('messages.My_Category') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/store-category*') ? 'is-active' : '' }}" href="{{ route('vendor.store-category.list') }}" data-id="ct-mycat">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.My_Category') }}</span>
                            <button type="button" class="v2-pin" data-pin="ct-mycat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($needs_catalog_extras && (Route::has('vendor.attribute.add-new') || Route::has('vendor.unit.index')))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-attr"><span>{{ translate('messages.Attribute_Setup') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if(Route::has('vendor.attribute.add-new'))
                        <a class="v2-nav-item {{ $is('vendor-panel/attribute*') ? 'is-active' : '' }}" href="{{ route('vendor.attribute.add-new') }}" data-id="ct-attr">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.attributes') }}</span>
                            <button type="button" class="v2-pin" data-pin="ct-attr" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if(Route::has('vendor.unit.index'))
                        <a class="v2-nav-item {{ $is('vendor-panel/unit*') ? 'is-active' : '' }}" href="{{ route('vendor.unit.index') }}" data-id="ct-unit">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.units') }}</span>
                            <button type="button" class="v2-pin" data-pin="ct-unit" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_campaign || $can_coupon || $can_banner || $can_ad || $can_ad_list || $reels_enabled || ($needs_catalog_extras && $can_item))
        <div class="v2-panel-content" data-panel="marketing" @if($active_section!=='marketing') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Marketing') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Campaigns, coupons, banners, ads, reels & flash sales') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::marketing'])

                @if($needs_catalog_extras && $can_item)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-flash"><span>{{ translate('messages.flash_sales') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/item/flash-sale*') ? 'is-active' : '' }}" href="{{ route('vendor.item.flash_sale') }}" data-id="mk-flash">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.flash_sales') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-flash" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($can_campaign || $can_coupon)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-promo"><span>{{ translate('messages.marketing_section') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_campaign)
                        <a class="v2-nav-item {{ $is('vendor-panel/campaign/list') ? 'is-active' : '' }}" href="{{ route('vendor.campaign.list') }}" data-id="mk-camp">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.basic_campaigns') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-camp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/campaign/item/list*') ? 'is-active' : '' }}" href="{{ route('vendor.campaign.itemlist') }}" data-id="mk-camp-item">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.Item Campaigns') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-camp-item" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_coupon)
                        <a class="v2-nav-item {{ $is('vendor-panel/coupon*') ? 'is-active' : '' }}" href="{{ route('vendor.coupon.add-new') }}" data-id="mk-coup">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.coupons') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-coup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can_banner)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-ban"><span>{{ translate('messages.banners') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/banner*') ? 'is-active' : '' }}" href="{{ route('vendor.banner.list') }}" data-id="mk-bn">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.banners') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-bn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($can_ad || $can_ad_list)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-ad"><span>{{ translate('Advertisement Management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_ad)
                        <a class="v2-nav-item {{ $is('vendor-panel/advertisement/create*') ? 'is-active' : '' }}" href="{{ route('vendor.advertisement.create') }}" data-id="mk-adc">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.New_Advertisement') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-adc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_ad_list)
                        @php
                            $vad_pending_active = $is('vendor-panel/advertisement*') && (request()->input('type') === 'pending');
                            $vad_list_active = $is('vendor-panel/advertisement*') && !$vad_pending_active && !$is('vendor-panel/advertisement/create*');
                        @endphp
                        <a class="v2-nav-item {{ $vad_pending_active ? 'is-active' : '' }}" href="{{ route('vendor.advertisement.index', ['type' => 'pending']) }}" data-id="mk-adp">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.Ad_Requests') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-adp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $vad_list_active ? 'is-active' : '' }}" href="{{ route('vendor.advertisement.index') }}" data-id="mk-adl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Ads_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-adl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($reels_enabled)
                @php
                    $vr_create_active = $is('vendor-panel/reels/create*');
                    $vr_list_active = $is('vendor-panel/reels*') && !$vr_create_active;
                @endphp
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-reels"><span>{{ translate('messages.Reels_Management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $vr_create_active ? 'is-active' : '' }}" href="{{ route('vendor.reels.create') }}" data-id="mk-rlc">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.Create_Reels') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-rlc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $vr_list_active ? 'is-active' : '' }}" href="{{ route('vendor.reels.index') }}" data-id="mk-rll">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Reels_List') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-rll" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_dm || $can_dm_list)
        <div class="v2-panel-content" data-panel="ops" @if($active_section!=='ops') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.deliveryman_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Add and manage delivery men') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::ops'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="op-dm"><span>{{ translate('messages.deliverymen_list') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_dm)
                        <a class="v2-nav-item {{ $is('vendor-panel/delivery-man/add*') ? 'is-active' : '' }}" href="{{ route('vendor.delivery-man.add') }}" data-id="op-dma">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_delivery_man') }}</span>
                            <button type="button" class="v2-pin" data-pin="op-dma" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_dm_list)
                        <a class="v2-nav-item {{ ($is('vendor-panel/delivery-man/list*') || $is('vendor-panel/delivery-man/edit/*') || $is('vendor-panel/delivery-man/preview/*')) ? 'is-active' : '' }}" href="{{ route('vendor.delivery-man.list') }}" data-id="op-dml">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.deliverymen_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="op-dml" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_wallet || $can_wal_method)
        <div class="v2-panel-content" data-panel="finance" @if($active_section!=='finance') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.Wallet Management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Wallet balance, transactions and disbursement methods') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::finance'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fn-wal"><span>{{ translate('messages.my_wallet') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_wallet)
                        <a class="v2-nav-item {{ $is('vendor-panel/wallet') ? 'is-active' : '' }}" href="{{ route('vendor.wallet.index') }}" data-id="fn-wal">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.my_wallet') }}</span>
                            <button type="button" class="v2-pin" data-pin="fn-wal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_wal_method)
                        <a class="v2-nav-item {{ ($is('vendor-panel/withdraw-method*') || $is('vendor-panel/wallet-method*')) ? 'is-active' : '' }}" href="{{ route('vendor.wallet-method.index') }}" data-id="fn-wmt">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.disbursement_method') }}</span>
                            <button type="button" class="v2-pin" data-pin="fn-wmt" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_role || $can_employee)
        <div class="v2-panel-content" data-panel="team" @if($active_section!=='team') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.employee_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Roles and employees with permissions') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::team'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="tm-role"><span>{{ translate('messages.employee_Role') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_role)
                        <a class="v2-nav-item {{ $is('vendor-panel/custom-role*') ? 'is-active' : '' }}" href="{{ route('vendor.custom-role.create') }}" data-id="tm-role">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.employee_Role') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-role" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @if($can_employee)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="tm-emp"><span>{{ translate('messages.employees') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/employee/add-new*') ? 'is-active' : '' }}" href="{{ route('vendor.employee.add-new') }}" data-id="tm-empa">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_new_Employee') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-empa" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('vendor-panel/employee/list*') || $is('vendor-panel/employee/edit/*')) ? 'is-active' : '' }}" href="{{ route('vendor.employee.list') }}" data-id="tm-empl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Employee_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-empl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_exp_rep || $can_vat_rep || $can_disb_rep)
        <div class="v2-panel-content" data-panel="reports" @if($active_section!=='reports') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.Report_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Earnings, expenses, disbursements and tax') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::reports'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_exp_rep)
                        <a class="v2-nav-item {{ $is('vendor-panel/report/expense-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.expense-report') }}" data-id="rp-exp">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.expense_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-exp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/report/store-earning-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.store-earning-report') }}" data-id="rp-ern">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Store_Earning_Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-ern" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_disb_rep)
                        <a class="v2-nav-item {{ $is('vendor-panel/report/disbursement-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.disbursement-report') }}" data-id="rp-dis">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.disbursement_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-dis" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_vat_rep)
                        <a class="v2-nav-item {{ ($is('vendor-panel/report/vendor-tax*') || $is('vendor-panel/report/vendorTax*') || $is('vendor-panel/report/tax*')) ? 'is-active' : '' }}" href="{{ route('vendor.report.vendorTax') }}" data-id="rp-vat">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.Vat_Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-vat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_store_setup || $can_notif_setup || $can_my_shop || $can_subscription || $can_reviews || $can_chat)
        <div class="v2-panel-content" data-panel="settings" @if($active_section!=='settings') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.business_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Store profile, notifications, subscription and chat') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::settings'])

                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_my_shop)
                        <a class="v2-nav-item {{ $is('vendor-panel/store/*') ? 'is-active' : '' }}" href="{{ route('vendor.shop.view') }}" data-id="st-shop">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.my_shop') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-shop" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_store_setup)
                        <a class="v2-nav-item {{ $is('vendor-panel/business-settings/store-setup*') ? 'is-active' : '' }}" href="{{ route('vendor.business-settings.store-setup') }}" data-id="st-cfg">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.storeConfig') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-cfg" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_notif_setup)
                        <a class="v2-nav-item {{ $is('vendor-panel/business-settings/notification-setup*') ? 'is-active' : '' }}" href="{{ route('vendor.business-settings.notification-setup') }}" data-id="st-not">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.notification_setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-not" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_subscription)
                        <a class="v2-nav-item {{ $is('vendor-panel/subscription*') ? 'is-active' : '' }}" href="{{ route('vendor.subscriptionackage.subscriberDetail') }}" data-id="st-sub">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.My_Business_Plan') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-sub" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_reviews && (\App\CentralLogics\Helpers::get_business_settings('review_section') ?? 1))
                        <a class="v2-nav-item {{ $is('vendor-panel/reviews*') ? 'is-active' : '' }}" href="{{ route('vendor.reviews') }}" data-id="st-rev">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.reviews') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-rev" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_chat)
                        <a class="v2-nav-item {{ $is('vendor-panel/message*') ? 'is-active' : '' }}" href="{{ route('vendor.message.list') }}" data-id="st-chat">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Chat') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-chat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.vendor.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
