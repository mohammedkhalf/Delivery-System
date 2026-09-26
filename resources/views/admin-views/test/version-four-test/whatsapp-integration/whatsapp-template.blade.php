@extends('layouts.admin.app')

@section('title', translate('messages.3rd Party Integration'))


@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-0 pb-2 position-relative z-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <h1 class="page-header-title text-capitalize">
                <span>
                    {{ translate('WhatsApp Template') }}
                </span>
            </h1>
            <div>
                <div class="dropdown">
                    <button class="dropdown-toggle d-flex align-items-center form-control pe-4" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{translate('admin template')}}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#0">{{translate('vendor Template')}}</a>
                        <a class="dropdown-item" href="#0">{{translate('customer Template')}}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <div class="js-nav-scroller tabs-slide-wrap tabs-slide-language hs-nav-scroller-horizontal mb-0">
            <ul class="nav tabs-inner nav-tabs border-0 nav--tabs nav--pills pt-2 nav--theme-version">
                <li class="nav-item">
                    <a class="nav-link active" href="">{{ translate('Order Placed') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order Confirmation') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order Preparing') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order out for delivery') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order delivered') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order cancelled') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order update') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " href="">{{ translate('Order refund') }}</a>
                </li>
            </ul>
            <div class="arrow-area">
                <div class="button-prev align-items-center">
                    <button type="button" class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                        <i class="tio-chevron-left fs-24"></i>
                    </button>
                </div>
                <div class="button-next align-items-center">
                    <button type="button" class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                        <i class="tio-chevron-right fs-24"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card p-20 mb-3">
        <div class="d-flex flex-sm-nowrap flex-wrap gap-2 align-items-center justify-content-between">
            <div class="">
                <div class="">
                    <h3 class="mb-1 fs-16">{{ translate('Send WhatsApp Notification on Order Confirmation?') }}</h3>
                    <p class="mb-0 gray-dark fs-12">
                        {{ translate('Enable the option to receive an WhatsApp notification for this event.') }} 
                    </p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <label class="toggle-switch toggle-switch-sm">
                    <input type="checkbox" id="" class="status toggle-switch-input" checked>
                    <span class="toggle-switch-label text">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </div>
        </div>
    </div>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card card-sm shadow-1 h-100">
                    <div class="card-header">
                        <h2 class="mb-0 fs-18 fw-bold">
                            {{ translate('Editor') }}
                        </h2>
                        <p class="fs-12 mb-0"></p>
                    </div>
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        <div class="bg-light2 rounded">
                            <div class="pt-2">
                                <div class="js-nav-scroller hs-nav-scroller-horizontal">
                                    <ul class="nav nav-tabs m-0 px-2">
                                        <li class="nav-item">
                                            <a class="nav-link lang_link px-2 active" href="#"id="default-link">{{ translate('Default') }}</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link lang_link px-2"href="#" id="">English(EN)</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link lang_link px-2"href="#" id="">Bengali - বাংলা(BN)</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link lang_link px-2"href="#" id="">Arabic - العربية(AR)</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-20px p-3">
                                <h5 class="mb-0 fs-16 fw-bold">
                                    {{ translate('message content') }}
                                </h5>                                        
                                <div class="header-select__content-image">
                                     <label class="input-label mb-2 fw-400" for="default_title">
                                        {{ translate('messages.Header Type') }}
                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.test') }}">
                                            <i class="tio-info text-light-gray"></i>
                                        </span>
                                    </label>
                                    <select name="" id="" class="custom-select bg-white edit__elect-imge-content">
                                        <option value="text">Text</option>
                                        <option value="bannerImage">Image Banner</option>
                                        <option value="nodata">none</option>
                                    </select>
                                </div>                                        
                                <div class="tab-showing_text d-none">
                                    <div class="mb-0">
                                        <label for="" class="input-label mb-2 fw-400">
                                            Header Text
                                        </label>
                                        <div class="character-count">
                                            <textarea name="" class="form-control character-count-field view-mail-title_main" rows="1" maxlength="100" id="" placeholder="Your ride has been confirmed."></textarea>
                                            <div class="d-flex justify-content-end">
                                                <span class="text-body-light text-right d-block mt-1">0/100</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-showing_img d-none">
                                    <div class="p-lg-4 p-3 rounded bg-white d-flex flex-column justify-content-around gap-3 P-3">
                                        <div class="text-start">
                                            <h5 class="mb-1 fs-14">
                                                {{ translate('Image Banner') }} 
                                            </h5>
                                            <p class="fs-12 mb-0">{{ translate('Upload Image Banner') }}</p>
                                        </div>                                        
                                        @include('admin-views.partials._image-uploader', [
                                            'id' => 'image-input',
                                            'name' => 'logo',
                                            'ratio' => '2:1',
                                            'isRequired' => true,
                                            'existingImage' => \App\CentralLogics\Helpers::get_full_url('business', $logo?->value ?? '', $logo?->storage[0]?->value ?? 'public', 'upload_image'),
                                            'imageExtension' => IMAGE_EXTENSION,
                                            'imageFormat' => IMAGE_FORMAT,
                                            'maxSize' => MAX_FILE_SIZE,
                                            'textPosition' => 'bottom',
                                            ])
                                    </div>
                                </div>
                                <div class="whatsapp-template-editor">
                                    <div class="mb-0 main-editor-body-wrap">
                                        <div class="d-flex align-items-center gap-2 justify-content-between mb-2">                                                
                                            <label for="" class="input-label mb-0 fw-400">
                                                Mail Body Message
                                            </label>
                                            <div class="dropdown">
                                                <button class="btn shadow-none w-100 outline-0 text-dark text-right border-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                    <div class="bg-soft-secondary rounded d-inline-block py-1 px-2 fs-12 fw-semibold">
                                                        Insert Variable
                                                    </div>
                                                </button>
                                                <ul class="dropdown-menu w-100 pb-1 px-2 pt-0">
                                                    <li class="mb-2 bg-white top-sticky-drop pt-2">
                                                        <form action="" class="">
                                                            <div class="d-flex align-items-center border rounded bg-white border search-form__input_group">
                                                                <span class="search-form__icon px-2">
                                                                    <i class="tio-search"></i>
                                                                </span>
                                                                <input type="search" name="search" value="" class="form-control outline-0 h--40px fs-12 border-0 bg-transparent" placeholder="Search here by Trip ID">
                                                            </div>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('Customer Name') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('By this variable you will add customer name') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {customer_name}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer active py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('Trip ID') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('Connect trip id by using this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {trip_id}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('driver Name') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('To add driver name automatically use this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {driver_name}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('driver Phone') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('To add driver phone automatically use this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {driver_phone}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('vehicle Type') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('To add vehicle type automatically use this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {vehicle_type}
                                                            </div>
                                                        </div>
                                                    </li>
                                                        <li>
                                                        <div class="dropdown-item py-12px cursor-pointer px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('Trip ID') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('Connect trip id by using this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {trip_id}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('driver Name') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('To add driver name automatically use this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {driver_name}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('driver Phone') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('To add driver phone automatically use this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {driver_phone}
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <div class="dropdown-item cursor-pointer py-12px px-2 d-flex align-items-start justify-content-between gap-2">
                                                            <div>
                                                                <h5 class="mb-1 lh-1 fs-13 fw-semibold title-color text-break text-wrap ">
                                                                    {{ translate('vehicle Type') }}
                                                                </h5>
                                                                <span class="mb-0 lh-1 fs-10 fw-normal title-color line--limit-2 lh-base text-break text-wrap ">
                                                                    {{ translate('To add vehicle type automatically use this variable') }}
                                                                </span>   
                                                            </div>
                                                            <div class="drop-data bg-primary-light rounded py-1 px-2 fs-10">
                                                                {vehicle_type}
                                                            </div>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <textarea name="" id="editor-mail_type" class=" form-control editor-mail_type" placeholder="Describe about this page">Describe about this page</textarea>
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label for="" class="input-label mb-2 fw-400 d-flex align-items-center gap-1">
                                        Footer Text
                                    </label>
                                    <div class="character-count">
                                        <textarea name="" class="form-control character-count-field footer-mail-title_main" rows="1" maxlength="100" id="" placeholder="Thank you for choosing us!"></textarea>
                                        <div class="d-flex justify-content-end">
                                            <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 rounded p-3">
                            <div class="callto-action-switcher d-flex align-items-center gap-2 justify-content-between mb-20">
                                <div class="mb-0">
                                    <h5 class="mb-0 fs-16">
                                        {{ translate('Call to Action Button') }}
                                    </h5>
                                </div>
                                <div class="position-relative">
                                    <label class="toggle-switch toggle-switch-sm">
                                        <input type="checkbox" class="switcher_input status toggle-switch-input" id="" checked>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <div class="mb-2">
                                    <label for="" class="input-label mb-2 fw-400 d-flex align-items-center gap-1">
                                        Button Name
                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top" title="Content need">
                                            <i class="bi bi-info-circle-fill fs-12 text-muted"></i>
                                        </span>
                                    </label>
                                    <div class="character-count">
                                        <textarea name="" class="form-control character-count-field footer__text-name" rows="1" maxlength="100" id="" placeholder="Track your ride"></textarea>
                                         <div class="d-flex justify-content-end">
                                            <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <label for="" class="input-label mb-2 fw-400 d-flex align-items-center gap-1">
                                        Button URL
                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top" title="Content need">
                                            <i class="bi bi-info-circle-fill fs-12 text-muted"></i>
                                        </span>
                                    </label>
                                    <div class="character-count">
                                        <textarea name="" class="form-control character-count-field footer__text-url" rows="1" maxlength="100" id="" placeholder="https://trck.app/order/{{1}}"></textarea>
                                        <div class="d-flex justify-content-end">
                                            <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card card-sm shadow-1 h-100">
                    <div class="card-header">
                        <h2 class="mb-0 fs-18 fw-bold">
                            {{ translate('preview') }}
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="editor-whatapp-box border rounded bg-white">
                            <div class="d-flex align-items-center justify-content-between gap-2 p-12">
                                <div class="d-flex align-items-center gap-10px">
                                    <img src="{{  asset('public/assets/admin/img/6m-logo.png') }}" alt="" class="w-40px h-40px rounded-circle object-cover">
                                    <div class="cont">
                                        <h6 class="mb-0 lh-1 fs-14">{{ translate('6amMart') }}</h6>
                                        <span class="fs-12">{{ translate('Business Account') }}</span>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                        <i class="tio-more-vertical fs-16 text-9EADC1"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="bg-editor p-xxl-20 p-3">
                                <div class="card border-0 rounded-10 overflow-hidden bg-white">
                                    <div class="bg--F6F6F6 border-bottom py-3 px-3 image__banner">
                                        <h3 class="mb-0 fs-16 lh-1 view-mail-title text-capitalize">
                                            Your ride has been confirmed.
                                        </h3>
                                    </div>
                                    <div class="view-mail-body text-dark p-12 px-3">
                                        Describe about this page
                                    </div>
                                    <p class="view-copyright-text fs-12 px-3 pb-3">
                                        Thank you for your order
                                    </p>
                                    <div class="">
                                        <a href="#0" class="view-btn_edit p-12 border-top fs-14 d-none align-items-center gap-1 justify-content-center text-info fw-semibold">
                                            <i class="tio-open-in-new m-0"></i>                                                  
                                            <span class="view-btn-text fw-medium">Track order</span>
                                        </a>
                                    </div>
                                    <div class="text-right fs-12 py-10px px-3 text-9EADC1 fw-medium">12:34 PM</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-info bg-opacity-10 d-flex align-items-center gap-2 p-12 rounded mt-20">
                            <p class="text-info fs-12 m-0">
                                <strong>Note:</strong> This is a preview with example values. Variables will be replaced with actual data when sent.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                @include('admin-views.partials._floating-submit-button')
            </div>
        </div>
    </form>
   
    
    
    

</div>
@endsection

@push('script_2')
<script src="{{asset('public/assets/admin/ckeditor/ckeditor.js')}}"></script>
<script src="{{asset('public/assets/admin/js/whatsapp-template/whatsapp-template.js')}}"></script>
@endpush
