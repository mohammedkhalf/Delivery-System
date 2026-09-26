@php($isStorefrontCustomer = (int) ($customer->sub_tenant_id ?? 0) > 0)
@php($showTabs = ! $isStorefrontCustomer && (addon_published_status('Rental') || addon_published_status('RideShare') || addon_published_status('Service') || \App\CentralLogics\Helpers::get_business_settings('pro_member_status') == 1))
@if ($showTabs)
    <div class="js-nav-scroller hs-nav-scroller-horizontal mb-4">
        <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
            <li class="nav-item">
                <a class="nav-link {{ Route::is('admin.users.customer.view') ? 'active' : '' }}"
                    href="{{ route('admin.users.customer.view', $customer->id) }}">
                    {{ translate('messages.Overview') }}
                </a>
            </li>
            @if (addon_published_status('Rental'))
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.users.customer.rental.view') ? 'active' : '' }}"
                        href="{{ route('admin.users.customer.rental.view', ['module' => true, 'user_id' => $customer->id]) }}">
                        {{ translate('Rental_Module') }}
                    </a>
                </li>
            @endif
            @if (addon_published_status('RideShare'))
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.users.customer.ride-share.view') ? 'active' : '' }}"
                        href="{{ route('admin.users.customer.ride-share.view', ['module' => true, 'user_id' => $customer->id]) }}">
                        {{ translate('RideShare_Module') }}
                    </a>
                </li>
            @endif
            @if (addon_published_status('Service'))
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.users.customer.service.view') ? 'active' : '' }}"
                        href="{{ route('admin.users.customer.service.view', ['user_id' => $customer->id]) }}">
                        {{ translate('Service_Module') }}
                    </a>
                </li>
            @endif
            @if (\App\CentralLogics\Helpers::get_business_settings('pro_member_status') == 1)
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.users.customer.subscription-plan') ? 'active' : '' }}"
                        href="{{ route('admin.users.customer.subscription-plan', $customer->id) }}">
                        {{ translate('messages.Subscription_Plan') }}
                    </a>
                </li>
            @endif
        </ul>
    </div>
@endif