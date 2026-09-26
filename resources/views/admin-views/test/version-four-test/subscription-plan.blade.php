@extends('layouts.admin.app')

@section('title', translate('messages.subscription'))

@section('content')


<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>
                    {{ translate('Subscription Plan') }}
                </span>
            </h1>
        </div>
    </div>

    <div class="card mb-20">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-20">
                <div class="">
                    <div class="">
                        <h3 class="mb-1 fs-16">{{ translate('Subscription plan') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('here you can see an overview of subscription plans.') }} 
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn--cancel py-1 h-40 text-nowrap px-3 offcanvas-trigger"
                       data-target="#offcanvas__createplan">
                        {{ translate('Cancel Subscription') }} 
                    </button>
                    <button type="button" class="btn btn--primary h-40 text-nowrap px-3" data-toggle="modal" data-target="#plan_modal_area">
                        {{ translate('Change / Renew Subscription') }} 
                    </button>
                </div>
            </div>
            <div class="bg-light2 p-xxl-20 p-3">
                <div class="row g-3">
                    <div class="col-md-12 col-lg-4">
                        <div class="subscription-plan__card">
                            <div class="">
                                <div class="mb-2 d-flex gap-2 align-items-center justify-content-start">
                                    <div class="w-40px">
                                        <img width="36" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                    </div>
                                    <h3 class="mb-0 fs-24 fw-medium lh-1">
                                        {{ translate('Monthly') }}
                                    </h3>
                                </div>
                                <p class="mb-0 fs-32 font-semibold text-dark">
                                    $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bg-white subscription-plan__card p-3 rounded">
                            <div class="mb-3 d-flex gap-2 align-items-center justify-content-start">
                                <h3 class="mb-0 fs-14 fw-medium lh-1">
                                    {{ translate('Plan Validity') }}
                                </h3>
                                <span class="badge text-success bg-success bg-opacity-10 px-2 rounded-pill fs-12">
                                    {{ translate('active') }}
                                </span>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="fs-14 min-w-90">{{ translate('start date') }}</span>
                                    <span>:</span>
                                    <span class="fs-14 text-dark">01 Jun 2026 12:00 am</span>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="fs-14 min-w-90">{{ translate('Expire date') }}</span>
                                    <span>:</span>
                                    <span class="fs-14 text-dark">31 Feb 2023 11:59 pm</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bg-white subscription-plan__card p-3 rounded">
                            <div class="mb-3 d-flex gap-2 align-items-center justify-content-start">
                                <h3 class="mb-0 fs-14 fw-medium lh-1">
                                    {{ translate('Transaction') }}
                                </h3>
                                <span class="fs-14 fw-medium text-dark">
                                    #5757756
                                </span>
                                <span class="badge text-danger bg-danger bg-opacity-10 px-2 rounded-pill fs-12">
                                    {{ translate('expired') }}
                                </span>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="fs-14 min-w-90">{{ translate('Payment date') }}</span>
                                    <span>:</span>
                                    <span class="fs-14 text-dark">31 Feb 2023 11:59 pM</span>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="fs-14 min-w-90">{{ translate('Paid by') }}</span>
                                    <span>:</span>
                                    <span class="fs-14 text-dark">SSL commerz</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <button class="btn btn--primary" data-toggle="modal" data-target="#plan_renew-subscription">Renew subscription</button>
    <button class="btn btn--primary" data-toggle="modal" data-target="#plan_shift-subscription">shift subscription</button>
