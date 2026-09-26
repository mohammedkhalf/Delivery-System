@extends('layouts.vendor.app')
@section('title','Edit Role')
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">

    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.edit_role')}}
            </span>
        </h1>
    </div>


    <form action="{{route('vendor.custom-role.update-role',[$role['id']])}}" method="post">
        @csrf
        @php($language=\App\Models\BusinessSetting::where('key','language')->first())
        @php($language = $language->value ?? null)
        @php($defaultLang = str_replace('_', '-', app()->getLocale()))
        @php($isServiceStore = \App\CentralLogics\Helpers::get_store_data()?->module?->module_type === 'service')
        @php($isRentalStore = \App\CentralLogics\Helpers::get_store_data()?->module?->module_type === 'rental')
        <div class="card mb-20">
            <div class="card-body">
                <div class="mb-20">
                    <h4 class="title-clr fs-18 mb-1">{{ translate('messages.role_form') }}</h4>
                    <p class="fs-12 mb-0">{{ translate('messages.Create role and assignee the role module & usage permission.') }}</p>
                </div>
                <div class="bg-light2 rounded p-xxl-20 p-3">
                @if($language)
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
                    <div class="lang_form" id="default-form">
                        <div class="form-group">
                            <label class="input-label" for="default_title">{{translate('messages.role_name')}} ({{translate('messages.default')}})</label>
                            <input type="text" name="name[]" id="default_title" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$role?->getRawOriginal('name')}}"  >
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                    @foreach(json_decode($language) as $lang)
                        <?php
                            if(count($role['translations'])){
                                $translate = [];
                                foreach($role['translations'] as $t)
                                {
                                    if($t->locale == $lang && $t->key=="name"){
                                        $translate[$lang]['name'] = $t->value;
                                    }
                                }
                            }
                        ?>
                        <div class="d-none lang_form" id="{{$lang}}-form">
                            <div class="form-group">
                                <label class="input-label" for="{{$lang}}_title">{{translate('messages.role_name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" id="{{$lang}}_title" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$translate[$lang]['name']??''}}"  >
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        </div>
                    @endforeach
                @else
                <div id="default-form">
                    <div class="form-group">
                        <label class="input-label" for="name">{{translate('messages.role_name')}} ({{ translate('messages.default') }})</label>
                        <input type="text" id="name" name="name[]" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$role['name']}}" maxlength="100" required>
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                </div>
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
                                                        id="dashboard" {{in_array('dashboard',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="dashboard">{{translate('messages.Dashboard')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="profile" class="form-check-input rounded"
                                                        id="profile" {{in_array('profile',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="order" {{in_array('order',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="order">{{translate('messages.All Orders')}}</label>
                                                </div>
                                            </div>
                                            @if (\App\CentralLogics\Helpers::employee_module_permission_check('pos'))
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="pos" class="form-check-input rounded"
                                                        id="pos" {{in_array('pos',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="item" {{in_array('item',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="item">{{translate('messages.Items')}}</label>
                                                </div>
                                            </div>
                                            @if (config('module.'.\App\CentralLogics\Helpers::get_store_data()->module->module_type)['add_on'])
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="addon" class="form-check-input rounded"
                                                        id="addon" {{in_array('addon',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="addon">{{translate('messages.Addons')}}</label>
                                                </div>
                                            </div>
                                            @endif
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="category" class="form-check-input rounded"
                                                        id="category" {{in_array('category',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                    <input type="checkbox" name="modules[]" value="service_booking" class="form-check-input rounded" id="service_booking" {{in_array('service_booking',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="service_booking">{{translate('messages.Bookings')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="service_management" class="form-check-input rounded" id="service_management" {{in_array('service_management',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="service_management">{{translate('messages.Services')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="category" class="form-check-input rounded" id="category" {{in_array('category',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="category">{{translate('messages.Categories')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="serviceman" class="form-check-input rounded" id="serviceman" {{in_array('serviceman',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="serviceman">{{translate('messages.Serviceman')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="service_request" class="form-check-input rounded" id="service_request" {{in_array('service_request',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="service_request">{{translate('messages.Service_Requests')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="custom_request" class="form-check-input rounded" id="custom_request" {{in_array('custom_request',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="custom_request">{{translate('messages.Custom_Requests')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="report" class="form-check-input rounded" id="report" {{in_array('report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="report">{{translate('messages.Reports')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="vat_report" class="form-check-input rounded" id="vat_report" {{in_array('vat_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vat_report">{{translate('messages.Vat Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="expense_report" class="form-check-input rounded" id="expense_report" {{in_array('expense_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="expense_report">{{translate('messages.Expense Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="disbursement_report" class="form-check-input rounded" id="disbursement_report" {{in_array('disbursement_report',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                    <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input rounded" id="vehicle" {{in_array('vehicle',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle">{{translate('messages.Vehicle')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="trip" class="form-check-input rounded" id="trip" {{in_array('trip',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="trip">{{translate('messages.Trip')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="driver" class="form-check-input rounded" id="driver" {{in_array('driver',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="driver">{{translate('messages.Driver')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="marketing" class="form-check-input rounded" id="marketing" {{in_array('marketing',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="campaign" {{in_array('campaign',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="campaign">{{translate('messages.Campaign')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="coupon" class="form-check-input rounded"
                                                        id="coupon" {{in_array('coupon',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="coupon">{{translate('messages.Coupon')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="banner" class="form-check-input rounded"
                                                        id="banner" {{in_array('banner',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                                id="reels" {{in_array('reels',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="advertisement" {{in_array('advertisement',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="advertisement">{{translate('messages.New Advertisement')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="advertisement_list" class="form-check-input rounded"
                                                        id="advertisement_list" {{in_array('advertisement_list',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="deliveryman" {{in_array('deliveryman',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman">{{translate('messages.New deliveryman')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="deliveryman_list" class="form-check-input rounded"
                                                        id="deliveryman_list" {{in_array('deliveryman_list',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="wallet" {{in_array('wallet',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="wallet">{{translate('messages.My Wallet')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="wallet_method" class="form-check-input rounded"
                                                        id="wallet_method" {{in_array('wallet_method',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="employee" {{in_array('employee',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                            id="report" {{in_array('report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="report">{{translate('messages.Reports')}}</label>
                                                </div>
                                            </div>
                                            @endif
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="expense_report" class="form-check-input rounded"
                                                            id="expense_report" {{in_array('expense_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="expense_report">{{translate('messages.Expense Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="disbursement_report" class="form-check-input rounded"
                                                        id="disbursement_report" {{in_array('disbursement_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="disbursement_report">{{translate('messages.Disbursement Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group form-check form--check m-0">
                                                    <input type="checkbox" name="modules[]" value="vat_report" class="form-check-input rounded"
                                                        id="vat_report" {{in_array('vat_report',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                        id="store_setup" {{in_array('store_setup',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label text-nowrap qcont text-dark" for="store_setup">{{translate('messages.Store Setup')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="notification_setup" class="form-check-input rounded"
                                                    id="notification_setup" {{in_array('notification_setup',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label text-nowrap qcont text-dark" for="notification_setup">{{translate('messages.Notification Setup')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="my_shop" class="form-check-input rounded"
                                                    id="my_shop" {{in_array('my_shop',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label text-nowrap qcont text-dark" for="my_shop">{{translate('messages.MY Shop')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="business_plan" class="form-check-input rounded"
                                                    id="business_plan" {{in_array('business_plan',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label text-nowrap qcont text-dark" for="business_plan">{{translate('messages.Business Plan')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    @if (\App\CentralLogics\Helpers::check_website_builder_status())
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item p-2">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="custom_website" class="form-check-input rounded"
                                                    id="custom_website" {{in_array('custom_website',(array)json_decode($role['modules']))?'checked':''}}>
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
                                                    id="reviews" {{in_array('reviews',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label text-nowrap qcont text-dark" for="reviews">{{translate('messages.Reviews')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 col-sm-4">
                                        <div class="check-item m-0 p-0">
                                            <div class="form-group form-check form--check m-0">
                                                <input type="checkbox" name="modules[]" value="chat" class="form-check-input rounded"
                                                    id="chat" {{in_array('chat',(array)json_decode($role['modules']))?'checked':''}}>
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
                    <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                </div>
            </form>
</div>
@endsection

@push('script_2')
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

    function initializeCheckboxStates() {
        $('.select-subwrapper').each(function () {
            const groupCheckboxes = $(this).find('input[type="checkbox"][name="modules[]"]');
            const groupChecked = groupCheckboxes.filter(':checked').length;
            $(this).find('.check-all').prop('checked', groupChecked === groupCheckboxes.length);
        });

        updateGlobalSelectAll();
    }

    function updateGlobalSelectAll() {
        const all = $('input[type="checkbox"][name="modules[]"]').length;
        const checked = $('input[type="checkbox"][name="modules[]"]:checked').length;
        $('#select-all').prop('checked', all === checked);
    }

    initializeCheckboxStates();
});
</script>

@endpush
