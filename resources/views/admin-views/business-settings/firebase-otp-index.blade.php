@extends('layouts.admin.app')

@section('title', translate('Firebase OTP Verification'))

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/firebase_auth.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Firebase OTP Verification')}}
                </span>
            </h1>

            @include('admin-views.business-settings.partials.third-party-links')
            <div class="">
                <div class="text--primary-2  mx-4 d-flex flex-wrap justify-content-end align-items-center" type="button" data-toggle="modal" data-target="#instructionsModal">
                    <strong class="mr-2">{{translate('How it Works')}}</strong>
                    <div class="blinkings">
                        <i class="tio-info text-gray1 fs-16"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Page Header -->



        <form
            action="{{getEnvMode()!='demo'?route('admin.business-settings.third-party.firebase_otp_update',['recaptcha']):'javascript:'}}"
            method="post">
            @csrf
            <div class="d-flex gap-2 fs-12 text-dark px-3 py-2 rounded bg-warning mb-3" style="--bs-bg-opacity: 0.1;">
                <span class="text-info lh-1 fs-14">
                    <i class="tio-info"></i>
                </span>
                <span>
                    {{ translate('messages.Web Api Key field need to fill properly otherwise OTP authentication can’t work.') }}
                </span>
            </div>
            <div class="row g-3">
                <div class="col-lg-12">

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-20">
                                <div class="">
                                    <div class="">
                                        <h3 class="mb-1 fs-16">{{ translate('Firebase Authentication') }}</h3>
                                        <p class="mb-0 gray-dark fs-12">
                                            {{ translate('To work the firebase OTP properly need to use exact API key.') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    @php($firebase_otp_verification = \App\Models\BusinessSetting::where('key', 'firebase_otp_verification')->first())
                                    @php($firebase_otp_verification = $firebase_otp_verification ? $firebase_otp_verification->value : '')
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch h--45px toggle-switch-sm">
                                            <input type="checkbox"
                                                   data-id="firebase_otp_verification"
                                                   data-type="toggle"
                                                   data-image-on="{{ asset('public/assets/admin/img/modal/order-delivery-verification-on.png') }}"
                                                   data-image-off="{{ asset('public/assets/admin/img/modal/order-delivery-verification-off.png') }}"
                                                   data-title-on="<strong>{{translate('Want to enable Firebase OTP Verification?')}}</strong>"
                                                   data-title-off="<strong>{{translate('Want to disable Firebase OTP Verification?')}}</strong> "
                                                   data-text-on="<p>{{ translate('With Firebase OTP enabled, verification codes will be sent through Firebase.') .' </p>' .'  <p>   <strong>
                                            Note: ' . translate('Enable Firebase OTP means users will not receive verification codes through Email or SMS Although those methods are activated.') .'</strong>'}}</p>"
                                                   data-text-off="<p>{{ translate('If you disable Firebase OTP, users will no longer receive verification codes via Firebase. You must activate Email or SMS verification as an alternative') }}</p>"
                                                   class="status toggle-switch-input dynamic-checkbox-toggle"
                                                   value="1"
                                                   name="firebase_otp_verification" id="firebase_otp_verification"
                                                {{ $firebase_otp_verification == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-light2 p-xl-20 p-3 rounded">
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-12">
                                        @php($firebase_web_api_key = \App\Models\BusinessSetting::where('key', 'firebase_web_api_key')->first())
                                        <div class="form-group mb-0">
                                            <label class=" input-label text-capitalize"
                                                   for="firebase_web_api_key">
                                                <span>
                                                    {{ translate('Web_API_key') }}
                                                </span>
                                                <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Enter_the_maximum_cash_amount_stores_can_hold._If_this_number_exceeds,_stores_will_be_suspended_and_not_receive_any_orders.') }}">
                                                    <i class="tio-info text-gray1 fs-16"></i>
                                                </span>

                                                {{-- <span class="form-label-secondary"
                                                      data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{ translate('Enter_the_maximum_cash_amount_stores_can_hold._If_this_number_exceeds,_stores_will_be_suspended_and_not_receive_any_orders.') }}"><img
                                                        src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="{{ translate('messages.dm_cancel_order_hint') }}"></span> --}}
                                            </label>
                                            <input type="text" name="firebase_web_api_key" class="form-control"
                                                   id="firebase_web_api_key"
                                                   value="{{ $firebase_web_api_key ? $firebase_web_api_key->value : '' }}"  required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-4">
                                <button type="reset" class="btn btn--reset">{{ translate('messages.reset') }}</button>
                                <button type="{{ getEnvMode() != 'demo' ? 'submit' : 'button' }}"
                                        class="btn btn--primary call-demo">{{ translate('save_information') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>




    <div class="modal fade" id="instructionsModal" tabindex="-1" aria-labelledby="instructionsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-end">
                    <button type="button" class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center my-5">
                        <img src="{{ asset('public/assets/admin/img/modal/bell.png') }}">
                    </div>

                    <h5 class="modal-title my-3" id="instructionsModalLabel">{{translate('Instructions')}}</h5>
                    <p>{{ translate('For configuring OTP in the Firebase, you must create a Firebase project first.If you haven’t created any project for your application yet, please create a project first.') }}
                    </p>
                    <p>{{ translate('Now go the') }} <a href="https://console.firebase.google.com/" target="_blank" class="text-underline text-info">Firebase console </a>{{ translate('and follow the instructions below') }} -</p>
                    <ol class="d-flex flex-column __gap-5px __instructions">
                        <li>{{ translate('Go to your Firebase project.') }}</li>
                        <li>{{ translate('Navigate to the Build menu from the left sidebar and select Authentication.') }}</li>
                        <li>{{ translate('Get started the project and go to the Sign-in method tab.') }}</li>
                        <li>{{ translate('From the Sign-in providers section, select the Phone option.') }}</li>
                        <li>{{ translate('Ensure to enable the method Phone and press save.') }}</li>
                    </ol>
                </div>
            </div>
        </div>
        </div>

    @endsection
