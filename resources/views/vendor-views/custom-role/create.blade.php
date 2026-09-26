@extends('layouts.vendor.app')
@section('title',translate('messages.Create_Role'))
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">

    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/role.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.custom_role')}}
            </span>
        </h1>
    </div>

    @php($language=\App\Models\BusinessSetting::where('key','language')->first())
    @php($language = $language->value ?? null)
    @php($isServiceStore = \App\CentralLogics\Helpers::get_store_data()?->module?->module_type === 'service')
    @php($isRentalStore = \App\CentralLogics\Helpers::get_store_data()?->module?->module_type === 'rental')

    <form action="{{route('vendor.custom-role.create')}}" method="post">
        @csrf
        <div class="card mb-20">
            <div class="card-body">
                <div class="mb-20">
                    <h4 class="title-clr fs-18 mb-1">{{ translate('messages.role_form') }}</h4>
                    <p class="fs-12 mb-0">{{ translate('messages.Create role and assignee the role module & usage permission.') }}</p>
                </div>
                <div class="bg-light2 rounded p-xxl-20 p-3">
                @if ($language)
                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link lang_link active"
                                href="#"
                                id="default-link">{{translate('messages.default')}}</a>
                            </li>
                            @foreach (json_decode($language) as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link"
                                        href="#"
                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                </li>
                            @endforeach
                        </ul>
                            <div class="form-group lang_form" id="default-form">
                                <label class="input-label" for="name">{{translate('messages.role_name')}} ({{ translate('messages.default') }})</label>
                                <input type="text" id="name" name="name[]" class="form-control" required placeholder="{{translate('role_name_example')}}" maxlength="191"  >
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                    <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                        <label class="input-label" for="name{{$lang}}">{{translate('messages.role_name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" id="name{{$lang}}" name="name[]" class="form-control" placeholder="{{translate('role_name_example')}}" maxlength="191"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="form-group">
                                    <label class="input-label" for="name">{{translate('messages.role_name')}}</label>
                                    <input type="text" id="name" name="name" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{old('name')}}" required maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex w-100 justify-content-between flex-wrap select--all-checkes gap-2">
                    <h5 class="input-label m-0 fs-18 title-clr text-capitalize">{{translate('messages.Module Wise Permission')}}</h5>
                    <div class="check-item check-item-custom pb-0 w-auto">
                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                            <input type="checkbox" value="" class="form-check-input rounded position-relative rounded mt-0" id="select-all">
                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="select-all">{{ translate('messages.All Module Permission') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                    <div class="check--item-wrapper check--item-wrapper-custom">
                        <div class="row g-3">
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.dashboard') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-1" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="dashboard" class="form-check-input rounded"
                                                        id="dashboard">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="dashboard">{{translate('messages.Dashboard')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="profile" class="form-check-input rounded"
                                                        id="profile">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="profile">{{translate('messages.Profile')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if(!$isServiceStore && !$isRentalStore)
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Sales') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-2" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-2">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="order" class="form-check-input rounded"
                                                        id="order">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="order">{{translate('messages.All Orders')}}</label>
                                                </div>
                                            </div>
                                            @if (\App\CentralLogics\Helpers::employee_module_permission_check('pos'))
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="pos" class="form-check-input rounded"
                                                        id="pos">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="pos">{{translate('messages.Point of Sale (POS)')}}</label>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Catalog') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-3" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-3">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-lg-nowrap flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="item" class="form-check-input rounded"
                                                        id="item">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="item">{{translate('messages.Items')}}</label>
                                                </div>
                                            </div>
                                            @if (config('module.'.\App\CentralLogics\Helpers::get_store_data()->module->module_type)['add_on'])
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="addon" class="form-check-input rounded"
                                                        id="addon">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="addon">{{translate('messages.Addons')}}</label>
                                                </div>
                                            </div>
                                            @endif
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="category" class="form-check-input rounded"
                                                        id="category">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="category">{{translate('messages.Categories')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            @if($isServiceStore)
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.Service Management') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-service" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-service">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="service_booking" class="form-check-input rounded" id="service_booking">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="service_booking">{{translate('messages.Bookings')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="service_management" class="form-check-input rounded" id="service_management">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="service_management">{{translate('messages.Services')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="category" class="form-check-input rounded" id="category">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="category">{{translate('messages.Categories')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="serviceman" class="form-check-input rounded" id="serviceman">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="serviceman">{{translate('messages.Serviceman')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="service_request" class="form-check-input rounded" id="service_request">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="service_request">{{translate('messages.Service_Requests')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="custom_request" class="form-check-input rounded" id="custom_request">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="custom_request">{{translate('messages.Custom_Requests')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="report" class="form-check-input rounded" id="report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="report">{{translate('messages.Reports')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="vat_report" class="form-check-input rounded" id="vat_report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vat_report">{{translate('messages.Vat Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="expense_report" class="form-check-input rounded" id="expense_report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="expense_report">{{translate('messages.Expense Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="disbursement_report" class="form-check-input rounded" id="disbursement_report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="disbursement_report">{{translate('messages.Disbursement Report')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            @if($isRentalStore)
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.Rental Management') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-rental" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-rental">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input rounded" id="vehicle">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle">{{translate('messages.Vehicle')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="trip" class="form-check-input rounded" id="trip">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="trip">{{translate('messages.Trip')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="driver" class="form-check-input rounded" id="driver">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="driver">{{translate('messages.Driver')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="marketing" class="form-check-input rounded" id="marketing">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="marketing">{{translate('messages.Marketing')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Marketing') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-4" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-4">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-lg-nowrap flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="campaign" class="form-check-input rounded"
                                                        id="campaign">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="campaign">{{translate('messages.Campaign')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="coupon" class="form-check-input rounded"
                                                        id="coupon">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="coupon">{{translate('messages.Coupon')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="banner" class="form-check-input rounded"
                                                        id="banner">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="banner">{{translate('messages.Banner')}}</label>
                                                </div>
                                            </div>

                                             @if (
                                                addon_published_status('ReelsModule')
                                                && \App\CentralLogics\Helpers::get_business_settings('vendor_can_upload_reels')
                                                && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType(\App\CentralLogics\Helpers::get_store_data()?->module?->module_type)
                                                )
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="reels" class="form-check-input rounded"
                                                            id="reels">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="reels">{{translate('messages.reels')}}</label>
                                                </div>
                                            </div>

                                            @endif

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.Advertisement Management') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-5" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-5">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                                <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="advertisement" class="form-check-input rounded"
                                                        id="advertisement">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="advertisement">{{translate('messages.New Advertisement')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="advertisement_list" class="form-check-input rounded"
                                                        id="advertisement_list">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="advertisement_list">{{translate('messages.Advertisement List')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if (!$isServiceStore && \App\CentralLogics\Helpers::get_store_data()->sub_self_delivery)
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.Delivery Man Management') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-10" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-10">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                                <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="deliveryman" class="form-check-input rounded"
                                                        id="deliveryman">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman">{{translate('messages.New deliveryman')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="deliveryman_list" class="form-check-input rounded"
                                                        id="deliveryman_list">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman_list">{{translate('messages.Deliveryman List')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.Wallet Management') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-6" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-6">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="wallet" class="form-check-input rounded"
                                                        id="wallet">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="wallet">{{translate('messages.My Wallet')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="wallet_method" class="form-check-input rounded"
                                                        id="wallet_method">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="wallet_method">{{translate('messages.Wallet Method')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Employee Section') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-7" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-7">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="employee" class="form-check-input rounded"
                                                        id="employee">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="employee">{{translate('messages.All Employee')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if(!$isServiceStore)
                            <div class="col-lg-12 mb-20">
                                <div class="bg-light2 rounded select-subwrapper h-100">
                                    <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                        <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('messages.Report Section') }}</h5>
                                        <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                            <label for="select-allsub-8" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                            <div class="form-group form-check form--check m-0 ml-2">
                                                <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-8">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex flex-wrap module-wise-gap">
                                            @if($isRentalStore)
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="report" class="form-check-input rounded"
                                                            id="report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="report">{{translate('messages.Reports')}}</label>
                                                </div>
                                            </div>
                                            @endif
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="expense_report" class="form-check-input rounded"
                                                            id="expense_report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="expense_report">{{translate('messages.Expense Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="disbursement_report" class="form-check-input rounded"
                                                        id="disbursement_report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="disbursement_report">{{translate('messages.Disbursement Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="vat_report" class="form-check-input rounded"
                                                        id="vat_report">
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vat_report">{{translate('messages.Vat Report')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 check--item-wrapper check--item-wrapper-custom">
                        <div class="bg-light2 rounded select-subwrapper h-100">
                            <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Business Section') }}</h5>
                                <div class="check-item p-2 d-flex align-items-center gap-2 pb-0 w-auto cursor-pointer">
                                    <label for="select-allsub-9" class="fs-14 text-title m-0">{{ translate('messages.Select All') }}</label>
                                    <div class="form-group form-check form--check m-0 ml-2">
                                        <input type="checkbox" name="" value="" class="form-check-input rounded position-relative rounded check-all" id="select-allsub-9">
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <div class="m-0 row g-3">
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="store_setup" class="form-check-input rounded"
                                                        id="store_setup">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="store_setup">{{translate('messages.Store Setup')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="notification_setup" class="form-check-input rounded"
                                                    id="notification_setup">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="notification_setup">{{translate('messages.Notification Setup')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="my_shop" class="form-check-input rounded"
                                                    id="my_shop">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="my_shop">{{translate('messages.MY Shop')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="business_plan" class="form-check-input rounded"
                                                    id="business_plan">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="business_plan">{{translate('messages.Business Plan')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    @if (\App\CentralLogics\Helpers::check_website_builder_status())
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item p-2">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="custom_website" class="form-check-input rounded"
                                                    id="custom_website">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="custom_website">{{translate('messages.Custom Website')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('reviews'))
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                            <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="reviews" class="form-check-input rounded"
                                                    id="reviews">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="reviews">{{translate('messages.Reviews')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="chat" class="form-check-input rounded"
                                                    id="chat">
                                                <label class="form-check-label text-nowrap qcont text-dark" for="chat">{{translate('messages.Chat')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                    <button type="submit" class="btn btn--primary">{{translate('messages.submit')}}</button>
                </div>
            </form>

    <div class="card mt-3">
        <div class="card-header border-0">
            <div class="search--button-wrapper">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-document-text-outlined"></i>
                    </span>
                    <span>
                        {{translate('messages.roles_table')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$rl->total()}}</span>
                    </span>
                </h5>
                <form  class="search-form min--250">

                    <div class="input-group input--group">
                        <input  value="{{request()?->search ?? ''}}" type="search" name="search" class="form-control" placeholder="{{translate('messages.search_role')}}" aria-label="{{translate('messages.search')}}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>

                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0 w-50px">{{translate('messages.sl#')}}</th>
                            <th class="border-0 w-50px">{{translate('messages.role_name')}}</th>
                            <th class="border-0 w-100px">{{translate('messages.modules')}}</th>
                            <th class="border-0 w-50px">{{translate('messages.created_at')}}</th>
                            <th class="border-0 w-50px text-center">{{translate('messages.action')}}</th>
                        </tr>
                    </thead>
                    <tbody  id="set-rows">
                    @foreach($rl as $k=>$r)
                        <tr>
                            <td >{{$k+$rl->firstItem()}}</td>
                            <td>{{Str::limit($r['name'],20,'...')}}</td>
                            <td class="text-capitalize">
                                @if($r['modules']!=null)
                                    @foreach((array)json_decode($r['modules']) as $key=>$m)

                                    @if ($m == 'bank_info')
                                    {{translate('messages.profile')}}
                                    @else
                                    {{translate(str_replace('_',' ',$m))}}
                                    @endif

                                    {{  !$loop->last ? ',' : '.'}}
                                    @endforeach
                                @endif
                            </td>
                            <td>{{date('d-M-y',strtotime($r['created_at']))}}</td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn-theme-dark btn-outline-base offcanvas-trigger data-info-show"
                                        data-id="{{$r['id']}}" data-url="{{route('vendor.custom-role.view',[$r['id']])}}"
                                        href="#0" data-target="#offcanvas__role_table" title="{{translate('messages.view')}}">
                                        <i class="tio-visible"></i>
                                    </a>
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                        href="{{route('vendor.custom-role.edit',[$r['id']])}}" title="{{translate('messages.edit_role')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                       data-id="role-{{$r['id']}}" data-message="{{translate('messages.Want_to_delete_this_role')}}"
                                         title="{{translate('messages.delete_role')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                </div>
                                <form action="{{route('vendor.custom-role.delete',[$r['id']])}}"
                                        method="post" id="role-{{$r['id']}}">
                                    @csrf @method('delete')
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($rl) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    <table>
                        <tfoot>
                        {!! $rl->links() !!}
                        </tfoot>
                    </table>
                </div>
                @if(count($rl) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('no_data_found')}}
                    </h5>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div id="offcanvas__role_table" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div>
        <div id="data-view" class="h-100">  </div>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
<script>
    $(document).on('click', '.data-info-show', function() {
        let id = $(this).data('id');
        let url = $(this).data('url');
        $('#content-disable').addClass('disabled');
        fetch_data(id, url)
    })

    function fetch_data(id, url) {
        $.ajax({
            url: url,
            type: "get",
            beforeSend: function() {
                $('#data-view').empty();
                $('#loading').show();
            },
            success: function(data) {
                $("#data-view").append(data.view);
                bindOffcanvasClose();
            },
            complete: function() {
                $('#loading').hide()
            }
        })
    }

    function bindOffcanvasClose() {
        $('.offcanvas-close, #offcanvasOverlay').on('click', function () {
            $('.custom-offcanvas').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
            $('#content-disable').removeClass('disabled');
        });
    }
</script>
<script>
$(document).ready(function () {
    $('#select-all').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('input[type="checkbox"][name="modules[]"]').prop('checked', isChecked);
        $('.check-all').prop('checked', isChecked);
    });

    $('.check-all').on('change', function () {
        const container = $(this).closest('.select-subwrapper');
        const isChecked = $(this).is(':checked');
        container.find('input[type="checkbox"][name="modules[]"]').prop('checked', isChecked);

        updateGlobalSelectAll();
    });

    $('input[type="checkbox"][name="modules[]"]').on('change', function () {
        const container = $(this).closest('.select-subwrapper');
        const allInGroup = container.find('input[type="checkbox"][name="modules[]"]').length;
        const checkedInGroup = container.find('input[type="checkbox"][name="modules[]"]:checked').length;

        container.find('.check-all').prop('checked', allInGroup === checkedInGroup);

        updateGlobalSelectAll();
    });

    function updateGlobalSelectAll() {
        const all = $('input[type="checkbox"][name="modules[]"]').length;
        const checked = $('input[type="checkbox"][name="modules[]"]:checked').length;
        $('#select-all').prop('checked', all === checked);
    }
});
</script>
@endpush

