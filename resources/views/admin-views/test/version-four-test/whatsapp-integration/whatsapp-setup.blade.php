@extends('layouts.admin.app')

@section('title', translate('messages.3rd Party Integration'))


@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between gap-1 w-100">
            <h1 class="page-header-title">
                <span>
                    {{translate('3rd Party Integration')}}
                </span>
            </h1>
        </div>
    </div>
    <!-- Page Header -->

    <!-- End Page Header -->
    <form action="" method="post" enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1 fs-16">
                        {{ translate('WhatsApp Integration') }}
                    </h4>
                    <p class="mb-0 fs-12">
                        {{ translate('Manage the basic settings that control how vendors operate in your platform.') }}
                    </p>
                </div>
                <div class="bg-light rounded p-xxl-20 p-3 mb-20">
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-group mb-0 whatsapp_active">
                                <label class="d-flex align-items-center mb-2">
                                    <span class="text-dark pr-1">
                                        {{ translate('messages.Active WhatsApp') }} <span class="text-danger">*</span>
                                    </span>
                                </label>
                                <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                    <span class="pr-1 d-flex align-items-center switch--label">
                                        <span class="line--limit-1">
                                            {{ translate('messages.Status') }}
                                        </span>
                                    </span>
                                    <input type="checkbox" class="status toggle-switch-input" name="" id="">
                                    <span class="toggle-switch-label text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="d-flex align-items-center mb-2">
                                    <span class="text-dark pr-1">
                                        {{ translate('messages.Provider Selection') }} 
                                    </span>
                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.test') }}">
                                        <i class="tio-info text-light-gray"></i>
                                    </span>
                                </label>
                                <div class="provider-selection">
                                    <select name="" id="" class="custom-select">
                                        <option value="whatsapp">{{ translate('messages.WhatsApp') }} </option>
                                        <option value="thirdparty">{{ translate('messages.3rd Party') }} </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="d-flex align-items-center mb-2">
                                    <span class="text-dark pr-1">
                                        {{ translate('messages.WhatsApp API') }} 
                                    </span>
                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.test') }}">
                                        <i class="tio-info text-light-gray"></i>
                                    </span>
                                </label>
                                <div class="whatsapp-api">
                                    <input type="text" name="" class="form-control" placeholder="Ex: Type API Key" value="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="third-party-wrap">
                    <div>
                        <h4 class="mb-10px fs-16">
                            {{ translate('Select 3rd Part Provider') }}
                        </h4>
                        <div class="bg-light rounded p-xxl-20 p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="mb-0">
                                    <h3 class="mb-1">
                                        {{ translate('TWILIO') }}
                                    </h3>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Setup TWILIO as SMS gateway') }}
                                    </p>
                                </div>
                                <label class="toggle-switch h--45px toggle-switch-sm">                                
                                    <input type="checkbox" class="status toggle-switch-input" name="" id="" checked>
                                    <span class="toggle-switch-label text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                            <div class="bg-white rounded p-3 mt-20">
                                <div class="row g-3">
                                    <div class="col-lg-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Account SID') }} <span class="text-danger">*</span>
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.test') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </label>
                                           <input type="text" name="" class="form-control" placeholder="Ex: data" value="">
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Auth Token') }} <span class="text-danger">*</span>
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.test') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </label>
                                           <input type="text" name="" class="form-control" placeholder="Ex: data" value="">
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.From Number') }} <span class="text-danger">*</span>
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.test') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </label>
                                           <input type="text" name="" class="form-control" placeholder="Ex: +88012345678" value="">
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Messaging Service SID (optional)') }} 
                                                </span>
                                            </label>
                                           <input type="text" name="" class="form-control" placeholder="Ex: data" value="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light rounded p-xxl-20 p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="mb-0">
                                    <h3 class="mb-1">
                                        {{ translate('Massage Bird') }}
                                    </h3>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Setup Alphanet SMS as SMS gateway') }}
                                    </p>
                                </div>
                                <label class="toggle-switch h--45px toggle-switch-sm">                                
                                    <input type="checkbox" class="status toggle-switch-input" name="" id="" checked>
                                    <span class="toggle-switch-label text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="bg-light rounded p-xxl-20 p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="mb-0">
                                    <h3 class="mb-1">
                                        {{ translate('Watti') }}
                                    </h3>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Setup RELEANS as SMS gateway') }}
                                    </p>
                                </div>
                                <label class="toggle-switch h--45px toggle-switch-sm">                                
                                    <input type="checkbox" class="status toggle-switch-input" name="" id="" checked>
                                    <span class="toggle-switch-label text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('admin-views.partials._floating-submit-button')
    </form>



</div>
@endsection

@push('script_2')
<script>
    $(document).ready(function () {
        function handleAll() {
            let isChecked = $('.whatsapp_active .toggle-switch-input').is(':checked');
            let provider = $('.provider-selection select').val();

            if (!isChecked) {
                $('.provider-selection, .whatsapp-api').addClass('disabled');
                $('.provider-selection select, .whatsapp-api input').prop('disabled', true);

                $('.third-party-wrap').addClass('d-none').removeClass('d-block');

                return; 
            }

            $('.provider-selection').removeClass('disabled');
            $('.provider-selection select').prop('disabled', false);

            if (provider === 'thirdparty') {
                $('.whatsapp-api').addClass('disabled');
                $('.whatsapp-api input').prop('disabled', true);

                $('.third-party-wrap').removeClass('d-none').addClass('d-block');
            } else {
                $('.whatsapp-api').removeClass('disabled');
                $('.whatsapp-api input').prop('disabled', false);

                $('.third-party-wrap').addClass('d-none').removeClass('d-block');
            }
        }
        $(document).on('change', '.whatsapp_active .toggle-switch-input', handleAll);
        $(document).on('change', '.provider-selection select', handleAll);
        handleAll();
    });
</script>
@endpush
