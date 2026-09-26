@extends('layouts.admin.app')
@section('title',translate('Edit Role'))
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
                {{translate('messages.employee_Role')}}
            </span>
        </h1>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="">
                <div class="">
                    <form action="{{route('admin.users.custom-role.update',[$role['id']])}}" method="post">
                        @csrf
                        <div class="card mb-20">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="title-clr fs-18 mb-1">{{ translate('messages.Role form') }}</h4>
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
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link"
                                                        href="#"
                                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="lang_form" id="default-form">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="default_title">{{translate('messages.role_name')}} ({{translate('messages.default')}}) <span class="form-label-secondary text-danger"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.')}}"> *
                                                    </span>
                                             </label>
                                                <input type="text" name="name[]" id="default_title" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$role?->getRawOriginal('name')}}"  >
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                        @foreach($language as $lang)
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
                                                <div class="form-group mb-0">
                                                    <label class="input-label" for="{{$lang}}_title">{{translate('messages.role_name')}} ({{strtoupper($lang)}})</label>
                                                    <input type="text" name="name[]" id="{{$lang}}_title" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$translate[$lang]['name']??''}}"  >
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{$lang}}">
                                            </div>
                                        @endforeach
                                    @else
                                    <div id="default-form">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.role_name')}} ({{ translate('messages.default') }})</label>
                                            <input type="text" name="name[]" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$role['name']}}" maxlength="100">
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
                                    <h5 class="input-label m-0 fs-18 title-clr text-capitalize">{{translate('messages.Set_permission')}} : </h5>
                                    <div class="check-item check-item-custom pb-0 w-auto">
                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                            <input type="checkbox" class="form-check-input mt-0" id="select-all">
                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="select-all">{{ translate('All Management') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="check--item-wrapper check--item-wrapper-custom">
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.General') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Profile Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_general_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_general_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="dashboard" class="form-check-input" id="cr_dashboard"  {{in_array('dashboard',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_dashboard">{{translate('messages.Dashboard')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="profile" class="form-check-input" id="cr_profile"  {{in_array('profile',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_profile">{{translate('messages.Profile')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.User Management') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('User Overview')}}</h5>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="user_overview" class="form-check-input" id="cr_user_overview"  {{in_array('user_overview',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_user_overview">{{translate('User Overview')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Customer Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_customer_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_customer_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="customer_management" class="form-check-input" id="cr_customer_management"  {{in_array('customer_management',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_customer_management">{{translate('Customer accounts')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="customer_wallet" class="form-check-input" id="cr_customer_wallet"  {{in_array('customer_wallet',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_customer_wallet">{{translate('messages.customer_wallet')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="customer_loyalty_point" class="form-check-input" id="cr_customer_loyalty_point"  {{in_array('customer_loyalty_point',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_customer_loyalty_point">{{translate('messages.customer_loyalty_point')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="cashback" class="form-check-input" id="cr_cashback"  {{in_array('cashback',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_cashback">{{translate('messages.Promotion_management')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.deliveryman_management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_delivery_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_delivery_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="deliveryman" class="form-check-input" id="cr_deliveryman"  {{in_array('deliveryman',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_deliveryman">{{translate('Deliveryman management')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if (addon_published_status('RideShare'))
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.rider')}} {{translate('management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rider_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rider_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rider" class="form-check-input" id="cr_rider"  {{in_array('rider',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rider">{{translate('messages.rider')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rider_level" class="form-check-input" id="cr_rider_level"  {{in_array('rider_level',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rider_level">{{translate('messages.rider_level')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rider_review" class="form-check-input" id="cr_rider_review"  {{in_array('rider_review',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rider_review">{{translate('messages.reviews')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Vehicle_Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_ride_vehicle_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_ride_vehicle_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="ride_vehicle" class="form-check-input" id="cr_ride_vehicle"  {{in_array('ride_vehicle',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_ride_vehicle">{{translate('messages.Vehicle_Management')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Employee Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_employee_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_employee_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="employee" class="form-check-input" id="cr_employee"  {{in_array('employee',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_employee">{{translate('messages.Employee')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if (addon_published_status('Service'))
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Service Man Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_serviceman_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_serviceman_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="service_provider" class="form-check-input" id="cr_service_provider"  {{in_array('service_provider',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_service_provider">{{translate('messages.Service Man')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                </div>
                            </div>
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Finance') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Withdraw Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_finance_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_finance_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="withdraw_list" class="form-check-input" id="cr_withdraw_list"  {{in_array('withdraw_list',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_withdraw_list">{{translate('Withdraw Management')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Auto Disbursements')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_auto_disb_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_auto_disb_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="disbursement" class="form-check-input" id="cr_disbursement"  {{in_array('disbursement',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_disbursement">{{translate('Auto Disbursements')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Cash Operations')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_cash_ops_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_cash_ops_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="collect_cash" class="form-check-input" id="cr_collect_cash"  {{in_array('collect_cash',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_collect_cash">{{translate('messages.collect_Cash')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="provide_dm_earning" class="form-check-input" id="cr_provide_dm_earning"  {{in_array('provide_dm_earning',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_provide_dm_earning">{{translate('Pay Earnings')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="withdraw_method" class="form-check-input" id="cr_withdraw_method"  {{in_array('withdraw_method',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_withdraw_method">{{translate('messages.withdraw_method')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Tax & Compliance')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_finance_tax_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_finance_tax_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="admin_text_module" class="form-check-input" id="cr_admin_text_module"  {{in_array('admin_text_module',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_admin_text_module">{{translate('Admin Tax Report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="vendor_vat_report" class="form-check-input" id="cr_vendor_vat_report"  {{in_array('vendor_vat_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_vendor_vat_report">{{translate('Vendor Tax Report')}}</label>
                                                </div>
                                            </div>
                                            @if (addon_published_status('Rental'))
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('Dispatch') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Dispatch Dashboard')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_dispatch_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_dispatch_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="dispatch" class="form-check-input" id="cr_dispatch"  {{in_array('dispatch',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_dispatch">{{translate('Dispatch Dashboard')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Report & Analytics') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Transaction Report')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_report_transaction_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_report_transaction_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="report" class="form-check-input" id="cr_report"  {{in_array('report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_report">{{translate('Transaction Report')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Earning Reports')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_report_earning_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_report_earning_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="earning_report" class="form-check-input" id="cr_earning_report"  {{in_array('earning_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_earning_report">{{translate('Earning Reports')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Payout & Disbursement')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_report_payout_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_report_payout_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="disbursement_report" class="form-check-input" id="cr_disbursement_report"  {{in_array('disbursement_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_disbursement_report">{{translate('messages.disbursement_report')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="expense_report" class="form-check-input" id="cr_expense_report"  {{in_array('expense_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_expense_report">{{translate('messages.expense_report')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Sales Reports')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_report_sales_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_report_sales_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="sales_report" class="form-check-input" id="cr_sales_report"  {{in_array('sales_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_sales_report">{{translate('Sales Reports')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Performance Reports')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_report_performance_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_report_performance_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="performance_report" class="form-check-input" id="cr_performance_report"  {{in_array('performance_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_performance_report">{{translate('Performance Reports')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Settings') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Business Setup')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_business_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_business_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="settings" class="form-check-input" id="cr_settings"  {{in_array('settings',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_settings">{{translate('Business Setup')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Module Setup')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_business_modules_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_business_modules_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="module" class="form-check-input" id="cr_module"  {{in_array('module',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_module">{{translate('messages.Module Setup')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Subscription Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_subscription_mgmt_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_subscription_mgmt_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="subscription" class="form-check-input" id="cr_subscription"  {{in_array('subscription',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_subscription">{{translate('Vendor Subscription')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="pro_customer_subscription" class="form-check-input" id="cr_pro_customer_subscription"  {{in_array('pro_customer_subscription',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_pro_customer_subscription">{{translate('messages.Pro_Customer_Management')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if (addon_published_status('TaxModule'))
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Finance & Tax')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_settings_tax_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_settings_tax_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="system_tax" class="form-check-input" id="cr_system_tax"  {{in_array('system_tax',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_system_tax">{{translate('messages.system_tax')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Website, Pages & Content')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_pages_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_pages_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="social_media" class="form-check-input" id="cr_social_media"  {{in_array('social_media',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_social_media">{{translate('Social Media')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="landing_pages" class="form-check-input" id="cr_landing_pages"  {{in_array('landing_pages',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_landing_pages">{{translate('Landing Pages')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="business_pages" class="form-check-input" id="cr_business_pages"  {{in_array('business_pages',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_business_pages">{{translate('Business Pages')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="seo" class="form-check-input" id="cr_seo"  {{in_array('seo',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_seo">{{translate('SEO & Metadata')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('System Configuration')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_system_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_system_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="system_config" class="form-check-input" id="cr_system_config"  {{in_array('system_config',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_system_config">{{translate('System Configuration')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Authentication & Access')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_auth_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_auth_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="login_setup" class="form-check-input" id="cr_login_setup"  {{in_array('login_setup',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_login_setup">{{translate('messages.login_setup')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Communication Setup')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_comm_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_comm_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="email_setups" class="form-check-input" id="cr_email_setups"  {{in_array('email_setups',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_email_setups">{{translate('messages.Email Setup')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="notification_setup" class="form-check-input" id="cr_notification_setup"  {{in_array('notification_setup',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_notification_setup">{{translate('messages.notification_setup')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Integrations & Third-Party')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_int_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_int_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="third_party-ms" class="form-check-input" id="cr_third_party-ms"  {{in_array('third_party-ms',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_third_party-ms">{{translate('messages.3rd Party & Configuration')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if (addon_published_status('Service'))
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Service Module Settings')}}</h5>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="service_settings" class="form-check-input" id="cr_service_settings"  {{in_array('service_settings',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_service_settings">{{translate('Service Module Settings')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @if (addon_published_status('RideShare'))
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Ride Share Settings')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_ride_settings_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_ride_settings_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="ride_settings" class="form-check-input" id="cr_ride_settings"  {{in_array('ride_settings',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_ride_settings">{{translate('Ride Share Settings')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Media & File Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_media_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_media_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="gallery" class="form-check-input" id="cr_gallery"  {{in_array('gallery',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_gallery">{{translate('messages.gallery')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Maintenance & Database')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_maint_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_maint_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="clean_database" class="form-check-input" id="cr_clean_database"  {{in_array('clean_database',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_clean_database">{{translate('messages.Clean Database')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Modules Wise Management') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Sales')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_order_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_order_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="order" class="form-check-input" id="cr_order"  {{in_array('order',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_order">{{translate('messages.Orders')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="pos" class="form-check-input" id="cr_pos"  {{in_array('pos',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_pos">{{translate('messages.POS Orders')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="parcel" class="form-check-input" id="cr_parcel"  {{in_array('parcel',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_parcel">{{translate('messages.parcel')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Marketing')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_promotion_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_promotion_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="campaign" class="form-check-input" id="cr_campaign"  {{in_array('campaign',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_campaign">{{translate('messages.campaign')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="banner" class="form-check-input" id="cr_banner"  {{in_array('banner',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_banner">{{translate('messages.banner')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="coupon" class="form-check-input" id="cr_coupon"  {{in_array('coupon',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_coupon">{{translate('Promotions')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="notification" class="form-check-input" id="cr_notification"  {{in_array('notification',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_notification">{{translate('messages.Push Notification')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="reels" class="form-check-input" id="cr_reels"  {{in_array('reels',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_reels">{{translate('messages.reels')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Catalog')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_product_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_product_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="category" class="form-check-input" id="cr_category"  {{in_array('category',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_category">{{translate('Setup')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="addon" class="form-check-input" id="cr_addon"  {{in_array('addon',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_addon">{{translate('messages.Addons')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="item" class="form-check-input" id="cr_item"  {{in_array('item',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_item">{{translate('messages.item')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.stores')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_store_all">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_store_all">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="store" class="form-check-input" id="cr_store"  {{in_array('store',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_store">{{translate('messages.store')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="store_bulk" class="form-check-input" id="cr_store_bulk"  {{in_array('store_bulk',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_store_bulk">{{translate('Bulk')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        @if (addon_published_status('Rental'))
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Rental Management') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Sales')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rn_sales">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rn_sales">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="trip" class="form-check-input" id="cr_trip"  {{in_array('trip',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_trip">{{translate('messages.trip')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Catalog')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rn_cat">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rn_cat">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input" id="cr_vehicle"  {{in_array('vehicle',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_vehicle">{{translate('messages.vehicle')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rental_vehicle_setup" class="form-check-input" id="cr_rental_vehicle_setup"  {{in_array('rental_vehicle_setup',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rental_vehicle_setup">{{translate('Setup')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.provider_management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rn_prov">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rn_prov">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="provider" class="form-check-input" id="cr_provider"  {{in_array('provider',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_provider">{{translate('messages.provider')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="driver" class="form-check-input" id="cr_driver"  {{in_array('driver',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_driver">{{translate('messages.driver')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rental_provider_bulk" class="form-check-input" id="cr_rental_provider_bulk"  {{in_array('rental_provider_bulk',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rental_provider_bulk">{{translate('Bulk')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Marketing')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rn_mkt">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rn_mkt">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="promotion" class="form-check-input" id="cr_promotion"  {{in_array('promotion',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_promotion">{{translate('messages.promotion')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rental_banners" class="form-check-input" id="cr_rental_banners"  {{in_array('rental_banners',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rental_banners">{{translate('Banners')}}</label>
                                                </div>
                                            </div>
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="rental_communication" class="form-check-input" id="cr_rental_communication"  {{in_array('rental_communication',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_rental_communication">{{translate('Communication')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Apps')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rn_app">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rn_app">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="download_app" class="form-check-input" id="cr_download_app"  {{in_array('download_app',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_download_app">{{translate('messages.download_app')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        @endif
                        @if (addon_published_status('RideShare'))
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Ride Share Management') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Dashboard')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rs_dash">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rs_dash">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="heat_map" class="form-check-input" id="cr_heat_map"  {{in_array('heat_map',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_heat_map">{{translate('Dashboard')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Ride_Management')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rs_ride">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rs_ride">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="ride" class="form-check-input" id="cr_ride"  {{in_array('ride',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_ride">{{translate('messages.ride')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Catalog')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rs_fare">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rs_fare">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="fare" class="form-check-input" id="cr_fare"  {{in_array('fare',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_fare">{{translate('messages.fare')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Marketing')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_rs_mkt">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_rs_mkt">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="ride_promotion" class="form-check-input" id="cr_ride_promotion"  {{in_array('ride_promotion',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_ride_promotion">{{translate('messages.ride_promotion')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        @endif
                        @if (addon_published_status('Service'))
                            <div class="shadow-cutom-box-xxl mb-20">
                                <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Service Management') }}</h4>
                                <div class="row g-3">
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Bookings')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_sv_book">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_sv_book">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="service_booking" class="form-check-input" id="cr_service_booking"  {{in_array('service_booking',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_service_booking">{{translate('messages.service_booking')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-20">
                                    <div class="bg-light2 rounded select-subwrapper h-100">
                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('Catalog')}}</h5>
                                            <div class="check-item-custom pb-0 w-auto">
                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                    <input type="checkbox" class="form-check-input mt-0 check-all" id="g_sv_cat">
                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="g_sv_cat">{{ translate('Select All') }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                            <div class="check-item m-0 p-0">
                                                <div class="form-group m-0 p-0 form-check form--check">
                                                    <input type="checkbox" name="modules[]" value="service_management" class="form-check-input" id="cr_service_management"  {{in_array('service_management',(array)json_decode($role['modules']))?'checked':''}}>
                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cr_service_management">{{translate('messages.service_management')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        @endif
                        </div>

                        <div class="btn--container justify-content-end mt-4">
                            <button type="reset" class="btn btn--reset min-w-120px">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary min-w-120px">{{translate('messages.update')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/custom-role-index.js"></script>
@endpush