</div>


    <!-- shif renew modal -->
    <div class="modal fade" id="plan_modal_area" tabindex="-1" role="dialog" aria-labelledby="modalLabel"
        aria-hidden="true">
        <div class=" modal-dialog max-w-500px mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <div class="mb-20">
                        <form class="" method="post">
                            @method('put')
                            @csrf
                            <h3 class="mb-3 fs-20 text-center"> {{ translate('Shift / Renew Subscription') }}</h3>
                            <div class="border rounded-10">
                                <div class="bg-plan rounded-10">
                                    <div class="rounded-4 bg-plan-gradient p-3 text-center plan-pro-head">
                                        <div class="w-40px mx-auto mb-10px">
                                            <img width="36" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                        </div>
                                        <h3 class="mb-1 fs-18 fw-medium lh-1">
                                            {{ translate('StackFood Pro') }}
                                        </h3>
                                        <p class="mb-0 fs-14">
                                            {{ translate('Save more on every order') }}
                                        </p>
                                    </div>
                                    <div class="d-flex flex-column gap-20px p-20">
                                        <div class="d-flex gap-2">
                                            <img width="20" src="{{asset('public/assets/admin/img/check-circle.svg')}}" alt="img" class="arrows">
                                            <div class="cont">
                                                <h3 class="mb-1 fs-16 font-weight-light lh-1">
                                                    {{ translate('Up-to 5% off on all orders') }}
                                                </h3>
                                                <p class="mb-0 fs-12">
                                                    {{ translate('Applied automatically at checkout') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <img width="20" src="{{asset('public/assets/admin/img/check-circle.svg')}}" alt="img" class="arrows">
                                            <div class="cont">
                                                <h3 class="mb-1 fs-16 font-weight-light lh-1">
                                                    {{ translate('Free delivery') }}
                                                </h3>
                                                <p class="mb-0 fs-12">
                                                    {{ translate('On orders above a minimum amount') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <img width="20" src="{{asset('public/assets/admin/img/check-circle.svg')}}" alt="img" class="arrows">
                                            <div class="cont">
                                                <h3 class="mb-1 fs-16 font-weight-light lh-1">
                                                    {{ translate('Exclusive offers on Food') }}
                                                </h3>
                                                <p class="mb-0 fs-12">
                                                    {{ translate('Pro-only deals and early access') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-20">
                                    <p class="fs-14 mb-10px">
                                        {{ translate('Select duration') }}
                                    </p>
                                    <select name="" id="" class="custom-select mb-10px">
                                        <option value="1">30 days</option>
                                        <option value="1">this month</option>
                                        <option value="1">this week</option>
                                    </select>
                                    <div class="bg-plan-monthly d-flex p-3 rounded align-items-center gap-2 justify-content-between">
                                        <div class="mb-0 d-flex gap-2 align-items-center justify-content-start">
                                            <h3 class="mb-0 fs-14 fw-medium lh-1">
                                                {{ translate('monthly') }}
                                            </h3>
                                            <span class="badge text-success bg-success bg-opacity-10 px-2 rounded-pill fs-12">
                                                {{ translate('active') }}
                                            </span>
                                        </div>
                                        <h3 class="m-0 fs-25">$20.00</h3>
                                    </div>
                                    <div class="mt-4 text-center">
                                        <button type="button" class="max-w-260px w-100 btn btn--primary px-3">
                                            {{ translate('Renew Subscription') }}
                                        </button>
                                        <button type="button" class="max-w-260px w-100 btn btn--primary px-3">
                                            {{ translate('Shift Subscription') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Renew Subscription modal -->
    <div class="modal fade" id="plan_renew-subscription" tabindex="-1" role="dialog" aria-labelledby="modalLabel"
        aria-hidden="true">
        <div class=" modal-dialog max-w-500px mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <div class="mb-20">
                        <form class="" method="post">
                            @method('put')
                            @csrf
                            <h3 class="mb-3 fs-20 text-center"> {{ translate('Renew Subscription') }}</h3>
                            <div class="border rounded-10 p-4">
                                <div class="bg-plan max-w-353px mx-auto subscription-plan__card rounded-10 mb-4">
                                    <div class="d-flex align-items-center gap-3 rounded-0 bg-plan-gradient p-3 plan-pro-head">
                                        <div class="w-40px mb-0">
                                            <img width="36" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                        </div>
                                        <div class="text-start">
                                            <h3 class="mb-1 fs-24 fw-medium lh-1">
                                                {{ translate('Monthly') }}
                                            </h3>
                                            <p class="mb-0 fs-32 font-semibold text-dark">
                                                $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center pt-2">
                                    <p class="mb-3">
                                        {{ translate('#Note : Ensure payment is received before changing or renewing the subscription.') }}
                                    </p>
                                    <button type="button" class="max-w-260px w-100 btn p-0 btn btn--primary py-2 px-3">
                                        {{ translate('confirm renew') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Renew Subscription modal -->
    <div class="modal fade" id="plan_shift-subscription" tabindex="-1" role="dialog" aria-labelledby="modalLabel"
        aria-hidden="true">
        <div class=" modal-dialog max-w-850px mx-auto modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close bg-light w-40px h-40px rounded-circle fs-20" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-4 px-4 pt-0">
                    <div class="mb-20">
                        <form class="" method="post">
                            @method('put')
                            @csrf
                            <h3 class="mb-3 fs-20 text-center"> {{ translate('Shift Subscription') }}</h3>
                            <div class="border rounded-10 p-4">
                                <div class="p-xl-1 d-flex align-items-center mb-4 gap-2 justify-content-center flex-md-nowrap flex-wrap">
                                    <div class="position-relative w-100 bg-light subscription-plan__card shift_subs-card rounded-10">
                                        <div class="d-flex align-items-center gap-2 p-3 plan-pro-head">
                                            <div class="w-40px mb-0">
                                                <img width="36" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                            </div>
                                            <div class="text-start">
                                                <h3 class="mb-1 fs-24 fw-medium lh-1">
                                                    {{ translate('Monthly') }}
                                                </h3>
                                                <p class="mb-0 fs-32 font-semibold text-dark">
                                                    $ 20.00 <span class="fs-20 font-weight-light gray-dark">/30 days</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="check-arrow position-absolute top-0 right-0 m-2 opacity-0">
                                            <img width="22" src="{{asset('public/assets/admin/img/check-circle.svg')}}" alt="img" class="arrows">
                                        </div>
                                    </div>
                                    <img width="26" src="{{asset('public/assets/admin/img/convert-arrow.png')}}" alt="img" class="arrows d-md-block d-none">
                                    <div class="position-relative w-100 subscription-plan__card shift_subs-card active rounded-10">
                                        <div class="d-flex align-items-center gap-2 p-3 plan-pro-head">
                                            <div class="w-40px mb-0">
                                                <img width="36" src="{{asset('public/assets/admin/img/subscription-win-badge.png')}}" alt="img" class="rounded-circle">
                                            </div>
                                            <div class="text-start">
                                                <h3 class="mb-1 fs-24 fw-medium lh-1">
                                                    {{ translate('yearly') }}
                                                </h3>
                                                <p class="mb-0 fs-32 font-semibold text-dark">
                                                    $ 120.00 <span class="fs-20 font-weight-light gray-dark">/365 days</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="check-arrow position-absolute top-0 right-0 m-2 opacity-0">
                                            <img width="22" src="{{asset('public/assets/admin/img/check-circle.svg')}}" alt="img" class="arrows">
                                        </div>
                                    </div>
                                </div>
                                <div class=" d-flex align-items-center mb-4 pb-xxl-2 gap-20px flex-sm-nowrap flex-wrap justify-content-center">
                                    <div class="w-100 d-flex align-items-center rounded py-3 px-3 border justify-content-between gap-1">
                                        <label class="form-check form--check mr-2 mr-md-4">
                                            <input class="form-check-input" type="radio" value="" name="payment_status" checked>
                                            <span class="form-check-label">{{ translate('messages.Pay via Wallet') }}</span>
                                        </label>   
                                        <img width="22" src="{{asset('public/assets/admin/img/wallet-in.svg')}}" alt="img" class="24px">                                     
                                    </div>
                                    <div class="w-100 d-flex align-items-center rounded py-3 px-3 border justify-content-between gap-1">
                                        <label class="form-check form--check mr-2 mr-md-4">
                                            <input class="form-check-input" type="radio" value="" name="payment_status">
                                            <span class="form-check-label">{{ translate('messages.Manually pay') }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="text-center pb-2 pt-2">
                                    <p class="mb-3">
                                        {{ translate('#Note : Ensure payment is received before changing or renewing the subscription.') }}
                                    </p>
                                    <button type="button" class="max-w-260px w-100 btn btn btn--primary px-3">
                                        {{ translate('confirm shift') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')

@endpush