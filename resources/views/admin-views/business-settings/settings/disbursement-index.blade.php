@extends('layouts.admin.app')

@section('title', translate('Disbursement_settings'))


@section('content')
    @php use App\CentralLogics\Helpers; @endphp
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/business.png') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.business_setup') }}
                </span>
            </h1>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>

        @php($disbursement_type = Helpers::get_business_settings('disbursement_type') ?? 'manual')
        <!-- Page Header -->

        <!-- End Page Header -->
        <form action="{{ route('admin.business-settings.update-disbursement') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row g-2">
                <div class="col-lg-12" id="disbursement_setup_section">
                    <div class="card mb-20">
                        <div class="card-body">
                            <div class="mb-0">
                                <h3 class="mb-1">
                                    {{ translate('Disbursement Setup') }}
                                </h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('Manage and configure how vendors & deliverymen receive their payouts') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-20">
                        <div class="card-body" id="disbursement_request_type_section">
                            <div class="row g-3 align-items-center">
                                <div class="col-xxl-7 col-lg-6">
                                    <div class="mb-0">
                                        <h4 class="mb-1">
                                            {{ translate('Disbursement Request Type') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Select Manual to approve payouts individually, or Automated to process them automatically') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-xxl-5 col-lg-6">
                                    <div class="form-group m-0">
                                        <div class="restaurant-type-group border flex-nowrap">
                                            <label class="form-check form--check w-100">
                                                <input class="form-check-input" type="radio" value="manual"
                                                    name="disbursement_type" id="disbursement_type"
                                                    {{ $disbursement_type == 'manual' ? 'checked' : '' }}>
                                                <span class="form-check-label">
                                                    {{ translate('Manual Request') }}
                                                </span>
                                            </label>
                                            <label class="form-check form--check w-100">
                                                <input class="form-check-input" type="radio" value="automated"
                                                    name="disbursement_type" id="disbursement_type2"
                                                    {{ $disbursement_type == 'automated' ? 'checked' : '' }}>
                                                <span class="form-check-label">
                                                    {{ translate('Automated Request') }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <div class="card mb-20 automated_disbursement_section {{ $disbursement_type == 'manual' ? 'd-none' : '' }}"
                        id="scheduler_dependency_section">
                        <div class="card-body">
                            <div class="row g-1 align-items-center">
                                <div class="col-xxl-9 col-xl-8 col-md-6">
                                    <div class="mb-0">
                                        <h4 class="mb-1">
                                            {{ translate('Scheduler Dependency') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Automated disbursement runs through Laravel\'s scheduler. Configure one cron entry on your server and every scheduled task — including disbursement — runs from it.') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-xxl-3 col-xl-4 col-md-6">
                                    <div class="d-flex justify-content-md-end">
                                        <button type="button" class="btn btn--primary" data-toggle="modal"
                                            data-target="#disbursementSchedulerModal">
                                            {{ translate('messages.Check_Dependencies') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="automated_disbursement_section {{ $disbursement_type == 'manual' ? 'd-none' : '' }} "
                        id="disbursement_request_setup_section">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="mb-20">
                                            <h4 class="mb-1">
                                                {{ translate('Vendor Panel Disbursement Request') }}
                                            </h4>
                                            <p class="mb-0 fs-12">
                                                {{ translate('Configure the parameters for when vendors can request disbursements.') }}
                                            </p>
                                        </div>
                                        <div class="bg-light2 rounded p-xxl-20 p-3">
                                            <div class="row">
                                                @php($store_disbursement_time_period = Helpers::get_business_settings('store_disbursement_time_period') ?? 1)
                                                <div class='{{ $store_disbursement_time_period == 'weekly' ? 'col-sm-6' : 'col-sm-6' }}'
                                                    id="store_time_period_section">
                                                    <div class="form-group lang_form default-form">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="store_disbursement_time_period"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Create_Disbursements') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Choose_how_the_disbursement_request_will_be_generated:_Monthly,_Weekly_or_Daily.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <select name="store_disbursement_time_period"
                                                            id="store_disbursement_time_period" class="form-control"
                                                            required>
                                                            <option value="daily"
                                                                {{ $store_disbursement_time_period == 'daily' ? 'selected' : '' }}>
                                                                {{ translate('messages.daily') }}
                                                            </option>
                                                            <option value="weekly"
                                                                {{ $store_disbursement_time_period == 'weekly' ? 'selected' : '' }}>
                                                                {{ translate('messages.weekly') }}
                                                            </option>
                                                            <option value="monthly"
                                                                {{ $store_disbursement_time_period == 'monthly' ? 'selected' : '' }}>
                                                                {{ translate('messages.monthly') }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class='col-sm-6 {{ $store_disbursement_time_period == 'weekly' ? '' : 'd-none' }}'
                                                    id="store_week_day_section">
                                                    @php($store_disbursement_week_start = Helpers::get_business_settings('store_disbursement_week_start') ?? 'saturday')
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="store_disbursement_week_start"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Week_Start') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Choose_when_the_week_starts_for_the_new_disbursement_request._This_section_will_only_appear_when_weekly_disbursement_is_selected.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <select name="store_disbursement_week_start"
                                                            id="store_disbursement_week_start" class="form-control"
                                                            required>
                                                            <option value="saturday"
                                                                {{ $store_disbursement_week_start == 'saturday' ? 'selected' : '' }}>
                                                                {{ translate('messages.saturday') }}
                                                            </option>
                                                            <option value="sunday"
                                                                {{ $store_disbursement_week_start == 'sunday' ? 'selected' : '' }}>
                                                                {{ translate('messages.sunday') }}
                                                            </option>
                                                            <option value="monday"
                                                                {{ $store_disbursement_week_start == 'monday' ? 'selected' : '' }}>
                                                                {{ translate('messages.monday') }}
                                                            </option>
                                                            <option value="tuesday"
                                                                {{ $store_disbursement_week_start == 'tuesday' ? 'selected' : '' }}>
                                                                {{ translate('messages.tuesday') }}
                                                            </option>
                                                            <option value="wednesday"
                                                                {{ $store_disbursement_week_start == 'wednesday' ? 'selected' : '' }}>
                                                                {{ translate('messages.wednesday') }}
                                                            </option>
                                                            <option value="thursday"
                                                                {{ $store_disbursement_week_start == 'thursday' ? 'selected' : '' }}>
                                                                {{ translate('messages.thursday') }}
                                                            </option>
                                                            <option value="friday"
                                                                {{ $store_disbursement_week_start == 'friday' ? 'selected' : '' }}>
                                                                {{ translate('messages.friday') }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class='col-sm-6'>
                                                    @php($store_disbursement_create_time = Helpers::get_business_settings('store_disbursement_create_time') ?? '')
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="store_disbursement_create_time"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Create_Time') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Define_when_the_new_disbursement_request_will_be_generated_automatically.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <input type="time" id="store_disbursement_create_time"
                                                            placeholder="{{ translate('Ex:_7') }}"
                                                            class="form-control h--45px"
                                                            name="store_disbursement_create_time"
                                                            value="{{ $store_disbursement_create_time }}" required>
                                                    </div>
                                                </div>
                                                <div class='col-sm-6'>
                                                    @php($store_disbursement_min_amount = Helpers::get_business_settings('store_disbursement_min_amount') ?? '')
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="store_disbursement_min_amount"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Minimum_Amount') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Enter_the_minimum_amount_to_be_eligible_for_generating_an_auto-disbursement_request.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <input id="store_disbursement_min_amount" type="number"
                                                            placeholder="{{ translate('Ex:_100') }}"
                                                            class="form-control h--45px" min="1"
                                                            name="store_disbursement_min_amount"
                                                            value="{{ $store_disbursement_min_amount }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    @php($store_disbursement_waiting_time = Helpers::get_business_settings('store_disbursement_waiting_time') ?? '')
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="store_disbursement_waiting_time"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Days_needed_to_complete_disbursement') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Enter_the_number_of_days_in_which_the_disbursement_will_be_completed.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <input id="store_disbursement_waiting_time" type="number"
                                                            placeholder="{{ translate('Ex:_7') }}" min="1"
                                                            class="form-control h--45px"
                                                            name="store_disbursement_waiting_time"
                                                            value="{{ $store_disbursement_waiting_time }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="mb-20">
                                            <h4 class="mb-1">
                                                {{ translate('Delivery Man Disbursement Request') }}
                                            </h4>
                                            <p class="mb-0 fs-12">
                                                {{ translate('Set parameters for when delivery drivers can request disbursements.') }}
                                            </p>
                                        </div>
                                        @php($dm_disbursement_time_period = Helpers::get_business_settings('dm_disbursement_time_period') ?? '')
                                        <div class="bg-light2 rounded p-xxl-20 p-3">
                                            <div class="row">
                                                <div class='{{ $dm_disbursement_time_period == 'weekly' ? 'col-sm-6' : 'col-sm-6' }}'
                                                    id="dm_time_period_section">
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="dm_disbursement_time_period"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Create_Disbursements') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Choose_how_the_disbursement_request_will_be_generated:_Monthly,_Weekly_or_Daily.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <select name="dm_disbursement_time_period"
                                                            id="dm_disbursement_time_period" class="form-control"
                                                            required>
                                                            <option value="daily"
                                                                {{ $dm_disbursement_time_period == 'daily' ? 'selected' : '' }}>
                                                                {{ translate('messages.daily') }}
                                                            </option>
                                                            <option value="weekly"
                                                                {{ $dm_disbursement_time_period == 'weekly' ? 'selected' : '' }}>
                                                                {{ translate('messages.weekly') }}
                                                            </option>
                                                            <option value="monthly"
                                                                {{ $dm_disbursement_time_period == 'monthly' ? 'selected' : '' }}>
                                                                {{ translate('messages.monthly') }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                @php($dm_disbursement_week_start = Helpers::get_business_settings('dm_disbursement_week_start') ?? 'saturday')
                                                <div class='col-sm-6 {{ $dm_disbursement_time_period == 'weekly' ? '' : 'd-none' }}'
                                                    id="dm_week_day_section">
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="dm_disbursement_week_start"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Week_Start') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Choose_when_the_week_starts_for_the_new_disbursement_request._This_section_will_only_appear_when_weekly_disbursement_is_selected.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <select name="dm_disbursement_week_start"
                                                            id="dm_disbursement_week_start" class="form-control" required>
                                                            <option value="saturday"
                                                                {{ $dm_disbursement_week_start == 'saturday' ? 'selected' : '' }}>
                                                                {{ translate('messages.saturday') }}
                                                            </option>
                                                            <option value="sunday"
                                                                {{ $dm_disbursement_week_start == 'sunday' ? 'selected' : '' }}>
                                                                {{ translate('messages.sunday') }}
                                                            </option>
                                                            <option value="monday"
                                                                {{ $dm_disbursement_week_start == 'monday' ? 'selected' : '' }}>
                                                                {{ translate('messages.monday') }}
                                                            </option>
                                                            <option value="tuesday"
                                                                {{ $dm_disbursement_week_start == 'tuesday' ? 'selected' : '' }}>
                                                                {{ translate('messages.tuesday') }}
                                                            </option>
                                                            <option value="wednesday"
                                                                {{ $dm_disbursement_week_start == 'wednesday' ? 'selected' : '' }}>
                                                                {{ translate('messages.wednesday') }}
                                                            </option>
                                                            <option value="thursday"
                                                                {{ $dm_disbursement_week_start == 'thursday' ? 'selected' : '' }}>
                                                                {{ translate('messages.thursday') }}
                                                            </option>
                                                            <option value="friday"
                                                                {{ $dm_disbursement_week_start == 'friday' ? 'selected' : '' }}>
                                                                {{ translate('messages.friday') }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class='col-sm-6'>
                                                    @php($dm_disbursement_create_time = Helpers::get_business_settings('dm_disbursement_create_time') ?? 1)
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="dm_disbursement_create_time"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Create_Time') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Define_when_the_new_disbursement_request_will_be_generated_automatically.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <input id="dm_disbursement_create_time" type="time"
                                                            placeholder="{{ translate('Ex:_7') }}"
                                                            class="form-control h--45px"
                                                            name="dm_disbursement_create_time"
                                                            value="{{ $dm_disbursement_create_time }}" required>
                                                    </div>
                                                </div>
                                                <div class='col-sm-6'>
                                                    @php($dm_disbursement_min_amount = Helpers::get_business_settings('dm_disbursement_min_amount') ?? 'saturday')
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="dm_disbursement_min_amount"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Minimum_Amount') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Enter_the_minimum_amount_to_be_eligible_for_generating_an_auto-disbursement_request.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <input id="dm_disbursement_min_amount" type="number"
                                                            placeholder="{{ translate('Ex:_100') }}"
                                                            class="form-control h--45px" min="1"
                                                            name="dm_disbursement_min_amount"
                                                            value="{{ $dm_disbursement_min_amount }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    @php($dm_disbursement_waiting_time = Helpers::get_business_settings('dm_disbursement_waiting_time') ?? '')
                                                    <div class="form-group lang_form default-form">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <label for="dm_disbursement_waiting_time"
                                                                class="form-label text-capitalize m-0">
                                                                {{ translate('Days_needed_to_complete_disbursement') }}
                                                                <span class="input-label-secondary text--title"
                                                                    data-toggle="tooltip" data-placement="right"
                                                                    data-original-title="{{ translate('Enter_the_number_of_days_in_which_the_disbursement_will_be_completed.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                  <span class="text-danger">*</span>
                                                            </label>
                                                        </div>
                                                        <input id="dm_disbursement_waiting_time" type="number"
                                                            min="1" placeholder="{{ translate('Ex:_7') }}"
                                                            class="form-control h--45px"
                                                            name="dm_disbursement_waiting_time"
                                                            value="{{ $dm_disbursement_waiting_time }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="mt-0 footer-sticky">
                        <div class="container-fluid">
                            <div class="btn--container justify-content-end py-3">
                                <button type="reset" id="reset_btn"
                                    class="btn min-w-120px btn--reset location-reload">{{ translate('messages.reset') }}</button>
                                <button type="submit" id="submit" class="btn min-w-120px btn--primary call-demo"><i
                                        class="tio-save"></i> {{ translate('messages.save_information') }}</button>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </form>

        <?php
            $disbursementCronLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
            $disbursementSchedulerSupervisor = "[program:6ammart-scheduler]\n"
                . "process_name=%(program_name)s\n"
                . "command=php " . base_path('artisan') . " schedule:work\n"
                . "autostart=true\n"
                . "autorestart=true\n"
                . "user=www-data\n"
                . "numprocs=1\n"
                . "redirect_stderr=true\n"
                . "stdout_logfile=" . storage_path('logs/scheduler.log') . "\n"
                . "stopwaitsecs=60";
        ?>

        <div class="modal" id="disbursementSchedulerModal" tabindex="-1" role="dialog" aria-labelledby="disbursementSchedulerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="disbursementSchedulerModalLabel">{{ translate('Disbursement Scheduler Dependency') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="fs-13 mb-3">
                            {{ translate('When Automated Request is selected, Laravel\'s scheduler runs dm:disbursement and store:disbursement based on the time period (daily / weekly / monthly) and create-time configured above. Pick ONE launcher for the scheduler.') }}
                        </p>

                        <div class="bg-light rounded p-3 mb-3">
                            <h6 class="mb-2">{{ translate('Option 1 — Cron drives the scheduler') }}</h6>
                            <p class="fs-12 mb-2">
                                {{ translate('Add this single line to your server crontab. Cron will trigger schedule:run every minute and Laravel decides which scheduled commands fire.') }}
                            </p>
                            <div class="input--group input-group">
                                <input type="text" value="{{ $disbursementCronLine }}" class="form-control" id="disbursementCronCommand" readonly>
                                <button type="button" class="btn btn-primary disbursementCronCopy">{{ translate('Copy') }}</button>
                            </div>
                        </div>

                        <div class="bg-light rounded p-3 mb-0">
                            <h6 class="mb-2">{{ translate('Option 2 — Supervisor drives the scheduler (no cron)') }}</h6>
                            <p class="fs-12 mb-2">
                                {{ translate('Use this if you can\'t install a cron entry (Docker, some shared hosts). Supervisor keeps schedule:work alive; it internally invokes schedule:run every 60 seconds.') }}
                            </p>
                            <textarea class="form-control mb-2" id="disbursementSchedulerSupervisorBlock" rows="10" readonly>{{ $disbursementSchedulerSupervisor }}</textarea>
                            <button type="button" class="btn btn-primary disbursementSchedulerSupervisorCopy">{{ translate('Copy') }}</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="global_guideline_offcanvas"
        class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
        <!-- Guidline Offcanvas -->
        {{-- <div class="global_guideline_offcanvas" tabindex="-1" id="offcanvasSetupGuide" aria-labelledby="offcanvasSetupGuideLabel"
            style="--offcanvas-width: 500px"> --}}
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <h3 class="mb-0">{{ translate('messages.Disbursement Guideline') }}</h3>
                <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                    aria-label="Close">&times;</button>
            </div>

            <div class="custom-offcanvas-body offcanvas-height-100 py-3 px-md-4 px-3">
                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#disbursement_setup"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('messages.Disbursement Setup') }}</span>
                        </button>
                        <a href="#disbursement_setup_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('messages.Let’s Setup') }}</a>
                    </div>
                    <div class="collapse mt-3 show" id="disbursement_setup">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Disbursement Setup') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.The Disbursement Setup feature allows the admin to manage the payout of earnings to vendors and delivery personnel. It ensures timely and accurate settlements based on completed orders, commissions, and deductions.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#disbursement_request_type"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('messages.Disbursement Request Type') }}</span>
                        </button>
                        <a href="#disbursement_request_type_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('messages.Let’s Setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="disbursement_request_type">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Disbursement Request Type') }}</h5>
                                <p class="fs-12 mb-3">
                                    {{ translate('messages.The system supports two types of disbursement requests for vendors and delivery personnel: Manual and Automated. These settings determine how earnings are transferred from the platform to the recipients.') }}
                                </p>
                                <ul class="mb-0 fs-12">
                                    <li class="font-semibold">
                                        {{ translate('messages.Manual Disbursement') }}
                                    </li>
                                    <p class="mb-3">
                                        {{ translate('messages.Admin reviews and approves each payout request before processing.') }}
                                    </p>
                                    <li class="font-semibold">
                                        {{ translate('messages.Automated Disbursement') }}
                                    </li>
                                    <p class="mb-3">
                                        {{ translate('messages.The system automatically processes payouts according to predefined schedules (daily, weekly, or monthly).') }}
                                    </p>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#disbursement_request_setup"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('messages.Disbursement Request Setup') }}</span>
                        </button>
                        <a href="#disbursement_request_setup_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('messages.Let’s Setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="disbursement_request_setup">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Disbursement Request Setup') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.This feature allows the admin to configure how and when earnings are disbursed to vendors and delivery personnel. Proper setup ensures timely payouts, automated processing, and compliance with operational rules.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection
@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/disbursement.js') }}"></script>
    <script>
        "use strict";
        $(document).on('ready', function() {
            @if ($disbursement_type == 'manual')
                $('.automated_disbursement_section').hide();
            @endif

            $('.offcanvas-close-btn').on('click', function(e) {
                e.preventDefault();
                $('.global_guideline_offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
                $('html, body').animate({
                    scrollTop: $($(this).attr('href')).offset().top - 100
                }, 500);
            });

            function copyFromElement(id) {
                var el = document.getElementById(id);
                el.select();
                el.setSelectionRange(0, 99999);
                try {
                    document.execCommand("copy");
                    toastr.success('{{ translate('Copied to clipboard!') }}');
                } catch (err) {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(el.value).then(function () {
                            toastr.success('{{ translate('Copied to clipboard!') }}');
                        });
                    }
                }
            }
            $(document).on('click', '.disbursementCronCopy', function (e) {
                e.preventDefault();
                copyFromElement('disbursementCronCommand');
            });
            $(document).on('click', '.disbursementSchedulerSupervisorCopy', function (e) {
                e.preventDefault();
                copyFromElement('disbursementSchedulerSupervisorBlock');
            });
        });
    </script>
@endpush
