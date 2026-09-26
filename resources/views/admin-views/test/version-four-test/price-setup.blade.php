@extends('layouts.admin.app')

@section('title', translate('messages.subscription'))

@section('content')


<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>
                    {{ translate('Price Setup') }}
                </span>
            </h1>
        </div>
    </div>

   <div class="card mb-20">
        <div class="card-body">
            <div class="d-flex gap-2 align-items-center justify-content-between mb-20">
                <div class="">
                    <div class="">
                        <h3 class="mb-1 fs-16">{{ translate('Subscription Price') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('Manage subscription packages here') }} 
                        </p>
                    </div>
                </div>
                <div class="">
                    <button type="button" class="btn btn--primary text-nowrap px-3 offcanvas-trigger"
                       data-target="#offcanvas__createplan">
                        <i class="tio-add-circle"></i> {{ translate('add plan') }} 
                    </button>
                </div>
            </div>
            <div class="">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="bg-light2 subscription-plan__card p-xl-20 p-20 rounded">
                            <div class="d-flex gap-2 align-items-center justify-content-between">
                                <div class="w-40px">
                                    <img width="40" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                </div>
                                <div class="bg-white rounded p-2 d-flex align-items-center gap-4">
                                    <button type="reset" class="btn outline-none border-0 p-0 pe--12 text-danger">
                                        <img src="{{asset('public/assets/admin/img/trash-stroke.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                    <button type="reset" class="btn outline-none border-0 p-0 text-primary offcanvas-trigger"
                                        data-target="#offcanvas__editplan">
                                        <img src="{{asset('public/assets/admin/img/bx-edit.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                </div>
                            </div>
                            <div class="pt-4 mt-1">
                                <h3 class="mb-2 fs-24 fw-medium lh-1">
                                    {{ translate('Monthly') }}
                                </h3>
                                <p class="mb-0 fs-32 font-semibold text-dark">
                                    $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                </p>
                            </div>
                        </div>
                    </div>                    
                    <div class="col-md-6 col-lg-4">
                        <div class="bg-light2 subscription-plan__card p-xl-20 p-20 rounded">
                            <div class="d-flex gap-2 align-items-center justify-content-between">
                                <div class="w-40px">
                                    <img width="40" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                </div>
                                <div class="bg-white rounded p-2 d-flex align-items-center gap-4">
                                    <button type="reset" class="btn outline-none border-0 p-0 pe--12 text-danger">
                                        <img src="{{asset('public/assets/admin/img/trash-stroke.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                    <button type="reset" class="btn outline-none border-0 p-0 text-primary offcanvas-trigger"
                                        data-target="#offcanvas__editplan">
                                        <img src="{{asset('public/assets/admin/img/bx-edit.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                </div>
                            </div>
                            <div class="pt-4 mt-1">
                                <h3 class="mb-2 fs-24 fw-medium lh-1">
                                    {{ translate('Quarterly') }}
                                </h3>
                                <p class="mb-0 fs-32 font-semibold text-dark">
                                    $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="bg-light2 subscription-plan__card p-xl-20 p-20 rounded">
                            <div class="d-flex gap-2 align-items-center justify-content-between">
                                <div class="w-40px">
                                    <img width="40" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                </div>
                                <div class="bg-white rounded p-2 d-flex align-items-center gap-4">
                                    <button type="reset" class="btn outline-none border-0 p-0 pe--12 text-danger">
                                        <img src="{{asset('public/assets/admin/img/trash-stroke.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                    <button type="reset" class="btn outline-none border-0 p-0 text-primary offcanvas-trigger"
                                        data-target="#offcanvas__editplan">
                                        <img src="{{asset('public/assets/admin/img/bx-edit.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                </div>
                            </div>
                            <div class="pt-4 mt-1">
                                <h3 class="mb-2 fs-24 fw-medium lh-1">
                                    {{ translate('Yearly') }}
                                </h3>
                                <p class="mb-0 fs-32 font-semibold text-dark">
                                    $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="bg-light2 subscription-plan__card p-xl-20 p-20 rounded">
                            <div class="d-flex gap-2 align-items-center justify-content-between">
                                <div class="w-40px">
                                    <img width="40" src="{{asset('public/assets/admin/img/for-free.png')}}" alt="img" class="rounded-circle">
                                </div>
                                <div class="bg-white rounded p-2 d-flex align-items-center gap-4">
                                    <button type="reset" class="btn outline-none border-0 p-0 pe--12 text-danger">
                                        <img src="{{asset('public/assets/admin/img/trash-stroke.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                    <button type="reset" class="btn outline-none border-0 p-0 text-primary offcanvas-trigger"
                                        data-target="#offcanvas__editplan">
                                        <img src="{{asset('public/assets/admin/img/bx-edit.svg')}}" alt="img" class="rounded-circle svg">
                                    </button>
                                </div>
                            </div>
                            <div class="pt-4 mt-1">
                                <h3 class="mb-2 fs-24 fw-medium lh-1">
                                    {{ translate('Free Trial') }}
                                </h3>
                                <p class="mb-0 fs-32 font-semibold text-dark">
                                    $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card py-4">
        <div class="card-body py-5 my-5">
            <div class="text-center py-5">
                <div class="mb-20">
                    <div class="w-40px mx-auto mb-20">
                        <img width="40" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                    </div>
                    <div class="">
                        <h3 class="mb-1 fs-16">{{ translate('Add Subscription Plan') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('Discount will be applied to all food under this restaurant') }} 
                        </p>
                    </div>
                </div>
                <div class="">
                    <button type="button" class="btn btn--primary text-nowrap px-3 offcanvas-trigger"
                       data-target="#offcanvas__createplan">
                        <i class="tio-add-circle"></i> {{ translate('add plan') }} 
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>


