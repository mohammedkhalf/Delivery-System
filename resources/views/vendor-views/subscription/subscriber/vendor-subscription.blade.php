@php
    $vendorData = \App\CentralLogics\Helpers::get_store_data();
    $vendor = $vendorData?->module_type;
    $isServiceModule = $vendor == 'service';
    $title = ($vendor == 'rental' || $isServiceModule) ? 'Provider' : 'Store';
    $orderOrTrip = $vendor == 'rental' ? 'trip' : ($isServiceModule ? 'booking' : 'order');
@endphp
@extends('layouts.vendor.app')
@section('title',translate('messages.' . $title . '_Subscription'))
@section('subscriberList')
active
@endsection
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">

    @if ($store->store_business_model == 'commission' &&  \App\CentralLogics\Helpers::commission_check())

    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center py-2">
            <div class="flex-grow-1">
                <div class="d-flex align-items-start">
                    <img src="{{asset('/public/assets/admin/img/store.png')}}" width="24" alt="img">
                    <div class="w-0 flex-grow pl-2">
                        <h1 class="page-header-title">{{ $store->name }} {{translate('Business_Plan')}} &nbsp; &nbsp;

                        </h1>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($store->store_all_sub_trans_count > 0)

    <div class="js-nav-scroller hs-nav-scroller-horizontal mb-4">
        <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
            <li class="nav-item">
                <a href="#" class="nav-link active">{{ translate('Business_Details') }} </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('vendor.subscriptionackage.subscriberTransactions',$store->id) }}" class="nav-link">{{ translate('Transactions') }}</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('vendor.subscriptionackage.subscriberWalletTransactions') }}" class="nav-link">{{ translate('Subscription_Refunds') }}</a>
            </li>
        </ul>
    </div>

    @endif

    <div class="card mb-3">
        <div class="card-header border-0 align-items-center">
            <h4 class="card-title align-items-center gap-2">
                <span class="card-header-icon">
                    <img width="25" src="{{asset('public/assets/admin/img/subscription-plan/subscribed-user.png')}}" alt="">
                </span>
                <span>{{ translate('Overview') }}</span>
            </h4>
        </div>
        <div class="card-body pt-0">
            <div class="__bg-F8F9FC-card __plan-details">
                <div class="d-flex flex-wrap flex-md-nowrap justify-content-between __plan-details-top">
                    <div class="w-100">
                        <h2 class="name text--primary">{{ translate('Commission Base Plan') }}</h2>
                        <h4 class="title mt-2"><span class="text-180">{{ $store->comission ?? $admin_commission }} %</span> {{ translate('messages.Commission_per_'.$orderOrTrip) }}</h4>
                        <div class="info-text ">
                            {{ translate($title . ' will pay') }} {{ $store->comission ?? $admin_commission }}% {{ translate('commission to') }} <strong>{{ $business_name }}</strong> {{ translate('from each '.$orderOrTrip.'. You will get access of all the features and options  in '.$title.' panel , app and interaction with user.') }}
                        </div>
                    </div>
                </div>
            </div>
            @if (\App\CentralLogics\Helpers::subscription_check() )
                <div class="btn--container justify-content-end mt-20">
                    <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary">{{ translate('Change Business Plan') }}</button>
                </div>
            @endif
        </div>
    </div>

    @elseif (in_array($store->store_business_model,[ 'subscription' ,'unsubscribed']) && $store?->store_sub_update_application)

        <div class="page-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center py-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-start">
                        <img src="{{asset('/public/assets/admin/img/store.png')}}" width="24" alt="img">
                        <div class="w-0 flex-grow pl-2">
                            <h1 class="page-header-title">{{ $store->name }} {{translate('Subscription')}} &nbsp; &nbsp;
                                @if($store?->store_sub_update_application?->status == 0)
                                <span class=" badge badge-pill badge-danger">  &nbsp; {{ translate('Expired') }}  &nbsp; </span>
                                @elseif ($store?->store_sub_update_application?->is_canceled == 1)
                                <span class=" badge badge-pill badge-warning">  &nbsp; {{ translate('canceled') }}  &nbsp; </span>
                                @elseif($store?->store_sub_update_application?->status == 1)
                                <span class=" badge badge-pill badge-success">  &nbsp; {{ translate('Active') }}  &nbsp; </span>
                                @endif
                            </h1>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="js-nav-scroller hs-nav-scroller-horizontal mb-4">
            <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
                <li class="nav-item">
                    <a href="#" class="nav-link active">{{ translate('Subscription_Details') }} </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('vendor.subscriptionackage.subscriberTransactions',$store->id) }}" class="nav-link">{{ translate('Transactions') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('vendor.subscriptionackage.subscriberWalletTransactions') }}" class="nav-link">{{ translate('Subscription_Refunds') }}</a>
                </li>
            </ul>
        </div>

        @include('subscription.partials._billing', ['store' => $store])
        @include('subscription.partials._plan-overview', ['store' => $store, 'routePrefix' => 'vendor.subscriptionackage', 'isServiceModule' => $isServiceModule, 'showPos' => !in_array($store?->module?->module_type, ['rental', 'service']), 'showSelfDelivery' => !in_array($store?->module?->module_type, ['rental', 'service']), 'showStatusBadge' => false])

    @else

        @include('subscription.partials._empty-state', ['isServiceModule' => $isServiceModule])

    @endif

    @include('subscription.partials._plan-modal', ['store' => $store, 'packages' => $packages, 'admin_commission' => $admin_commission, 'business_name' => $business_name, 'routePrefix' => 'vendor.subscriptionackage', 'title' => $title, 'orderOrTrip' => $orderOrTrip, 'isServiceModule' => $isServiceModule, 'showPos' => !in_array($store?->module?->module_type, ['rental', 'service']), 'showSelfDelivery' => !in_array($store?->module?->module_type, ['rental', 'service'])])

    @include('subscription.partials._renew-modal')

    @include('subscription.partials._product-warning-modal', ['isServiceModule' => $isServiceModule])

</div>
@endsection

@push('script_2')
    @include('subscription.partials._scripts', ['store' => $store, 'index' => $index, 'routePrefix' => 'vendor.subscriptionackage', 'enableQuickActions' => true])
@endpush
