@extends('layouts.admin.app')

@section('title', translate('messages.subscription'))

@section('content')


<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>
                    {{ translate('Pro Customer Benefits Setup') }}
                </span>
            </h1>
        </div>
    </div>
    <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 align-items-center d-flex mb-3">
        <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
        <span>
            {{translate('Only one benefit can be enabled for Pro customers at a time.')}}
        </span>
    </div>

    <div class="card mb-20 card-container">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div class="">
                    <div class="">
                        <h3 class="mb-1 fs-16">{{ translate('Discount') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('If enable this option pro customers will receive discount on every order') }} 
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-2">
                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                        {{ translate('messages.view') }}
                        <i class="tio-chevron-down fs-22"></i>
                    </div>
                    <label class="toggle-switch toggle-switch-sm ">
                        <input type="checkbox" id="" class="status toggle-switch-input" checked>
                        <span class="toggle-switch-label text">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="card-details-body pt-3">
                <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
                    <div class="d-flex flex-md-nowrap gap-2 flex-wrap align-items-center justify-content-between">
                        <div class="max-w-595">
                            <h3 class="mb-1 fs-16">{{ translate('Discount setup') }}</h3>
                            <p class="mb-0 gray-dark fs-12">
                                {{ translate('configure discount logic for pro customer') }}
                            </p>
                        </div>
                        <div class="resturant-type-group module_select-area max-w-542 w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                            <label class="form-check form--check w-100">
                                <input class="form-check-input" type="radio" value="central_setup" name="discount_method" checked>
                                <span class="form-check-label">
                                    {{ translate('messages.Central Setup') }}
                                </span>
                            </label>
                            <label class="form-check form--check w-100">
                                <input class="form-check-input" type="radio" value="individual_setup" name="discount_method">
                                <span class="form-check-label">
                                    {{ translate('messages.Individual Setup') }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="bg-light2 p-xl-20 p-3 rounded all_module_here d-none">
                    <div class="row g-3 align-items-center">
                        <div class="col-xl-3">
                            <h3 class="mb-0 fs-16">{{ translate('Discount for All Modules') }}</h3>
                        </div>
                        <div class="col-xl-9">
                            <div class="p-xxl-20 p-3 rounded bg-white">
                                <div class="row g-3 align-items-end">
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label fw-400" for="default_title">
                                                {{ translate('messages.Discount (%)') }} 
                                                <span class="text-danger">*</span>
                                                <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                    <i class="tio-info text-light-gray fs-14"></i>
                                                </span>
                                            </label>
                                            <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                        </div>        
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label fw-400" for="default_title">
                                                {{ translate('messages.Up to Discount Amount ($)') }} 
                                                <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                    <i class="tio-info text-light-gray fs-14"></i>
                                                </span>
                                            </label>
                                            <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                        </div>        
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                <span>
                                                    {{ translate('messages.Minimum amount ($)') }} 
                                                    <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                        <i class="tio-info text-light-gray fs-14"></i>
                                                    </span>
                                                </span>
                                                 <label class="toggle-switch toggle-switch-sm ">
                                                    <input type="checkbox" id="" class="status toggle-switch-input">
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </label>
                                            <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                        </div>        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="indevidual_module_here d-none">
                    <div class="d-flex gap-3 flex-column">
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('Grocery Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('food Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('shop Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('pharmacy Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('ride share Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('rental Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 p-xl-20 p-3 rounded">
                            <div class="row g-3 align-items-center">
                                <div class="col-xl-3">
                                    <h3 class="mb-0 fs-16">{{ translate('Grocery Module') }}</h3>
                                </div>
                                <div class="col-xl-9">
                                    <div class="p-xxl-20 p-3 rounded bg-white">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Discount (%)') }} 
                                                        <span class="text-danger">*</span>
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label fw-400" for="default_title">
                                                        {{ translate('messages.Up to Discount Amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="form-group mb-0">
                                                    <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                        <span>
                                                            {{ translate('messages.Minimum amount ($)') }} 
                                                            <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                                <i class="tio-info text-light-gray fs-14"></i>
                                                            </span>
                                                        </span>
                                                         <label class="toggle-switch toggle-switch-sm ">
                                                            <input type="checkbox" id="" class="status toggle-switch-input">
                                                            <span class="toggle-switch-label text">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </label>
                                                    <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                                </div>        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card py-3 px-xxl-4 px-3 mb-20">
        <div class="d-flex align-items-center justify-content-between">
            <div class="">
                <div class="">
                    <h3 class="mb-1 fs-16">{{ translate('coupon') }}</h3>
                    <p class="mb-0 gray-dark fs-12">
                        {{ translate('Create special coupons from') }} <a href="#0" class="text-underline text-info font-weight-medium">{{ translate('coupons') }}</a> {{ translate('and choose ‘Pro Customer’ as the coupon type.') }}
                    </p>
                </div>
            </div>
            <div class="">
                <label class="toggle-switch toggle-switch-sm ">
                    <input type="checkbox" id="" class="status toggle-switch-input" checked>
                    <span class="toggle-switch-label text">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </div>
        </div>
    </div>
    <div class="card card-container">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div class="">
                    <div class="">
                        <h3 class="mb-1 fs-16">{{ translate('Delivery Fee') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('If enable this option pro customers will receive free delivery') }} 
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-2">
                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                        {{ translate('messages.view') }}
                        <i class="tio-chevron-down fs-22"></i>
                    </div>
                    <label class="toggle-switch toggle-switch-sm ">
                        <input type="checkbox" id="" class="status toggle-switch-input" checked>
                        <span class="toggle-switch-label text">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="card-details-body pt-3">
                <div class="d-flex gap-3 flex-column">
                    <div class="bg-light2 p-xl-20 p-3 rounded">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-3">
                                <h3 class="mb-0 fs-16">{{ translate('Grocery Module') }}</h3>
                            </div>
                            <div class="col-xl-9">
                                <div class="p-xxl-20 p-3 rounded bg-white">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label fw-400" for="default_title">
                                                    {{ translate('messages.Delivery Type') }} 
                                                </label>
                                                <div class="resturant-type-group module_select-area max-w-542 w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="full_free" name="free_method" checked>
                                                        <span class="form-check-label">
                                                            {{ translate('messages.Full Free') }}
                                                        </span>
                                                    </label>
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="partial_free" name="free_method">
                                                        <span class="form-check-label">
                                                            {{ translate('messages.Partial Free') }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>                                               
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label fw-400" for="default_title">
                                                    {{ translate('messages.Discount (%)') }} 
                                                    <span class="text-danger">*</span>
                                                    <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                        <i class="tio-info text-light-gray fs-14"></i>
                                                    </span>
                                                </label>
                                                <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                            </div>        
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                    <span>
                                                        {{ translate('messages.Minimum amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </span>
                                                    <label class="toggle-switch toggle-switch-sm ">
                                                        <input type="checkbox" id="" class="status toggle-switch-input">
                                                        <span class="toggle-switch-label text">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </label>
                                                <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                            </div>        
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-light2 p-xl-20 p-3 rounded">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-3">
                                <h3 class="mb-0 fs-16">{{ translate('Food Module') }}</h3>
                            </div>
                            <div class="col-xl-9">
                                <div class="p-xxl-20 p-3 rounded bg-white">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label fw-400" for="default_title">
                                                    {{ translate('messages.Delivery Type') }} 
                                                </label>
                                                <div class="resturant-type-group module_select-area max-w-542 w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="full_free" name="free_method" checked>
                                                        <span class="form-check-label">
                                                            {{ translate('messages.Full Free') }}
                                                        </span>
                                                    </label>
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="partial_free" name="free_method">
                                                        <span class="form-check-label">
                                                            {{ translate('messages.Partial Free') }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>                                               
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label d-flex justify-content-between align-items-center gap-2 fw-400" for="default_title">
                                                    <span>
                                                        {{ translate('messages.Minimum amount ($)') }} 
                                                        <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('content..l') }}">
                                                            <i class="tio-info text-light-gray fs-14"></i>
                                                        </span>
                                                    </span>
                                                    <label class="toggle-switch toggle-switch-sm ">
                                                        <input type="checkbox" id="" class="status toggle-switch-input">
                                                        <span class="toggle-switch-label text">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </label>
                                                <input type="text" name="title[]" class="form-control" placeholder="{{ translate('messages.Ex: data') }}" value="">
                                            </div>        
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('admin-views.partials._floating-submit-button')
</div>




@endsection

@push('script_2')
<script>
   $(document).on('change', '.module_select-area input[name="discount_method"]', function () {
        $('.all_module_here, .indevidual_module_here').addClass('d-none');
        if ($(this).val() === 'central_setup') {
            $('.all_module_here').removeClass('d-none');
        } else {
            $('.indevidual_module_here').removeClass('d-none');
        }
    }).find(':checked').trigger('change');
</script>
@endpush