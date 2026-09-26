@extends('layouts.admin.app')
@section('title',translate('messages.Store_Subscription'))
@section('subscriberList')
active
@endsection
@push('css_or_js')

@endpush

@section('content')

    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center py-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-start">
                        <img src="{{asset('/public/assets/admin/img/store.png')}}" width="24" alt="img">
                        <div class="w-0 flex-grow pl-2">
                            <h1 class="page-header-title">{{ $store->name }} {{translate('Subscription')}} &nbsp; &nbsp;
                                @if($store?->status == 0 &&  $store?->vendor?->status == 0)
                                <span class=" badge badge-pill badge-info">  &nbsp; {{ translate('Approval_Pending') }}  &nbsp; </span>
                                @elseif($store?->store_sub_update_application?->status == 0)
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
                    <a href="" class="nav-link active">{{ translate('Subscription_Details') }} </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.business-settings.subscriptionackage.subscriberTransactions',$store->id) }}" class="nav-link">{{ translate('Transactions') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.business-settings.subscriptionackage.subscriberWalletTransactions',$store->id) }}" class="nav-link">{{ translate('Subscription_Refunds') }}</a>
                </li>
            </ul>
        </div>
        <div class="card mb-20">
            <div class="card-header border-0 align-items-center">
                <h4 class="card-title align-items-center gap-2">
                    <span class="card-header-icon">
                        <img src="{{asset('public/assets/admin/img/store-3.png')}}" alt="">
                    </span>
                    <span class="text-title">{{ translate('Store_Info') }}</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="resturant--info-address">
                                    <div class="logo">
                                        <a href="{{route('admin.store.view', $store->id)}}">
                                            <img class="onerror-image"
                                            src="{{ $store->logo_full_url ?? asset('public/assets/admin/img/100x100/1.png') }}">
                                        </a>
                                    </div>
                                    <ul class="address-info list-unstyled list-unstyled-py-3 text-dark">
                                        <li>
                                            <h5 class="name">
                                                {{ $store->name }}
                                            </h5>
                                        </li>

                                        <li>
                                            <i class="tio-call-talking nav-icon"></i>
                                            <span class="pl-1">
                                                <a href="tel:{{ $store->phone }}">
                                                    {{ $store->phone }}
                                                </a>
                                            </span>
                                        </li>
                                        <li>
                                            <i class="tio-email nav-icon"></i>
                                            <span class="pl-1">
                                                <a href="mailto:{{ $store->email }}">
                                                    {{ $store->email }}
                                                </a>
                                            </span>
                                        </li>
                                        <li>
                                            <i class="tio-city nav-icon"></i>
                                            <span class="pl-1">
                                                {{ $store->address }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="resturant--info-address">
                                    <ul class="address-info list-unstyled list-unstyled-py-3 text-dark pl-0">
                                        <li>
                                            <h5 class="name">
                                                {{ translate('Owner Info') }}
                                            </h5>
                                        </li>
                                        <li>
                                            <h5 class="name text-title">
                                                {{ $store?->vendor?->f_name  .' '. $store?->vendor?->l_name}}
                                            </h5>
                                        </li>
                                        <li>
                                            <i class="tio-call-talking nav-icon"></i>
                                            <span class="pl-1">
                                               <a href="tel:{{ $store?->vendor?->phone}}">
                                                {{ $store?->vendor?->phone}}
                                               </a>
                                            </span>
                                        </li>
                                        <li>
                                            <i class="tio-email nav-icon"></i>
                                            <span class="pl-1">
                                            <a href="mailto:{{ $store?->vendor?->email}}">{{ $store?->vendor?->email}}</a>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($store->store_business_model == 'commission' && \App\CentralLogics\Helpers::commission_check())

        <div class="card mb-3">
            <div class="card-header flex-wrap gap-2 border-0 align-items-center">
                <div>
                    <h3 class="card-title mb-1 align-items-center gap-2">
                        <span class="text-title">{{ translate('Package Overview') }}</span>
                    </h3>
                    <span class="fs-12 d-block color-334257B2">{{ translate('Here you see the active business plan.') }}</span>
                </div>
                <div class="btn--container justify-content-end m-0">
                    <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary">{{ translate('Change Business Plan') }}</button>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="bg-F7F8F9 p--20 rounded mb-20">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <div class="max-w-595">
                                <h3 class="name">{{ translate('Commission Base Plan') }}</h3>
                                <div class="info-text fs-14">
                                    {{ translate('Store will pay') }} {{ $store->comission ?? $admin_commission }}% {{ translate('commission to') }} <strong>{{ $business_name }}</strong> {{ translate('from each order. You will get access of all the features and options  in store panel , app and interaction with user.') }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-white d-flex align-items-center justify-content-between gap-2 flex-wrap rounded py-3 px-xxl-4 px-3">
                                <h4 class="title mt-2">
                                    <span class="text-180 fs-32 theme-clr">
                                     {{ $store->comission ?? $admin_commission }}%
                                    </span>
                                    <span class="fs-14 font-semibold d-block">{{ translate('messages.Commission_per_order') }}</span>
                                </h4>
                                <img width="40" src="{{asset('public/assets/admin/img/money-percentage.png')}}" alt="">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-F7F8F9 p--20 rounded">
                    <form action="{{route('admin.store.update-settings',[$store['id'] , 'tab' => 'business_plan'])}}" method="post">
                        @csrf
                        @method("post")
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <div class="max-w-595">
                                    <h3 class="name">{{ translate('Change Commission Rate') }}</h3>
                                    <div class="info-text fs-14">
                                        {{ translate('When enabled admin will only receive the certain commission percentage set for this store. Otherwise the system default commission will be applied.') }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-20">
                                    <label class="d-flex mb-1 justify-content-between switch toggle-switch-sm text-dark bg-white rounded border py-2 px-3 text-capitalize" for="comission_status">
                                        <span class="fs-14 lh-1">{{translate('messages.Status')}}</span>
                                        <input type="checkbox" class="toggle-switch-input" name="comission_status" id="comission_status" value="1" {{isset($store->comission)?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                                <div>
                                    <label class="d-flex mb-2 justify-content-between text-dark text-capitalize">
                                        <span>{{translate('messages.Change_Commission_Rate')}}(%)</span>
                                    </label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <input type="number" id="comission" min="0" max="10000" step="0.01" name="comission" class="form-control w-200px flex-grow-1 bg-white" required value="{{$store->comission??'0'}}" {{isset($store->comission)?'':'readonly'}}>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-end gap-3 mt-4">
                            <button type="submit" class="btn min-w-120px btn--reset h--45px">{{ translate('Reset') }}</button>
                            <button type="submit" class="btn min-w-120px btn--primary h--45px">{{ translate('Change') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @elseif (in_array($store->store_business_model,[ 'subscription' ,'unsubscribed']) && $store?->store_sub_update_application)

        @include('subscription.partials._billing', ['store' => $store])
        @include('subscription.partials._plan-overview', ['store' => $store, 'routePrefix' => 'admin.business-settings.subscriptionackage', 'isServiceModule' => $store?->module?->module_type == 'service', 'showPos' => !in_array($store?->module?->module_type, ['rental', 'service']), 'showSelfDelivery' => !in_array($store?->module?->module_type, ['rental', 'service']), 'showStatusBadge' => false])

        @else

        @include('subscription.partials._empty-state', ['isServiceModule' => $store?->module?->module_type == 'service'])

        @endif

        @include('subscription.partials._plan-modal', ['store' => $store, 'packages' => $packages, 'admin_commission' => $admin_commission, 'business_name' => $business_name, 'routePrefix' => 'admin.business-settings.subscriptionackage', 'title' => 'Store', 'orderOrTrip' => 'order', 'isServiceModule' => $store?->module?->module_type == 'service', 'showPos' => !in_array($store?->module?->module_type, ['rental', 'service']), 'showSelfDelivery' => !in_array($store?->module?->module_type, ['rental', 'service'])])

        @include('subscription.partials._renew-modal')

        @include('subscription.partials._product-warning-modal', ['isServiceModule' => $store?->module?->module_type == 'service'])

    </div>

@endsection

@push('script_2')
    @include('subscription.partials._scripts', ['store' => $store, 'index' => $index, 'routePrefix' => 'admin.business-settings.subscriptionackage', 'enableQuickActions' => false])
@endpush
