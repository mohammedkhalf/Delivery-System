@extends('layouts.admin.app')

@section('title',translate('login_page_setup'))

@push('css_or_js')
    <style>
        .form-check-label .form-check-link,
        .custom-control-label .form-check-link {
            position: relative;
            z-index: 4;
            pointer-events: auto;
        }
    </style>
@endpush

@section('content')
    <div class="content">
        <form action="{{route('admin.business-settings.login-settings.update')}}" method="post" id="login-settings-form">
            @csrf
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="page-header d-flex flex-wrap align-items-center justify-content-between">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{asset('public/assets/admin/img/app.png')}}" class="w--26" alt="">
                        </span>
                        <span>
                            {{translate('login_setup')}}
                        </span>
                    </h1>
                </div>
                <!-- End Page Header -->

                <ul class="nav nav-tabs border-0 nav--tabs nav--pills mb-4">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('admin.business-settings.login-settings.index') }}">{{translate('Customer_Login')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.business-settings.login_url_page') }}">{{translate('panel_login_page_Url')}}</a>
                    </li>
                </ul>

                <div class="card mb-20">
                    <div class="card-header">
                        <div>
                            <h4 class="fs-16 mb-1 font-semibold d-block">{{ translate('Login Setup') }}</h4>
                            <p class="fs-12 mb-0">{{ translate('The option you select customer will have the to option to login') }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card card-body">
                            <div class="mb-20">
                                <h4 class="fs-16 mb-1 font-semibold d-block">{{ translate('Choose How to Login') }}</h4>
                                <p class="fs-12 mb-0">{{ translate('The option you select customer will have the option to login customer app & websites') }}</p>
                            </div>
                            <div class="d-flex gap-2 fs-12 text-dark px-3 py-2 rounded bg-warning mb-20" style="--bs-bg-opacity: 0.1;">
                                <span class="text-warning lh-1 fs-14">
                                    <i class="tio-info"></i>
                                </span>
                                <span>
                                    {{ translate('messages.At least one login method must remain active for the
                                    customer. Otherwise they will be unable to log in to the
                                    system') }}
                                </span>
                            </div>
                            <div class="bg-light2 rounded p-3">
                                <div class="border bg-white rounded p-xl-3 p-2">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="custom-checkbox custom-control d-flex gap-2">
                                                <input class="custom-control-input login-option-type" type="checkbox" name="manual_login_status" id="customer-manual-login" value="1" {{ (isset($data['manual_login_status']) && $data['manual_login_status'] == '1')? 'checked':'' }}>
                                                <label class="custom-control-label d-flex flex-column justify-content-between mb-0"  for="customer-manual-login">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            {{ translate('messages.Manual Login') }}
                                                        </h5>
                                                        <p class="fs-12 m-0">
                                                            {{ translate('messages.By enabling manual login,
                                                            customers will get the option
                                                            to create account & login using necessary
                                                            credentials & password in the
                                                            app & website') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="custom-checkbox custom-control d-flex gap-2">
                                                <input class="custom-control-input login-option-type" type="checkbox" name="otp_login_status" id="customer-otp-login" value="1" {{ (isset($data['otp_login_status']) && $data['otp_login_status'] == '1')? 'checked':'' }}>
                                                <label class="custom-control-label d-flex flex-column justify-content-between mb-0"  for="customer-otp-login">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            {{ translate('messages.OTP Login') }}
                                                        </h5>
                                                        <p class="fs-12 m-0">
                                                            {{ translate('messages.With OTP Login, customers
                                                            can log in using their phone
                                                            number without password. To enable this feature') }}
                                                            <a href="{{ route('admin.business-settings.third-party.sms-module') }}" target="_blank" class="text-primary text-underline font-weight-bold">{{ translate('messages.Configure SMS Setup ') }}</a>
                                                            {{ translate('messages.Here.') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="custom-checkbox custom-control d-flex gap-2">
                                                <input class="custom-control-input login-option-type" type="checkbox" name="social_login_status" id="customer-social-login" value="1" {{ (isset($data['social_login_status']) && $data['social_login_status'] == '1')? 'checked':'' }}>
                                                <label class="custom-control-label d-flex flex-column justify-content-between mb-0"  for="customer-social-login">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            {{ translate('messages.Social Media Login') }}
                                                        </h5>
                                                        <p class="fs-12 m-0">
                                                            {{ translate('messages.With Social Login, customers
                                                            can log in using social media
                                                            accounts. To enable this feature') }}
                                                            <a href="{{ route('admin.business-settings.third-party.social-login.view') }}"  target="_blank" class="text-primary text-underline font-weight-bold">{{ translate('messages.Configure Social Media Setup') }}</a>
                                                            {{ translate('messages.Here.') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card card-body mt-3 social-media-login-container" style="display: {{ (isset($data['social_login_status']) && $data['social_login_status'] == '1')? '':'none' }}" id="social-login-area">
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-20">
                                <div class="flex-grow-1">
                                    <h4 class="fs-16 mb-1 font-semibold d-block">{{ translate('Social Media Login Setup') }}</h4>
                                    <p class="fs-12 mb-0">{{ translate('Select which social media you want for customer login') }}</p>
                                </div>
                            </div>
                            <div class="d-flex gap-2 fs-12 text-dark px-3 py-2 rounded bg-warning mb-20" style="--bs-bg-opacity: 0.1;">
                                <span class="text-warning lh-1 fs-14">
                                    <i class="tio-info"></i>
                                </span>
                                <span>
                                    {{ translate('messages.At least one social media must remain active for login. Otherwise social media login can’t work.') }}
                                </span>
                            </div>
                            <div class="bg-light2 rounded p-3">
                                <div class="border bg-white rounded p-xl-3 p-2">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="custom-checkbox custom-control d-flex gap-2 {{ !$google_login_status ? 'cursor-pointer' : '' }}"
                                                @if (!$google_login_status)
                                                    data-toggle="tooltip"
                                                    title="{{ translate('messages.Google is currently disabled from configure 3rd party social login options.') }}"
                                                @endif>
                                                <input type="checkbox" name="google_login_status" id="google_login_status" value="1" {{ (isset($data['google_login_status']) && $data['google_login_status'] == '1')? 'checked':'' }}
                                                    class="custom-control-input social-media-status-checkbox" {{ !$google_login_status ? 'disabled' : '' }}>
                                                <label class="custom-control-label d-flex flex-column justify-content-between mb-0 {{ !$google_login_status ? 'disabled' : '' }}"  for="google_login_status">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            {{ translate('messages.Google') }}
                                                        </h5>
                                                        <p class="fs-12 m-0">
                                                            {{ translate('messages.Enabling Google Login, customers can log in to the site using their existing Gmail credentials.') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="custom-checkbox custom-control d-flex gap-2 {{ !$facebook_login_status ? 'cursor-pointer' : '' }}"
                                                @if (!$facebook_login_status)
                                                    data-toggle="tooltip"
                                                    title="{{ translate('messages.Facebook is currently disabled from configure 3rd party social login options.') }}"
                                                @endif>
                                                <input type="checkbox" name="facebook_login_status" id="facebook_login_status" value="1" {{ (isset($data['facebook_login_status']) && $data['facebook_login_status'] == '1')? 'checked':'' }}
                                                    class="custom-control-input social-media-status-checkbox" {{ !$facebook_login_status ? 'disabled' : '' }}>
                                                <label class="custom-control-label d-flex flex-column justify-content-between mb-0 {{ !$facebook_login_status ? 'disabled' : '' }}"  for="facebook_login_status">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            {{ translate('messages.Facebook') }}
                                                        </h5>
                                                        <p class="fs-12 m-0">
                                                            {{ translate('messages.Enabling Facebook Login, customers can log in to the site using their existing Facebook credentials') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="custom-checkbox custom-control d-flex gap-2 {{ !$apple_login_status ? 'cursor-pointer' : '' }}"
                                                @if (!$apple_login_status)
                                                    data-toggle="tooltip"
                                                    title="{{ translate('messages.Apple is currently disabled from configure 3rd party social login options.') }}"
                                                @endif>
                                               <input type="checkbox" name="apple_login_status" id="apple_login_status" value="1" {{ (isset($data['apple_login_status']) && $data['apple_login_status'] == '1')? 'checked':'' }}
                                               class="custom-control-input social-media-status-checkbox" {{ !$apple_login_status ? 'disabled' : '' }}>
                                                <label class="custom-control-label d-flex flex-column justify-content-between mb-0 {{ !$apple_login_status ? 'disabled' : '' }}"  for="apple_login_status">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            {{ translate('messages.Apple') }}
                                                        </h5>
                                                        <p class="fs-12 m-0">
                                                            {{ translate('messages.Enabling Apple Login, customers can log in to the site using their existing Apple login credentials, Only for Apple devicesusing') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="card card-body">
                    <div class="mb-20">
                        <h4 class="fs-16 mb-1 font-semibold d-block">{{ translate('Verification') }}</h4>
                        <p class="fs-12 mb-0">{{ translate('The option you select from below will need to verify by customer from customer app/website.') }}</p>
                    </div>
                    <div class="d-flex gap-2 fs-12 text-dark px-3 py-2 rounded bg-warning mb-20" style="--bs-bg-opacity: 0.1;">
                        <span class="text-warning lh-1 fs-14">
                            <i class="tio-info"></i>
                        </span>
                        <span>
                            {{ translate('messages.At least one login option must remain active for Verification. Otherwise you will be unable to select & Save.') }}
                        </span>
                    </div>

                    <div class="bg-light2 rounded p-3">
                        <div class="border bg-white rounded p-xl-3 p-2">
                            <div class="row g-3">
                                <div class="col-lg-6 col-sm-6">
                                    <div class="custom-checkbox custom-control d-flex gap-2 {{ !$is_mail_active ? 'cursor-pointer' : '' }}"
                                        @if (!$is_mail_active)
                                            data-toggle="tooltip"
                                            title="{{ translate('messages.Email verification is currently disabled from mail setup options.') }}"
                                        @endif>
                                        <input type="checkbox" name="email_verification_status" id="email_verification_status" value="1" {{ (isset($data['email_verification_status']) && $data['email_verification_status'] == '1')? 'checked':'' }}
                                        class="custom-control-input social-media-status-checkbox" {{ !$is_mail_active ? 'disabled' : '' }}>
                                        <label class="custom-control-label d-flex flex-column justify-content-between mb-0 {{ !$is_mail_active ? 'disabled' : '' }}"  for="email_verification_status">
                                            <div>
                                                <h5 class="mb-1">
                                                    {{ translate('messages.Email Verification') }}
                                                </h5>
                                                <p class="fs-12 m-0">
                                                    {{ translate('messages.If Email verification is on, Customers must verify their email with an OTP to complete the signup process.') }}
                                                     <a href="{{ route('admin.business-settings.third-party.mail-config') }}" target="_blank" class="text-primary text-underline font-weight-bold form-check-link">{{ translate('messages.Email Setup') }}</a>
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-6">
                                    <div class="custom-checkbox custom-control d-flex gap-2">
                                        <input type="checkbox" name="phone_verification_status" id="phone_verification" value="1" {{ (isset($data['phone_verification_status']) && $data['phone_verification_status'] == '1')? 'checked':'' }}
                                            class="custom-control-input social-media-status-checkbox">
                                        <label class="custom-control-label d-flex flex-column justify-content-between mb-0"  for="phone_verification">
                                            <div>
                                                <h5 class="mb-1">
                                                    {{ translate('messages.Phone Number Verification') }}
                                                </h5>
                                                <p class="fs-12 m-0">
                                                    {{ translate('messages.If Phone Number verification is on, Customers must verify their Phone Number with an OTP to complete the signup process.') }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-body mt-3 otp-login-container">
                    <div class="mb-20">
                        <h4 class="fs-16 mb-1 font-semibold d-block">{{ translate('How to Get OTP for Login & Verification') }}</h4>
                        <p class="fs-12 mb-0">{{ translate('The option you select from below will be your system OTP sending getway.') }}</p>
                    </div>
                    <div class="d-flex gap-2 fs-12 text-dark px-3 py-2 rounded bg-warning mb-20" style="--bs-bg-opacity: 0.1;">
                        <span class="text-warning lh-1 fs-14">
                            <i class="tio-info"></i>
                        </span>
                        <span>
                            {{ translate('messages.At least one login option must remain active for work the OTP system.') }}
                        </span>
                    </div>

                    <div class="bg-light2 rounded p-3">
                        <div class="border bg-white rounded p-xl-3 p-2 mb-20">
                            <div class="row g-3">
                                <div class="col-lg-6 col-sm-6">
                                    <div class="form-check form--check d-flex gap-2 {{ !$is_firebase_active ? 'cursor-pointer' : '' }}"
                                        @if (!$is_firebase_active)
                                            data-toggle="tooltip"
                                            title="{{ translate('messages.Firebase OTP is currently disabled from OTP setup options.') }}"
                                        @endif>
                                        <input type="radio" class="form-check-input" value="firebase" name="send_otp_via" id="otp_firebase"  {{ data_get($data , 'send_otp_via') == 'firebase' && $is_firebase_active ? 'checked' : '' }} {{ !$is_firebase_active ? 'disabled' : '' }}>
                                        <label class="form-check-label d-flex flex-column justify-content-between mb-0 {{ !$is_firebase_active ? 'disabled' : '' }}"  for="otp_firebase">
                                            <div>
                                                <h5 class="mb-1">
                                                    {{ translate('messages.Firebase OTP') }}
                                                </h5>
                                                <p class="fs-12 m-0">
                                                    {{ translate('messages.With Firebase OTP enabled, verification codes will be sent through Firebase. To setup firebase visit') }}
                                                    <a href="{{ route('admin.business-settings.third-party.firebase_otp_index') }}" target="_blank" class="text-primary text-underline font-weight-bold form-check-link">{{ translate('messages.firebase OTP Setup ') }}</a>
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-6">
                                    <div class="form-check form--check d-flex gap-2 {{ !$is_sms_active ? 'cursor-pointer' : '' }}"
                                        @if (!$is_sms_active)
                                            data-toggle="tooltip"
                                            title="{{ translate('messages.SMS Gateway is currently disabled from OTP setup options.') }}"
                                        @endif>
                                        <input type="radio" class="form-check-input" value="sms" name="send_otp_via" id="otp_sms" {{ (data_get($data , 'send_otp_via') != 'firebase' || !$is_firebase_active) && $is_sms_active ? 'checked' : '' }} {{ !$is_sms_active ? 'disabled' : '' }}>
                                        <label class="form-check-label d-flex flex-column justify-content-between mb-0 {{ !$is_sms_active ? 'disabled' : '' }}"  for="otp_sms">
                                            <div>
                                                <h5 class="mb-1">
                                                    {{ translate('messages.Use SMS Gateway') }}
                                                </h5>
                                                <p class="fs-12 m-0">
                                                    {{ translate('messages.With SMS Gateway you must setup at least one gateway.To setup visit') }}
                                                    <a href="{{ route('admin.business-settings.third-party.sms-module') }}" target="_blank" class="text-primary text-underline font-weight-bold form-check-link">{{ translate('messages.3rd Party SMS Gateway') }}</a>
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-sticky mt-2">
                <div class="container-fluid">
                <div class="d-flex flex-wrap gap-3 justify-content-center py-3">
                    <button type="reset" id="reset_btn" class="btn btn--secondary min-w-120 location-reload">{{ translate('messages.Reset') }} </button>
                    <button type="{{ getEnvMode() != 'demo' ? 'submit' : 'button' }}" class="btn btn--primary call-demo">
                        <i class="tio-save"></i>
                        {{ translate('Save_Information') }}
                    </button>
                </div>
                </div>
            </div>
        </form>
    </div>


    <div class="modal fade" id="select-one-method-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/modal/package-status-disable.png')}}" alt="" class="mb-20">
                        <h5 class="modal-title">{{translate('Important Alert !')}}</h5>
                    </div>
                    <p>{{ translate('At least one login method must remain active for the customer; otherwise, they will be unable to log in to the system') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary mw-300px" data-dismiss="modal">{{translate('okay')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sms-config-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/sms-configuration.svg')}}" alt="" class="mb-20 img--80">
                        <h5 class="modal-title">{{translate('Set Up SMS Configuration First')}}</h5>
                    </div>
                    <p>{{ translate('It looks like your SMS configuration is not set up yet. To enable the OTP system, please set up the SMS configuration first.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary w-100 mw-300px" href="{{ route('admin.business-settings.third-party.sms-module') }}" target="_blank">{{translate('Go to SMS Configuration')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="select-one-method-android-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/modal/package-status-disable.png')}}" alt="" class="mb-20">
                        <h5 class="modal-title">{{translate('Important Alert !')}}</h5>
                    </div>
                    <p>{{ translate('If you are activating only social login as the login method, you must enable at least one option between Google and Facebook for Android users.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary mw-300px" data-dismiss="modal">{{translate('okay')}}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="select-one-method-social-login-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/modal/package-status-disable.png')}}" alt="" class="mb-20">
                        <h5 class="modal-title">{{translate('Important Alert !')}}</h5>
                    </div>
                    <p>{{ translate('If you are activating social login as the login method, you must enable at least one option between Google, Facebook & Apple.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary mw-300px" data-dismiss="modal">{{translate('okay')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="setup-google-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/modal/google.png')}}" alt="" class="mb-20">
                        <h5 class="modal-title">{{translate('Set Up Google Configuration First')}}</h5>
                    </div>
                    <p>{{ translate('It looks like your Google Login configuration is not set up yet. To enable the Google Login option, please set up the Google configuration first.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary mw-300px" href="{{route('admin.business-settings.third-party.social-login.view')}}" target="_blank">{{translate('Go to Google Configuration')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="setup-facebook-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/modal/facebook.png')}}" alt="" class="mb-20">
                        <h5 class="modal-title">{{translate('Set Up Facebook Configuration First')}}</h5>
                    </div>
                    <p>{{ translate('It looks like your Facebook Login configuration is not set up yet. To enable the Facebook Login option, please set up the Facebook configuration first.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary mw-300px" href="{{route('admin.business-settings.third-party.social-login.view')}}" target="_blank">{{translate('Go to Facebook Configuration')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="setup-apple-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/modal/apple.png')}}" alt="" class="mb-20">
                        <h5 class="modal-title">{{translate('Set Up Apple Configuration First')}}</h5>
                    </div>
                    <p>{{ translate('It looks like your Apple Login configuration is not set up yet. To enable the Apple Login option, please set up the Apple configuration first.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary mw-300px" href="{{route('admin.business-settings.third-party.social-login.view')}}" target="_blank">{{translate('Go to Apple Configuration')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sms-config-verification-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/sms-configuration.svg')}}" alt="" class="mb-20 img--80">
                        <h5 class="modal-title">{{translate('Set Up SMS Configuration First')}}</h5>
                    </div>
                    <p>{{ translate('It looks like your SMS configuration is not set up yet. To enable the phone verification, please set up the SMS configuration first.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary w-100 mw-300px" href="{{ route('admin.business-settings.third-party.sms-module') }}" target="_blank">{{translate('Go to SMS Configuration')}}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mail-config-verification-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('public/assets/admin/img/sms-configuration.svg')}}" alt="" class="mb-20 img--80">
                        <h5 class="modal-title">{{translate('Set Up Mail Configuration First')}}</h5>
                    </div>
                    <p>{{ translate('It looks like your Mail configuration is not set up yet. To enable the email verification, please set up the Mail configuration first.') }}</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <a type="button" class="btn btn--primary w-100 mw-300px" href="{{ route('admin.business-settings.third-party.mail-config') }}" target="_blank">{{translate('Go to Mail Configuration')}}</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script type="text/javascript">
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();

            @if (session('select-one-method'))
            $('#select-one-method-modal').modal('show');
            @endif
            @if (session('sms-config'))
            $('#sms-config-modal').modal('show');
            @endif
            @if (session('select-one-method-android'))
            $('#select-one-method-android-modal').modal('show');
            @endif
            @if (session('select-one-method-social-login'))
            $('#select-one-method-social-login-modal').modal('show');
            @endif
            @if (session('setup-google'))
            $('#setup-google-modal').modal('show');
            @endif
            @if (session('setup-facebook'))
            $('#setup-facebook-modal').modal('show');
            @endif
            @if (session('setup-apple'))
            $('#setup-apple-modal').modal('show');
            @endif
            @if (session('sms-config-verification'))
            $('#sms-config-verification-modal').modal('show');
            @endif
            @if (session('mail-config-verification'))
            $('#mail-config-verification-modal').modal('show');
            @endif


            function toggleLoginSections() {
                const otpOrPhoneEnabled = $('#customer-otp-login').is(':checked') || $('#phone_verification').is(':checked');
                $('.otp-login-container').toggle(otpOrPhoneEnabled);
                $('#social-login-area').toggle($('#customer-social-login').is(':checked'));
            }

            function syncPhoneVerificationRequirement() {
                const otpLoginEnabled = $('#customer-otp-login').is(':checked');
                $('#phone_verification').prop('required', otpLoginEnabled);
                if (otpLoginEnabled) {
                    $('#phone_verification').prop('checked', true);
                }
            }

            toggleLoginSections();
            syncPhoneVerificationRequirement();

            $('.login-option-type').on('change', function() {
                toggleLoginSections();
                syncPhoneVerificationRequirement();
            });

            $('#phone_verification').on('click', function(e) {
                if ($('#customer-otp-login').is(':checked')) {
                    e.preventDefault();
                    $(this).prop('checked', true);
                    toastr.info('{{ translate('messages.Phone verification is required when OTP login is enabled.') }}');
                }
            });

            $('#phone_verification').on('change', function() {
                toggleLoginSections();
            });

            $('#login-settings-form').on('submit', function(event) {
                if ($('#customer-otp-login').is(':checked') && !$('#phone_verification').is(':checked')) {
                    event.preventDefault();
                    toastr.error('{{ translate('messages.Phone verification is required when OTP login is enabled.') }}');
                }
            });

        });
    </script>
@endpush
