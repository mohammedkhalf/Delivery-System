@extends('layouts.admin.app')

@section('title', translate('messages.react_ride_share_page'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header pb-0">
            <div class="d-flex flex-wrap justify-content-between">
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/landing.png') }}" class="w--20" alt="">
                    </span>
                    <span>{{ translate('messages.react_ride_share_page') }}</span>
                </h1>
            </div>
        </div>

        <!-- <div class="mb-20 mt-2">
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                @include('admin-views.business-settings.landing-page-settings.top-menu-links.react-ride-share-page-links')
            </div>
        </div> -->

        <?php
        $role = request('role') === 'rider' ? 'rider' : 'customer';
        $keyPrefix = $role === 'rider' ? 'rider_' : '';
        $tabPrefix = $role === 'rider' ? 'rider-' : '';
        ?>

        <div class="mb-20">
            <ul class="nav nav-pills role-tabs gap-2 bg--secondary p-1 rounded-pill d-inline-flex">
                <li class="nav-item">
                    <a href="{{ url()->current() }}?role=customer"
                       class="nav-link px-4 py-2 rounded-pill {{ $role === 'customer' ? 'active text-white bg--primary' : 'text-dark' }}">
                        {{ translate('Customer') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url()->current() }}?role=rider"
                       class="nav-link px-4 py-2 rounded-pill {{ $role === 'rider' ? 'active text-white bg--primary' : 'text-dark' }}">
                        {{ translate('Rider') }}
                    </a>
                </li>
            </ul>
        </div>

        <div class="card py-3 px-xxl-4 px-3 mb-20">
            <div class="d-flex flex-sm-nowrap flex-wrap gap-3 align-items-center justify-content-between">
                <div>
                    <h3 class="mb-1">{{ translate('Hero Section') }}</h3>
                    <p class="mb-0 gray-dark fs-12">
                        {{ translate('See how your Hero Section will look to customers.') }}
                    </p>
                </div>
                <div class="max-w-300px ml-sm-auto">
                    <button type="button" class="btn btn-outline-primary py-2 fs-12 px-3 offcanvas-trigger"
                            data-target="#ride_share_hero_section">
                        <i class="tio-invisible"></i> {{ translate('Section Preview') }}
                    </button>
                </div>
            </div>
        </div>

        <?php
        $hero_section_status = \App\Models\DataSetting::where('type', 'react_ride_share_page')
            ->where('key', $keyPrefix.'hero_section_status')
            ->first();
        ?>
        <div class="card py-3 px-xxl-4 px-3 mb-15 mt-4">
            <div class="row g-3 align-items-center justify-content-between">
                <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                    <div>
                        <h3 class="mb-1">{{ translate('Show Hero Section') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('If you turn of the availability status, this section will not show in the website') }}
                        </p>
                    </div>
                </div>
                <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                    <div class="py-2 px-3 rounded d-flex justify-content-between border align-items-center w-300">
                        <h5 class="text-capitalize fw-normal mb-0">{{ translate('Status') }}</h5>

                        <form action="{{ route('admin.business-settings.statusUpdate', ['type' => 'react_ride_share_page', 'key' => $keyPrefix.'hero_section_status']) }}"
                              method="get" id="CheckboxStatus_form">
                        </form>
                        <label class="toggle-switch toggle-switch-sm" for="CheckboxStatus">
                            <input type="checkbox" data-id="CheckboxStatus" data-type="status"
                                   data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                   data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                   data-title-on="{{ translate('Do you want turn on this section ?') }}"
                                   data-title-off="{{ translate('Do you want to turn off this section ?') }}"
                                   data-text-on="<p>{{ translate('If you turn on this section will be show in react ride share page.') }}"
                                   data-text-off="<p>{{ translate('If you turn off this section will not be show in react ride share page.') }}</p>"
                                   class="toggle-switch-input status dynamic-checkbox" id="CheckboxStatus"
                                {{ $hero_section_status?->value ? 'checked' : '' }}>
                            <span class="toggle-switch-label text">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $language = \App\CentralLogics\Helpers::get_business_settings('language');
        $hero_intro_title = \App\Models\DataSetting::withoutGlobalScope('translate')->where('type', 'react_ride_share_page')->where('key', $keyPrefix.'hero_intro_title')->first();
        $hero_intro_sub_title = \App\Models\DataSetting::withoutGlobalScope('translate')->where('type', 'react_ride_share_page')->where('key', $keyPrefix.'hero_intro_sub_title')->first();
        $hero_intro_image = \App\Models\DataSetting::withoutGlobalScope('translate')->where('type', 'react_ride_share_page')->where('key', $keyPrefix.'hero_intro_image')->first();
        $hero_intro_image_url = $hero_intro_image?->value ? \App\CentralLogics\Helpers::get_full_url('ride_share_hero_section', $hero_intro_image->value, $hero_intro_image->storage[0]?->value ?? 'public', 'aspect_1') : '';
        ?>
        <div class="card mb-20">
            <form class="custom-validation"
                  action="{{ route('admin.business-settings.react-ride-share-page-settings-update', $tabPrefix.'hero-intro') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-20">
                        <h3 class="mb-1">{{ translate('Intro Section') }}</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="bg--secondary rounded h-100 p-xxl-4 p-3">
                                @if ($language)
                                    <ul class="nav nav-tabs mb-4 border-0">
                                        <li class="nav-item">
                                            <a class="nav-link lang_link active" href="#"
                                               id="default-link">{{ translate('messages.default') }}</a>
                                        </li>
                                        @foreach ($language as $lang)
                                            <li class="nav-item">
                                                <a class="nav-link lang_link" href="#"
                                                   id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="row g-3">
                                    @if ($language)
                                        <div class="col-md-12 lang_form default-form">
                                            <div class="row g-1">
                                                <div class="col-12">
                                                    <label for="hero_intro_title"
                                                           class="form-label">{{ translate('Title') }}
                                                        ({{ translate('messages.default') }})
                                                        <span class="form-label-secondary" data-toggle="tooltip"
                                                              data-placement="right"
                                                              data-original-title="{{ translate('Write_the_title_within_50_characters') }}">
                                                            <i class="tio-info color-A7A7A7"></i>
                                                        </span>
                                                        <span class="form-label-secondary text-danger"
                                                              data-toggle="tooltip" data-placement="right"
                                                              data-original-title="{{ translate('messages.Required.') }}"> *
                                                        </span>
                                                    </label>
                                                    <input id="hero_intro_title" type="text" maxlength="50"
                                                           name="{{ $keyPrefix }}hero_intro_title[]"
                                                           value="{{ $hero_intro_title?->getRawOriginal('value') ?? '' }}"
                                                           class="form-control"
                                                           placeholder="{{ translate('messages.title_here...') }}" required>
                                                    <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/50</span>
                                                </div>
                                                <div class="col-12">
                                                    <label for="hero_intro_sub_title"
                                                           class="form-label">{{ translate('Sub Title') }}
                                                        ({{ translate('messages.default') }})
                                                        <span class="form-label-secondary" data-toggle="tooltip"
                                                              data-placement="right"
                                                              data-original-title="{{ translate('Write_the_sub_title_within_150_characters') }}">
                                                            <i class="tio-info color-A7A7A7"></i>
                                                        </span>
                                                        <span class="form-label-secondary text-danger"
                                                              data-toggle="tooltip" data-placement="right"
                                                              data-original-title="{{ translate('messages.Required.') }}"> *
                                                        </span>
                                                    </label>
                                                    <textarea id="hero_intro_sub_title" rows="3" type="text" maxlength="150"
                                                              name="{{ $keyPrefix }}hero_intro_sub_title[]" class="form-control"
                                                              placeholder="{{ translate('messages.sub_title_here...') }}" required>{{ $hero_intro_sub_title?->getRawOriginal('value') ?? '' }}</textarea>
                                                    <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/150</span>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                        @foreach ($language as $lang)
                                            <?php
                                            $hero_intro_title_translate = [];
                                            $hero_intro_sub_title_translate = [];

                                            if (isset($hero_intro_title->translations) && count($hero_intro_title->translations)) {
                                                foreach ($hero_intro_title->translations as $t) {
                                                    if ($t->locale == $lang && $t->key == $keyPrefix.'hero_intro_title') {
                                                        $hero_intro_title_translate[$lang]['value'] = $t->value;
                                                    }
                                                }
                                            }

                                            if (isset($hero_intro_sub_title->translations) && count($hero_intro_sub_title->translations)) {
                                                foreach ($hero_intro_sub_title->translations as $t) {
                                                    if ($t->locale == $lang && $t->key == $keyPrefix.'hero_intro_sub_title') {
                                                        $hero_intro_sub_title_translate[$lang]['value'] = $t->value;
                                                    }
                                                }
                                            }
                                            ?>
                                            <div class="col-md-12 d-none lang_form" id="{{ $lang }}-form">
                                                <div class="row g-1">
                                                    <div class="col-12">
                                                        <label for="hero_intro_title{{ $lang }}"
                                                               class="form-label">{{ translate('Title') }}
                                                            ({{ strtoupper($lang) }})
                                                            <span class="form-label-secondary" data-toggle="tooltip"
                                                                  data-placement="right"
                                                                  data-original-title="{{ translate('Write_the_title_within_50_characters') }}">
                                                                <i class="tio-info color-A7A7A7"></i>
                                                            </span>
                                                        </label>
                                                        <input id="hero_intro_title{{ $lang }}" type="text" maxlength="50"
                                                               name="{{ $keyPrefix }}hero_intro_title[]"
                                                               value="{{ $hero_intro_title_translate[$lang]['value'] ?? '' }}"
                                                               class="form-control"
                                                               placeholder="{{ translate('messages.title_here...') }}">
                                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/50</span>
                                                    </div>
                                                    <div class="col-12">
                                                        <label for="hero_intro_sub_title{{ $lang }}"
                                                               class="form-label">{{ translate('Sub Title') }}
                                                            ({{ strtoupper($lang) }})
                                                            <span class="form-label-secondary" data-toggle="tooltip"
                                                                  data-placement="right"
                                                                  data-original-title="{{ translate('Write_the_sub_title_within_150_characters') }}">
                                                                <i class="tio-info color-A7A7A7"></i>
                                                            </span>
                                                        </label>
                                                        <textarea id="hero_intro_sub_title{{ $lang }}" rows="3" type="text"
                                                                  maxlength="150" name="{{ $keyPrefix }}hero_intro_sub_title[]"
                                                                  class="form-control"
                                                                  placeholder="{{ translate('messages.sub_title_here...') }}">{{ $hero_intro_sub_title_translate[$lang]['value'] ?? '' }}</textarea>
                                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/150</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        @endforeach
                                    @else
                                        <div class="col-12">
                                            <div class="mb-2">
                                                <label for="hero_intro_title"
                                                       class="form-label">{{ translate('Title') }}</label>
                                                <input id="hero_intro_title" maxlength="50" type="text"
                                                       name="{{ $keyPrefix }}hero_intro_title[]"
                                                       value="{{ $hero_intro_title?->getRawOriginal('value') ?? '' }}"
                                                       class="form-control"
                                                       placeholder="{{ translate('messages.title_here...') }}" required>
                                                <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/50</span>
                                            </div>
                                            <div class="mb-4">
                                                <label for="hero_intro_sub_title"
                                                       class="form-label">{{ translate('Sub Title') }}</label>
                                                <textarea id="hero_intro_sub_title" rows="3" type="text" maxlength="150"
                                                          name="{{ $keyPrefix }}hero_intro_sub_title[]" class="form-control"
                                                          placeholder="{{ translate('messages.sub_title_here...') }}" required>{{ $hero_intro_sub_title?->getRawOriginal('value') ?? '' }}</textarea>
                                                <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/150</span>
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="bg--secondary h-100 rounded p-md-4 p-3 d-center">
                                <div class="text-center w-100">
                                    <div class="mb-4">
                                        <h5 class="mb-1">{{ translate('Upload Image') }}</h5>
                                        <p class="mb-0 fs-12 gray-dark">
                                            {{ translate('Upload Hero section Image') }}
                                        </p>
                                    </div>
                                    @include('admin-views.partials._image-uploader', [
                                        'name' => $keyPrefix.'hero_intro_image',
                                        'id' => $keyPrefix.'hero_intro_image',
                                        'ratio' => '1:1',
                                        'imageExtension' => IMAGE_EXTENSION,
                                        'imageFormat' => IMAGE_FORMAT,
                                        'maxSize' => MAX_FILE_SIZE,
                                        'isRequired' => !$hero_intro_image?->value,
                                        'existingImage' => $hero_intro_image_url,
                                        'textPosition' => 'bottom',
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-20">
                        <button type="reset" class="btn btn--reset mb-2">{{ translate('Reset') }}</button>
                        <button type="submit" class="btn btn--primary mb-2">{{ translate('Save') }}</button>
                    </div>
                </div>
            </form>
        </div>

        <?php
        $numPoints = 3;
        if (!function_exists('ride_share_ordinalSuffix')) {
            function ride_share_ordinalSuffix($number): string
            {
                if (!in_array($number % 100, [11, 12, 13])) {
                    switch ($number % 10) {
                        case 1:
                            return $number . 'st';
                        case 2:
                            return $number . 'nd';
                        case 3:
                            return $number . 'rd';
                    }
                }
                return $number . 'th';
            }
        }
        ?>

        <div class="row g-3">
            @for ($i = 1; $i <= $numPoints; $i++)
                <?php
                $point_status = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $keyPrefix."hero_point_status_card_$i")->first();
                $point_title = \App\Models\DataSetting::withoutGlobalScope('translate')->where('type', 'react_ride_share_page')->where('key', $keyPrefix."hero_point_title_card_$i")->first();
                $point_image = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $keyPrefix."hero_point_image_card_$i")->first();
                $point_image_url = $point_image?->value ? \App\CentralLogics\Helpers::get_full_url('ride_share_hero_section', $point_image->value, $point_image->storage[0]?->value ?? 'public', 'aspect_1') : '';
                $cardLabel = ride_share_ordinalSuffix($i);
                ?>

                <div class="col-md-6">
                    <form class="custom-validation"
                          action="{{ route('admin.business-settings.react-ride-share-page-settings-update', $tabPrefix.'hero-point-card-' . $i) }}"
                          method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="card h-100">
                            <div class="card-header">
                                <div class="w-100 d-flex align-items-center gap-2 flex-wrap justify-content-between">
                                    <h5 class="mb-0">{{ $cardLabel }} {{ translate('Point') }}</h5>
                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between gap-4">
                                        <span class="w-auto switch--label text-nowrap fs-14 text-title">{{ translate('messages.Status') }}</span>
                                        <input type="checkbox" class="status toggle-switch-input"
                                               name="{{ $keyPrefix }}hero_point_status_card_{{ $i }}"
                                               value="1" {{ $point_status?->value ? 'checked' : '' }}>
                                        <span class="toggle-switch-label text">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="bg--secondary h-100 rounded p-4 mb-20">
                                    <div class="text-center py-1">
                                        <div class="mb-4">
                                            <h5 class="mb-1">{{ translate('Upload Image') }}</h5>
                                            <p class="mb-0 fs-12 gray-dark">{{ translate('Upload') }} {{ $cardLabel }} {{ translate('Card Image') }}</p>
                                        </div>
                                        @include('admin-views.partials._image-uploader', [
                                            'name' => $keyPrefix.'hero_point_image_card_' . $i,
                                            'id' => $keyPrefix.'hero_point_image_card_' . $i,
                                            'ratio' => '1:1',
                                            'imageExtension' => IMAGE_EXTENSION,
                                            'imageFormat' => IMAGE_FORMAT,
                                            'maxSize' => MAX_FILE_SIZE,
                                            'isRequired' => !$point_image?->value,
                                            'existingImage' => $point_image_url,
                                            'pixel' => '80 × 80',
                                            'textPosition' => 'bottom',
                                        ])
                                    </div>
                                </div>
                                <div class="bg--secondary h-100 rounded p-md-4 p-3">
                                    @if ($language)
                                        <ul class="nav nav-tabs mb-4 border-bottom">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active" href="#"
                                                   id="default-link">{{ translate('messages.default') }}</a>
                                            </li>
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link" href="#"
                                                       id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                            @endforeach
                                        </ul>

                                        <div class="lang_form default-form">
                                            <div class="row g-1">
                                                <div class="col-sm-12">
                                                    <label for="hero_point_title_card_{{ $i }}">{{ translate('Title') }}
                                                        ({{ translate('messages.default') }})
                                                        <span class="form-label-secondary" data-toggle="tooltip"
                                                              data-placement="right"
                                                              data-original-title="{{ translate('Write_the_title_within_20_characters') }}">
                                                            <i class="tio-info color-A7A7A7"></i>
                                                        </span>
                                                        <span class="form-label-secondary text-danger"
                                                              data-toggle="tooltip" data-placement="right"
                                                              data-original-title="{{ translate('messages.Required.') }}"> *
                                                        </span>
                                                    </label>
                                                    <input id="hero_point_title_card_{{ $i }}" type="text" maxlength="20"
                                                           name="{{ $keyPrefix }}hero_point_title_card_{{ $i }}[]"
                                                           class="form-control"
                                                           value="{{ $point_title?->getRawOriginal('value') ?? '' }}"
                                                           placeholder="{{ translate('messages.title_here...') }}" required>
                                                    <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/20</span>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">

                                        @foreach ($language as $lang)
                                            <?php
                                            $point_title_translate = [];
                                            if (isset($point_title->translations) && count($point_title->translations)) {
                                                foreach ($point_title->translations as $t) {
                                                    if ($t->locale == $lang && $t->key == $keyPrefix."hero_point_title_card_$i") {
                                                        $point_title_translate[$lang]['value'] = $t->value;
                                                    }
                                                }
                                            }
                                            ?>
                                            <div class="d-none lang_form" id="{{ $lang }}-form">
                                                <div class="row g-1">
                                                    <div class="col-12">
                                                        <label for="hero_point_title_card_{{ $i }}_{{ $lang }}"
                                                               class="form-label">{{ translate('Title') }}
                                                            ({{ strtoupper($lang) }})
                                                            <span class="form-label-secondary" data-toggle="tooltip"
                                                                  data-placement="right"
                                                                  data-original-title="{{ translate('Write_the_title_within_20_characters') }}">
                                                                <i class="tio-info color-A7A7A7"></i>
                                                            </span>
                                                        </label>
                                                        <input id="hero_point_title_card_{{ $i }}_{{ $lang }}"
                                                               type="text" maxlength="20"
                                                               name="{{ $keyPrefix }}hero_point_title_card_{{ $i }}[]"
                                                               value="{{ $point_title_translate[$lang]['value'] ?? '' }}"
                                                               class="form-control"
                                                               placeholder="{{ translate('messages.title_here...') }}">
                                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/20</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        @endforeach
                                    @else
                                        <div class="row g-1">
                                            <div class="col-sm-12">
                                                <label for="hero_point_title_card_{{ $i }}">{{ translate('Title') }}</label>
                                                <input id="hero_point_title_card_{{ $i }}" type="text" maxlength="20"
                                                       name="{{ $keyPrefix }}hero_point_title_card_{{ $i }}[]" class="form-control"
                                                       value="{{ $point_title?->getRawOriginal('value') ?? '' }}"
                                                       placeholder="{{ translate('messages.title_here...') }}" required>
                                                <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/20</span>
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    @endif
                                </div>
                                <div class="btn--container justify-content-end mt-20">
                                    <button type="reset" class="btn btn--reset mb-2">{{ translate('Reset') }}</button>
                                    <button type="submit" class="btn btn--primary mb-2">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @endfor
        </div>
    </div>

    <?php
    $pointDefaultIcons = [
        1 => asset('public/assets/admin/img/instant-booking.png'),
        2 => asset('public/assets/admin/img/affordable-price.png'),
        3 => asset('public/assets/admin/img/live-tracking.png'),
    ];
    $pointDefaultTitles = [
        1 => 'Instant Booking',
        2 => 'Affordable Fares',
        3 => 'Live Tracking',
    ];

    $buildPreviewRoleData = function (string $prefix) use ($numPoints, $pointDefaultIcons, $pointDefaultTitles) {
        $title = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $prefix.'hero_intro_title')->first();
        $subTitle = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $prefix.'hero_intro_sub_title')->first();
        $image = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $prefix.'hero_intro_image')->first();
        $imageUrl = $image?->value
            ? \App\CentralLogics\Helpers::get_full_url('ride_share_hero_section', $image->value, $image->storage[0]?->value ?? 'public', 'aspect_1')
            : asset('public/assets/admin/img/ride-share-hero.png');

        $points = [];
        for ($i = 1; $i <= $numPoints; $i++) {
            $ptStatus = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $prefix."hero_point_status_card_$i")->first();
            if ($ptStatus && !$ptStatus->value) {
                continue;
            }
            $ptTitle = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $prefix."hero_point_title_card_$i")->first();
            $ptImage = \App\Models\DataSetting::where('type', 'react_ride_share_page')->where('key', $prefix."hero_point_image_card_$i")->first();
            $points[] = [
                'title' => $ptTitle?->value ?: ($pointDefaultTitles[$i] ?? ''),
                'image' => $ptImage?->value
                    ? \App\CentralLogics\Helpers::get_full_url('ride_share_hero_section', $ptImage->value, $ptImage->storage[0]?->value ?? 'public', 'aspect_1')
                    : ($pointDefaultIcons[$i] ?? null),
            ];
        }

        return [
            'title' => $title?->value ?: 'Ride Anywhere, Anytime With $6amMart$',
            'sub_title' => $subTitle?->value ?: 'Experience the future of mobility. Book fast, affordable rides in just a few taps with our executive-level service and reliable driver network.',
            'image_url' => $imageUrl,
            'points' => $points,
        ];
    };

    $previewRoles = [
        'customer' => ['label' => translate("I'm a Customer"), 'data' => $buildPreviewRoleData('')],
        'rider'    => ['label' => translate("I'am a Rider"),   'data' => $buildPreviewRoleData('rider_')],
    ];
    ?>

    <div id="ride_share_hero_section" class="custom-offcanvas offcanvas-750 offcanvas-xxl-1120 d-flex flex-column justify-content-between">
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <div class="py-1">
                    <h3 class="mb-0 line--limit-1">{{ translate('messages.Hero Section Preview') }}</h3>
                </div>
                <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                        aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="custom-offcanvas-body custom-offcanvas-body-100 p-20">
                <section class="common-section-view bg-white rounded-10 my-xl-3 mx-xl-3 p-3 p-md-4">
                    <div class="rounded-10 p-3 p-md-4"
                         style="border-left:1px solid #E5E7EB; border-right:1px solid #E5E7EB;">
                        <div class="mb-3 d-flex gap-4 preview-role-tabs">
                            @foreach ($previewRoles as $previewRole => $previewMeta)
                                <button type="button"
                                        class="btn p-0 fw-semibold preview-role-tab {{ $previewRole === $role ? 'active theme-clr border-bottom border-2 pb-1' : 'text-muted' }}"
                                        data-role="{{ $previewRole }}">
                                    {{ $previewMeta['label'] }}
                                </button>
                            @endforeach
                        </div>

                        @foreach ($previewRoles as $previewRole => $previewMeta)
                            @php($previewData = $previewMeta['data'])
                            <div class="preview-role-pane {{ $previewRole === $role ? '' : 'd-none' }}"
                                 data-role-pane="{{ $previewRole }}">
                                <div class="row g-3 align-items-center">
                                    <div class="col-lg-7">
                                        <h2 class="mb-2 fw-bold" style="font-size:28px; line-height:1.2;">
                                            {!! str_replace(',', ',<br>', \App\CentralLogics\Helpers::highlightWords($previewData['title'], 'text-success')) !!}
                                        </h2>
                                        <p class="fs-12 text-muted mb-3" style="max-width:420px;">
                                            {{ $previewData['sub_title'] }}
                                        </p>
                                        <div class="d-flex flex-wrap gap-4 mb-3">
                                            @foreach ($previewData['points'] as $previewPoint)
                                                <div class="d-flex align-items-center gap-2">
                                                    @if ($previewPoint['image'])
                                                        <img width="20" height="20" src="{{ $previewPoint['image'] }}" alt="">
                                                    @endif
                                                    <span class="fs-12 fw-medium">{{ $previewPoint['title'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <div class="d-flex">
                                                <img src="{{ asset('public/assets/admin/img/av-1.png') }}"
                                                     alt="" width="28" height="28"
                                                     class="rounded-circle border border-2 border-white"
                                                     style="object-fit:cover;">
                                                <img src="{{ asset('public/assets/admin/img/av-2.png') }}"
                                                     alt="" width="28" height="28"
                                                     class="rounded-circle border border-2 border-white"
                                                     style="object-fit:cover; margin-left:-10px;">
                                                <img src="{{ asset('public/assets/admin/img/av-1.png') }}"
                                                     alt="" width="28" height="28"
                                                     class="rounded-circle border border-2 border-white"
                                                     style="object-fit:cover; margin-left:-10px;">
                                            </div>
                                            <span class="fs-12 fw-semibold text-muted">+12k</span>
                                        </div>
                                        <p class="fs-12 text-muted mb-3">
                                            {{ translate('Trusted by thousands of Users worldwide') }}
                                        </p>
                                        <button type="button" class="btn btn--primary fs-12 px-3 py-2">
                                            {{ $previewRole === 'rider' ? translate('Drive With Us') : translate('Take A Ride') }}
                                            <i class="tio-arrow-forward pl-1"></i>
                                        </button>
                                    </div>
                                    <div class="col-lg-5 text-center">
                                        <img src="{{ $previewData['image_url'] }}"
                                             alt="" class="img-fluid object-contain w-100">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.upload-file_custom').forEach(function (card) {
                var flag = card.querySelector('.image-delete-flag');
                var fileInput = card.querySelector('.single_file_input');
                var removeBtn = card.querySelector('.remove_btn');

                if (removeBtn && flag) {
                    removeBtn.addEventListener('click', function () {
                        flag.value = '1';
                    });
                }
                if (fileInput && flag) {
                    fileInput.addEventListener('change', function () {
                        flag.value = '0';
                    });
                }
            });

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('reset', function () {
                    form.querySelectorAll('.image-delete-flag').forEach(function (flag) {
                        flag.value = '0';
                    });
                });
            });

            var previewRoot = document.getElementById('ride_share_hero_section');
            if (previewRoot) {
                previewRoot.querySelectorAll('.preview-role-tab').forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        var targetRole = tab.dataset.role;
                        previewRoot.querySelectorAll('.preview-role-tab').forEach(function (t) {
                            var isActive = t.dataset.role === targetRole;
                            t.classList.toggle('active', isActive);
                            t.classList.toggle('theme-clr', isActive);
                            t.classList.toggle('border-bottom', isActive);
                            t.classList.toggle('border-2', isActive);
                            t.classList.toggle('pb-1', isActive);
                            t.classList.toggle('text-muted', !isActive);
                        });
                        previewRoot.querySelectorAll('.preview-role-pane').forEach(function (pane) {
                            pane.classList.toggle('d-none', pane.dataset.rolePane !== targetRole);
                        });
                    });
                });
            }
        });
    </script>
@endpush
