@extends('layouts.admin.app')

@section('title', translate('FCM Settings'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/firebase.png') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('messages.firebase_push_notification_setup') }}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <?php
        $mod_type = 'service';
        $serviceFcmFields = [
            'booking_place_message' => 'Booking Placed',
            'booking_accepted_message' => 'Booking Accepted',
            'booking_ongoing_message' => 'Booking Ongoing',
            'serviceman_assign_message' => 'Serviceman Assigned',
            'booking_complete_message' => 'Booking Completed',
            'booking_cancel_message' => 'Booking Canceled',
            'schedule_booking_time_change_message' => 'Schedule Booking Time Change',
            'booking_service_location_change_message' => 'Booking Service Location Change',
            'customized_booking_request_message' => 'Customized Booking Request',
            'customized_booking_delete_message' => 'Customized Booking Delete',
            'provider_bid_offer_message' => 'Provider Bid Offer',
            'provider_bid_withdraw_message' => 'Provider Bid Withdraw',
            'booking_edit_service_add_message' => 'Booking Edit Service Add',
            'booking_edit_service_remove_message' => 'Booking Edit Service Remove',
        ];
        ?>
        <div class="card">
            <div class="card-header card-header-shadow pb-0">
                <div class="d-flex flex-wrap justify-content-between w-100 row-gap-1">
                    <ul class="nav nav-tabs nav--tabs border-0 gap-2">
                        <li class="nav-item mr-2 mr-md-4">
                            <a href="{{ route('admin.business-settings.fcm-index') }}"
                                class="nav-link pb-2 px-0 pb-sm-3 active" data-slide="1">
                                <img src="{{ asset('/public/assets/admin/img/notify.png') }}" alt="">
                                <span>{{ translate('Push Notification') }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.business-settings.fcm-config') }}" class="nav-link pb-2 px-0 pb-sm-3"
                                data-slide="2">
                                <img src="{{ asset('/public/assets/admin/img/firebase2.png') }}" alt="">
                                <span>{{ translate('Firebase Configuration') }}</span>
                            </a>
                        </li>
                    </ul>
                    <div class="py-1">
                        <div class="tab--content">
                            <div class="item show text--primary-2 d-flex flex-wrap align-items-center" type="button"
                                data-toggle="modal" data-target="#push-notify-modal">
                                <strong class="mr-2">{{ translate('Read Documentation') }}</strong>
                                <div class="blinkings">
                                    <i class="tio-info-outined"></i>
                                </div>
                            </div>
                            <div class="item text--primary-2 d-flex flex-wrap align-items-center" type="button"
                                data-toggle="modal" data-target="#firebase-modal">
                                <strong class="mr-2">{{ translate('Where to get this information') }}</strong>
                                <div class="blinkings">
                                    <i class="tio-info-outined"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="push-notify">
                        @php($language = \App\Models\BusinessSetting::where('key', 'language')->first())
                        @php($language = $language->value ?? null)
                        @php($defaultLang = 'en')
                        <div class="row justify-content-between">
                            <div class="col-sm-auto mb-5">
                                @if ($language)
                                    @php($defaultLang = json_decode($language)[0])
                                    <ul class="nav nav-tabs border-0">
                                        @foreach (json_decode($language) as $lang)
                                            <li class="nav-item">
                                                <a class="nav-link lang_link {{ $lang == $defaultLang ? 'active' : '' }}"
                                                    href="#"
                                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="col-sm-auto mb-5">
                                <select name="module_type" class="form-control js-select2-custom set-filter"
                                    data-url="{{ url()->full() }}" data-filter="module_type"
                                    title="{{ translate('messages.select_modules') }}">
                                    @foreach (config('module.module_type') as $module)
                                        {{-- Hide addon-based module types whose addon isn't published --}}
                                        @continue($module === 'rental' && !addon_published_status('Rental'))
                                        @continue($module === 'ride-share' && !addon_published_status('RideShare'))
                                        @continue($module === 'service' && !addon_published_status('Service'))
                                        <option value="{{ $module }}" {{ $mod_type == $module ? 'selected' : '' }}>
                                            {{ ucfirst(translate($module)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small>{{ translate('*Select Module Here') }}</small>
                            </div>
                        </div>
                        <form action="{{ route('admin.business-settings.update-fcm-messages-service') }}" method="post">
                            @csrf

                            @if ($language)
                                @php($defaultLang = json_decode($language)[0])
                                @foreach (json_decode($language) as $lang_key => $lang)
                                    <div class="{{ $lang != $defaultLang ? 'd-none' : '' }} lang_form"
                                        id="{{ $lang }}-form">
                                        <div class="row">

                                            {{-- Service booking notification message fields --}}
                                            @foreach ($serviceFcmFields as $fkey => $flabel)
                                                @php($fdata = \App\Models\NotificationMessage::with('translations')->where('module_type', $mod_type)->where('key', $fkey)->first())
                                                <?php
                                                $ftranslate = null;
                                                if ($fdata && isset($fdata->translations) && count($fdata->translations)) {
                                                    foreach ($fdata->translations as $t) {
                                                        if ($t->locale == $lang && $t->key == $fkey) {
                                                            $ftranslate = $t->value;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="form-group">
                                                        <div class="d-flex flex-wrap justify-content-between mb-2">
                                                            <span class="d-block form-label">
                                                                {{ translate('messages.' . $flabel) }} ({{ strtoupper($lang) }})
                                                            </span>
                                                            @if ($lang == 'en')
                                                                <label class="switch--custom-label toggle-switch d-flex align-items-center mb-0"
                                                                    for="{{ $fkey }}_status">
                                                                    <input type="checkbox" data-id="{{ $fkey }}_status"
                                                                        data-type="toggle"
                                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/pending-order-on.png') }}"
                                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/pending-order-off.png') }}"
                                                                        data-title-on="{{ translate('By Turning ON Notification Message For') }} <strong>{{ translate('messages.' . $flabel) }}</strong>"
                                                                        data-title-off="{{ translate('By Turning OFF Notification Message For') }} <strong>{{ translate('messages.' . $flabel) }}</strong>"
                                                                        data-text-on="<p>{{ translate('User will receive a proper notification message for this event') }}</p>"
                                                                        data-text-off="<p>{{ translate('User will not receive any notification message for this event') }}</p>"
                                                                        class="status toggle-switch-input add-required-attribute dynamic-checkbox-toggle"
                                                                        name="{{ $fkey }}_status"
                                                                        data-textarea-name="{{ $fkey }}" value="1"
                                                                        id="{{ $fkey }}_status"
                                                                        {{ $fdata ? ($fdata['status'] == 1 ? 'checked' : '') : '' }}>
                                                                    <span class="toggle-switch-label">
                                                                        <span class="toggle-switch-indicator"></span>
                                                                    </span>
                                                                </label>
                                                            @endif
                                                        </div>
                                                        <textarea name="{{ $fkey }}[]" placeholder="{{ translate('Write your message') }}"
                                                            class="form-control {{ $fkey }}"
                                                            @if ($lang == 'en') {{ $fdata ? ($fdata['status'] == 1 ? 'required' : '') : '' }} @endif>{!! $ftranslate !== null ? $ftranslate : ($fdata ? $fdata['message'] : '') !!}</textarea>
                                                    </div>
                                                </div>
                                            @endforeach

                                            {{-- Subscription notifications (global, module_type = null) --}}
                                            @if (\App\CentralLogics\Helpers::get_business_settings('pro_member_status') == 1)
                                            @php($ser = \App\Models\NotificationMessage::with('translations')->where('key', 'subscription_expire_reminder')->first())
                                            @php($sa = \App\Models\NotificationMessage::with('translations')->where('key', 'subscription_activated')->first())
                                            @php($se = \App\Models\NotificationMessage::with('translations')->where('key', 'subscription_expired')->first())
                                            @php($sc = \App\Models\NotificationMessage::with('translations')->where('key', 'subscription_canceled')->first())
                                            <?php
                                                $translate_ser = [];
                                                if (isset($ser->translations) && count($ser->translations)) {
                                                    foreach ($ser->translations as $t) {
                                                        if ($t->locale == $lang && $t->key == 'subscription_expire_reminder') {
                                                            $translate_ser[$lang]['message'] = $t->value;
                                                        }
                                                    }
                                                }
                                                $translate_sa = [];
                                                if (isset($sa->translations) && count($sa->translations)) {
                                                    foreach ($sa->translations as $t) {
                                                        if ($t->locale == $lang && $t->key == 'subscription_activated') {
                                                            $translate_sa[$lang]['message'] = $t->value;
                                                        }
                                                    }
                                                }
                                                $translate_se = [];
                                                if (isset($se->translations) && count($se->translations)) {
                                                    foreach ($se->translations as $t) {
                                                        if ($t->locale == $lang && $t->key == 'subscription_expired') {
                                                            $translate_se[$lang]['message'] = $t->value;
                                                        }
                                                    }
                                                }
                                                $translate_sc = [];
                                                if (isset($sc->translations) && count($sc->translations)) {
                                                    foreach ($sc->translations as $t) {
                                                        if ($t->locale == $lang && $t->key == 'subscription_canceled') {
                                                            $translate_sc[$lang]['message'] = $t->value;
                                                        }
                                                    }
                                                }
                                            ?>
                                            <div class="col-12 mt-4" id="subscription-notification-{{ $lang }}">
                                                <div class="bg-light rounded p-3 p-xxl-20">
                                                    <h4 class="mb-3">{{ translate('messages.Subscription notification') }} ({{ strtoupper($lang) }})</h4>
                                                    <div class="row g-3">
                                                        {{-- Subscription Expire Reminder --}}
                                                        <div class="col-12">
                                                            <div class="bg-white rounded p-3 p-xxl-20">
                                                                <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap mb-3">
                                                                    <div>
                                                                        <h4 class="mb-1">{{ translate('messages.Subscription Expire Reminder') }}</h4>
                                                                        <p class="fs-12 m-0">{{ translate('messages.Configure the messages of automatic reminder for customers before expire their subscription') }}</p>
                                                                    </div>
                                                                    @if ($lang == 'en')
                                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                        @if ($subscription_reminder_enabled)
                                                                            <button type="button" class="btn btn--primary" data-toggle="modal" data-target="#subscriptionSchedulerModal">{{ translate('messages.Check_Dependencies') }}</button>
                                                                        @endif
                                                                        <label class="switch--custom-label toggle-switch d-flex align-items-center mb-0" for="subscription_expire_reminder_status">
                                                                            <input type="checkbox"
                                                                                   data-id="subscription_expire_reminder_status"
                                                                                   data-type="toggle"
                                                                                   data-image-on="{{ asset('/public/assets/admin/img/modal/schedule-on.png') }}"
                                                                                   data-image-off="{{ asset('/public/assets/admin/img/modal/schedule-off.png') }}"
                                                                                   data-title-on="{{ translate('By Turning ON Notification Message For') }} <strong>{{ translate('messages.Subscription Expire Reminder') }}</strong>"
                                                                                   data-title-off="{{ translate('By Turning OFF Notification Message For') }} <strong>{{ translate('messages.Subscription Expire Reminder') }}</strong>"
                                                                                   data-text-on="<p>{{ translate('Customer will receive a proper notification message for this event') }}</p>"
                                                                                   data-text-off="<p>{{ translate('Customer will not receive any notification message for this event') }}</p>"
                                                                                   class="status toggle-switch-input dynamic-checkbox-toggle"
                                                                                   name="subscription_expire_reminder_status"
                                                                                   data-textarea-name="subscription_expire_reminder"
                                                                                   value="1"
                                                                                   id="subscription_expire_reminder_status" {{ $ser ? ($ser['status'] == 1 ? 'checked' : '') : '' }}>
                                                                            <span class="toggle-switch-label">
                                                                                <span class="toggle-switch-indicator"></span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                                <div class="row g-3 align-items-end">
                                                                    <div class="col-lg-6">
                                                                        <div class="form-group mb-0">
                                                                            <label class="d-block form-label">
                                                                                {{ translate('messages.Expire Reminder Message') }} ({{ strtoupper($lang) }})
                                                                            </label>
                                                                            <textarea name="subscription_expire_reminder[]" class="form-control min-h-45px subscription_expire_reminder" rows="1"
                                                                                placeholder="{{ translate('messages.Your subscription expires in 2 days. Please renew.') }}">{!! (isset($translate_ser) && isset($translate_ser[$lang])) ? $translate_ser[$lang]['message'] : '' !!}</textarea>
                                                                        </div>
                                                                    </div>
                                                                    @if ($lang == 'en')
                                                                    <div class="col-lg-6">
                                                                        <div class="form-group mb-0">
                                                                            <label class="d-block form-label">
                                                                                {{ translate('messages.Send reminder before Expire') }}
                                                                            </label>
                                                                            <div class="d-flex border rounded overflow-hidden">
                                                                                <input type="number" name="subscription_reminder_before_time" class="form-control rounded-0 border-0"
                                                                                    value="{{ $subscription_reminder_before_time ?? 0 }}" min="0" placeholder="{{ translate('messages.Ex: 3') }}">
                                                                                <select name="subscription_reminder_before"
                                                                                    class="custom-select rounded-0 border-0 bg-modal-btn form-control w-90px">
                                                                                    <option value="hour" {{ ($subscription_reminder_before ?? 'days') == 'hour' ? 'selected' : '' }}>{{ translate('messages.Hour') }}</option>
                                                                                    <option value="days" {{ ($subscription_reminder_before ?? 'days') == 'days' ? 'selected' : '' }}>{{ translate('messages.Days') }}</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Subscription Activated --}}
                                                        <div class="col-lg-6">
                                                            <div class="form-group mb-0">
                                                                <div class="d-flex flex-wrap justify-content-between mb-2">
                                                                    <span class="d-block form-label">
                                                                        {{ translate('messages.Subscription Activated message') }} ({{ strtoupper($lang) }})
                                                                    </span>
                                                                    @if ($lang == 'en')
                                                                    <label class="switch--custom-label toggle-switch d-flex align-items-center mb-0" for="subscription_activated_status">
                                                                        <input type="checkbox"
                                                                               data-id="subscription_activated_status"
                                                                               data-type="toggle"
                                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/crown_on.png') }}"
                                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/crown_off.png') }}"
                                                                               data-title-on="{{ translate('By Turning ON Notification Message For') }} <strong>{{ translate('messages.Subscription Activated message') }}</strong>"
                                                                               data-title-off="{{ translate('By Turning OFF Notification Message For') }} <strong>{{ translate('messages.Subscription Activated message') }}</strong>"
                                                                               data-text-on="<p>{{ translate('Customer will receive a proper notification message for this event') }}</p>"
                                                                               data-text-off="<p>{{ translate('Customer will not receive any notification message for this event') }}</p>"
                                                                               class="status toggle-switch-input dynamic-checkbox-toggle"
                                                                               name="subscription_activated_status"
                                                                               data-textarea-name="subscription_activated"
                                                                               value="1"
                                                                               id="subscription_activated_status" {{ $sa ? ($sa['status'] == 1 ? 'checked' : '') : '' }}>
                                                                        <span class="toggle-switch-label">
                                                                            <span class="toggle-switch-indicator"></span>
                                                                        </span>
                                                                    </label>
                                                                    @endif
                                                                </div>
                                                                <textarea name="subscription_activated[]" placeholder="{{ translate('messages.Ex : Subscription activated successfully') }}" class="form-control subscription_activated">{!! (isset($translate_sa) && isset($translate_sa[$lang])) ? $translate_sa[$lang]['message'] : '' !!}</textarea>
                                                            </div>
                                                        </div>

                                                        {{-- Subscription Expired --}}
                                                        <div class="col-lg-6">
                                                            <div class="form-group mb-0">
                                                                <div class="d-flex flex-wrap justify-content-between mb-2">
                                                                    <span class="d-block form-label">
                                                                        {{ translate('messages.Subscription Expire Message') }} ({{ strtoupper($lang) }})
                                                                    </span>
                                                                    @if ($lang == 'en')
                                                                    <label class="switch--custom-label toggle-switch d-flex align-items-center mb-0" for="subscription_expired_status">
                                                                        <input type="checkbox"
                                                                               data-id="subscription_expired_status"
                                                                               data-type="toggle"
                                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/status-on.png') }}"
                                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/status-off.png') }}"
                                                                               data-title-on="{{ translate('By Turning ON Notification Message For') }} <strong>{{ translate('messages.Subscription Expire Message') }}</strong>"
                                                                               data-title-off="{{ translate('By Turning OFF Notification Message For') }} <strong>{{ translate('messages.Subscription Expire Message') }}</strong>"
                                                                               data-text-on="<p>{{ translate('Customer will receive a proper notification message for this event') }}</p>"
                                                                               data-text-off="<p>{{ translate('Customer will not receive any notification message for this event') }}</p>"
                                                                               class="status toggle-switch-input dynamic-checkbox-toggle"
                                                                               name="subscription_expired_status"
                                                                               data-textarea-name="subscription_expired"
                                                                               value="1"
                                                                               id="subscription_expired_status" {{ $se ? ($se['status'] == 1 ? 'checked' : '') : '' }}>
                                                                        <span class="toggle-switch-label">
                                                                            <span class="toggle-switch-indicator"></span>
                                                                        </span>
                                                                    </label>
                                                                    @endif
                                                                </div>
                                                                <textarea name="subscription_expired[]" placeholder="{{ translate('messages.Ex : Your Subscription has been expired ') }}" class="form-control subscription_expired">{!! (isset($translate_se) && isset($translate_se[$lang])) ? $translate_se[$lang]['message'] : '' !!}</textarea>
                                                            </div>
                                                        </div>

                                                        {{-- Subscription Canceled --}}
                                                        <div class="col-lg-6">
                                                            <div class="form-group mb-0">
                                                                <div class="d-flex flex-wrap justify-content-between mb-2">
                                                                    <span class="d-block form-label">
                                                                        {{ translate('messages.Subscription Canceled message') }} ({{ strtoupper($lang) }})
                                                                    </span>
                                                                    @if ($lang == 'en')
                                                                    <label class="switch--custom-label toggle-switch d-flex align-items-center mb-0" for="subscription_canceled_status">
                                                                        <input type="checkbox"
                                                                               data-id="subscription_canceled_status"
                                                                               data-type="toggle"
                                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/status-on.png') }}"
                                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/status-off.png') }}"
                                                                               data-title-on="{{ translate('By Turning ON Notification Message For') }} <strong>{{ translate('messages.Subscription Canceled message') }}</strong>"
                                                                               data-title-off="{{ translate('By Turning OFF Notification Message For') }} <strong>{{ translate('messages.Subscription Canceled message') }}</strong>"
                                                                               data-text-on="<p>{{ translate('Customer will receive a proper notification message for this event') }}</p>"
                                                                               data-text-off="<p>{{ translate('Customer will not receive any notification message for this event') }}</p>"
                                                                               class="status toggle-switch-input dynamic-checkbox-toggle"
                                                                               name="subscription_canceled_status"
                                                                               data-textarea-name="subscription_canceled"
                                                                               value="1"
                                                                               id="subscription_canceled_status" {{ $sc ? ($sc['status'] == 1 ? 'checked' : '') : '' }}>
                                                                        <span class="toggle-switch-label">
                                                                            <span class="toggle-switch-indicator"></span>
                                                                        </span>
                                                                    </label>
                                                                    @endif
                                                                </div>
                                                                <textarea name="subscription_canceled[]" placeholder="{{ translate('messages.Ex : Your Subscription has been canceled') }}" class="form-control subscription_canceled">{!! (isset($translate_sc) && isset($translate_sc[$lang])) ? $translate_sc[$lang]['message'] : '' !!}</textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                            <input type="hidden" name="module_type" value="{{ $mod_type }}">
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                            <div class="btn--container justify-content-end mt-4">
                                <button type="reset" class="btn btn--reset">{{ translate('messages.reset') }}</button>
                                <button type="submit"
                                    class="btn btn--primary">{{ translate('messages.submit') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Firebase Modal -->
        <div class="modal fade" id="push-notify-modal">
            <div class="modal-dialog status-warning-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true" class="tio-clear"></span>
                        </button>
                    </div>
                    <div class="modal-body pb-5 pt-0">
                        <div class="single-item-slider owl-carousel">
                            <div class="item">
                                <div class="mb-20">
                                    <div class="text-center">
                                        <img src="{{ asset('/public/assets/admin/img/email-templates/3.png') }}"
                                            alt="" class="mb-20">
                                        <h5 class="modal-title">
                                            {{ translate('Write_a_message_in_the_Notification_Body') }}</h5>
                                    </div>
                                    <p>
                                        {{ translate('you_can_add_your_message_using_placeholders_to_include_dynamic_content._Here_are_some_examples_of_placeholders_you_can_use:') }}
                                    </p>
                                    <ul>
                                        <li>
                                            {userName}: {{ translate('the_name_of_the_user.') }}
                                        </li>
                                        <li>
                                            {providerName}: {{ translate('the_name_of_the_provider.') }}
                                        </li>
                                        <li>
                                            {bookingId}: {{ translate('the_booking_id.') }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="item">
                                <div class="mb-20">
                                    <div class="text-center">
                                        <img src="{{ asset('/public/assets/admin/img/firebase/slide-4.png') }}"
                                            alt="" class="mb-20">
                                        <h5 class="modal-title">
                                            {{ translate('Please Visit the Docs to Set FCM on Mobile Apps') }}</h5>
                                    </div>
                                    <div class="text-center">
                                        <p>
                                            {{ translate('Please check the documentation below for detailed instructions on setting up your mobile app to receive Firebase Cloud Messaging (FCM) notifications.') }}
                                        </p>
                                        <a href="https://docs.6amtech.com/docs-six-am-mart/mobile-apps/mandatory-setup"
                                            target="_blank">{{ translate('Click Here') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center">
                            <div class="slide-counter"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @if ($subscription_reminder_enabled)
        <?php
            $subscriptionCronLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
            $subscriptionSchedulerSupervisor = "[program:6ammart-scheduler]\n"
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

        <div class="modal" id="subscriptionSchedulerModal" tabindex="-1" role="dialog" aria-labelledby="subscriptionSchedulerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="subscriptionSchedulerModalLabel">{{ translate('Subscription Reminder Scheduler Dependency') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="fs-13 mb-3">
                            {{ translate('Laravel\'s scheduler runs customer-subscription:reminder based on the unit you selected (minute / hour / day). The command finds Pro customers whose subscription will expire within the configured window and sends them a push notification. Pick ONE launcher for the scheduler.') }}
                        </p>
                        <p class="fs-12 mb-3">
                            <span class="badge badge-soft-primary">
                                {{ translate('messages.Send reminder before Expire') }}: {{ $subscription_reminder_before_time }}
                                @switch($subscription_reminder_before)
                                    @case('hour') {{ translate('messages.Hour') }} @break
                                    @case('min')  {{ translate('messages.Minute') }} @break
                                    @default      {{ translate('messages.Days') }}
                                @endswitch
                            </span>
                        </p>

                        <div class="bg-light rounded p-3 mb-3">
                            <h6 class="mb-2">{{ translate('Option 1 — Cron drives the scheduler') }}</h6>
                            <p class="fs-12 mb-2">
                                {{ translate('Add this single line to your server crontab. Cron will trigger schedule:run every minute and Laravel decides when the subscription reminder fires.') }}
                            </p>
                            <div class="input--group input-group">
                                <input type="text" value="{{ $subscriptionCronLine }}" class="form-control" id="subscriptionCronCommand" readonly>
                                <button type="button" class="btn btn-primary subscriptionCronCopy">{{ translate('Copy') }}</button>
                            </div>
                        </div>

                        <div class="bg-light rounded p-3 mb-0">
                            <h6 class="mb-2">{{ translate('Option 2 — Supervisor drives the scheduler (no cron)') }}</h6>
                            <p class="fs-12 mb-2">
                                {{ translate('Use this if you can\'t install a cron entry. Supervisor keeps schedule:work alive; it internally invokes schedule:run every 60 seconds.') }}
                            </p>
                            <textarea class="form-control mb-2" id="subscriptionSchedulerSupervisorBlock" rows="10" readonly>{{ $subscriptionSchedulerSupervisor }}</textarea>
                            <button type="button" class="btn btn-primary subscriptionSchedulerSupervisorCopy">{{ translate('Copy') }}</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('script_2')
    <script>
        "use strict";
        $(function () {
            function copySubscriptionEl(id) {
                var el = document.getElementById(id);
                if (!el) return;
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
            $(document).on('click', '.subscriptionCronCopy', function (e) {
                e.preventDefault();
                copySubscriptionEl('subscriptionCronCommand');
            });
            $(document).on('click', '.subscriptionSchedulerSupervisorCopy', function (e) {
                e.preventDefault();
                copySubscriptionEl('subscriptionSchedulerSupervisorBlock');
            });
        });
    </script>
@endpush