<!-- Edit plan -->
<div id="offcanvas__editplan" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div class="h-100">
        <form action="#" method="post" class="d-flex flex-column h-100" enctype="multipart/form-data">
            <div>
                <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                    <h3 class="mb-0 fs-18 line--limit-1">{{ translate('Edit Subscription Plan') }}</h3>
                    <button type="button"
                            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                            aria-label="Close">&times;
                    </button>
                </div>        
                <div class="custom-offcanvas-body p-20">
                    <div class="bg--secondary rounded pt-2 px-3 mb-20 pb-3">        
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.Create Subscription Plan') }}
                                    </label>
                                    <div class="resturant-type-group module_select-area max-w-542 w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                                        <label class="form-check form--check w-100">
                                            <input class="form-check-input" type="radio" value="central_setup" name="subscription_status" checked>
                                            <span class="form-check-label">
                                                {{ translate('messages.Paid') }}
                                            </span>
                                        </label>
                                        <label class="form-check form--check w-100">
                                            <input class="form-check-input" type="radio" value="individual_setup" name="subscription_status">
                                            <span class="form-check-label">
                                                {{ translate('messages.Free Trial') }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.plan name') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ translate('messages.ex: monthly') }}" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.Plan Price ($)') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ translate('messages.ex: 500') }}" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.Duration (Days)') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ translate('messages.ex: 100') }}" required>
                                </div>
                            </div>                            
                        </div>
                    </div>
                </div>
            </div>
        
            <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
                <button type="button" class="btn w-100 btn--reset">{{ translate('Cancel') }}</button>
                <button type="submit" class="btn w-100 btn--primary">{{ translate('Add') }}</button>
            </div>
        </form>
    </div>
</div>
<!-- Create plan -->
<div id="offcanvas__createplan" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div class="h-100">
        <form action="#" method="post" class="d-flex flex-column h-100" enctype="multipart/form-data">
            <div>
                <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                    <h3 class="mb-0 fs-18 line--limit-1">{{ translate('Create Subscription Plan') }}</h3>
                    <button type="button"
                            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                            aria-label="Close">&times;
                    </button>
                </div>
        
                <div class="custom-offcanvas-body p-20">
                    <div class="bg--secondary rounded pt-2 px-3 mb-20 pb-3">        
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.Create Subscription Plan') }}
                                    </label>
                                    <div class="resturant-type-group module_select-area max-w-542 w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                                        <label class="form-check form--check w-100">
                                            <input class="form-check-input" type="radio" value="central_setup" name="subscription_status" checked>
                                            <span class="form-check-label">
                                                {{ translate('messages.Paid') }}
                                            </span>
                                        </label>
                                        <label class="form-check form--check w-100">
                                            <input class="form-check-input" type="radio" value="individual_setup" name="subscription_status">
                                            <span class="form-check-label">
                                                {{ translate('messages.Free Trial') }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.plan name') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ translate('messages.ex: monthly') }}" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.Plan Price ($)') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ translate('messages.ex: 500') }}" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{ translate('messages.Duration (Days)') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ translate('messages.ex: 100') }}" required>
                                </div>
                            </div>                            
                        </div>
                    </div>
                </div>
            </div>
        
            <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
                <button type="button" class="btn w-100 btn--reset">{{ translate('reset') }}</button>
                <button type="submit" class="btn w-100 btn--primary">{{ translate('Add') }}</button>
            </div>
        </form>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

@push('script_2')

@endpush