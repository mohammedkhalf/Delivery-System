@extends('layouts.admin.app')

@section('title',translate('Update Banner'))

@php($isServiceModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'service' && service_addon_active())

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.update_banner')}}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-body">
                        <form data-ajax="true" id="banner_form" class="custom-validation">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    @if($language)
                                        <ul class="nav nav-tabs mb-4">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active"
                                                href="#"
                                                id="default-link">{{translate('messages.default')}}</a>
                                            </li>
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link"
                                                        href="#"
                                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="lang_form" id="default-form">
                                            <div class="form-group error-wrapper">
                                                <label class="input-label" for="default_title">{{translate('messages.title')}} ({{translate('messages.default')}}) <span class="text-danger">*</span></label>
                                                <input type="text" name="title[]" id="default_title" class="form-control" placeholder="{{translate('messages.new_banner')}}" required value="{{$banner?->getRawOriginal('title')}}">
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                        @foreach($language as $lang)
                                            <?php
                                                if(count($banner['translations'])){
                                                    $translate = [];
                                                    foreach($banner['translations'] as $t)
                                                    {
                                                        if($t->locale == $lang && $t->key=="title"){
                                                            $translate[$lang]['title'] = $t->value;
                                                        }
                                                    }
                                                }
                                            ?>
                                            <div class="d-none lang_form" id="{{$lang}}-form">
                                                <div class="form-group error-wrapper">
                                                    <label class="input-label" for="{{$lang}}_title">{{translate('messages.title')}} ({{strtoupper($lang)}})</label>
                                                    <input type="text" name="title[]" id="{{$lang}}_title" class="form-control" placeholder="{{translate('messages.new_banner')}}" value="{{$translate[$lang]['title']??''}}">
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{$lang}}">
                                            </div>
                                        @endforeach
                                    @else
                                    <div id="default-form">
                                        <div class="form-group error-wrapper">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.title')}} ({{ translate('messages.default') }}) <span class="text-danger">*</span></label>
                                            <input type="text" name="title[]" class="form-control" placeholder="{{translate('messages.new_banner')}}" value="{{$banner['title']}}" maxlength="100">
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    </div>
                                    @endif
                                    <div class="form-group error-wrapper">
                                        <label class="input-label" for="title">{{translate('messages.zone')}} <span class="text-danger">*</span></label>
                                        <select name="zone_id" id="zone" class="form-control js-select2-custom" required>
                                            <option  disabled selected>---{{translate('messages.select')}}---</option>
                                            @foreach($zones as $zone)
                                                @if(auth('admin')?->user()?->zone_id)
                                                    @if(auth('admin')->user()->zone_id == $zone->id)
                                                        <option value="{{$zone['id']}}" {{$zone->id == $banner->zone_id?'selected':''}}>{{$zone['name']}}</option>
                                                    @endif
                                                @else
                                                <option value="{{$zone['id']}}" {{$zone->id == $banner->zone_id?'selected':''}}>{{$zone['name']}}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group error-wrapper">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.banner_type')}} <span class="text-danger">*</span></label>
                                        <select name="banner_type" id="banner_type" class="form-control">
                                            <option value="store_wise" {{$banner->type == 'store_wise'? 'selected':'' }}>{{ $isServiceModule ? translate('Provider wise') : translate('messages.store_wise') }}</option>
                                            <option value="item_wise" {{$banner->type == 'item_wise'? 'selected':'' }}>{{ $isServiceModule ? translate('Service wise') : translate('messages.item_wise') }}</option>
                                            <option value="default" {{in_array($banner->type, ['default', 'default_link'])? 'selected':'' }}>{{translate('messages.default')}}</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-0 error-wrapper" id="store_wise">
                                        <label class="input-label" for="exampleFormControlSelect1">{{ $isServiceModule ? translate('Provider') : translate('messages.store') }}<span
                                                class="input-label-secondary"></span> <span class="text-danger">*</span></label>
                                        <select name="store_id" id="store_id" class="js-data-example-ajax form-control"  title="{{ $isServiceModule ? translate('Select Provider') : translate('Select Restaurant') }}">
                                            <option value=""></option>
                                        @if($banner->type=='store_wise')
                                        @php($store = \App\Models\Store::where('id', $banner->data)->first())
                                            @if($store)
                                            <option value="{{$store->id}}" data-verified="{{ (int) $store->verified_seller }}" selected>{{$store->name}}</option>
                                            @endif
                                        @endif
                                        </select>
                                    </div>
                                    <div class="form-group mb-0 error-wrapper" id="item_wise">
                                        <label class="input-label" for="exampleFormControlInput1">{{ $isServiceModule ? translate('Select service') : translate('messages.select_item') }} <span class="text-danger">*</span></label>
                                        <select name="item_id" id="choice_item" class="form-control js-select2-custom" placeholder="{{ $isServiceModule ? translate('Select service') : translate('messages.select_item') }}">

                                        </select>
                                    </div>
                                    <div class="form-group mb-0 error-wrapper" id="default">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.default_link')}} ({{ translate('messages.optional') }})</label>
                                        <input type="text" name="default_link" class="form-control" value="{{ $banner->default_link }}" placeholder="{{translate('messages.default_link')}}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="error-wrapper">
                                        <div class="h-100 d-flex flex-column justify-content-center">
                                            <label class="input-label d-block text-center mb-2">{{translate('messages.banner_image')}} <span class="text-danger">*</span></label>
                                            @include('admin-views.partials._image-uploader', [
                                                'id' => 'banner-image',
                                                'name' => 'image',
                                                'ratio' => '3:1',
                                                'isRequired' => empty($banner['image_full_url']),
                                                'existingImage' => $banner['image_full_url'] ?? '',
                                                'imageExtension' => IMAGE_EXTENSION,
                                                'imageFormat' => IMAGE_FORMAT,
                                                'maxSize' => MAX_FILE_SIZE,
                                                'textPosition' => 'bottom',
                                                'show_clear_button' => false,
                                            ])
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mt-4">
                                    <div class="btn--container justify-content-end">
                                        <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
                                        <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- End Table -->
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/banner-edit.js"></script>
    <script>
        "use strict";

        var zone_id = @json($banner->zone_id ?? []);

        var module_id = @json($banner->module_id);

        var item_source_url = "{{ $isServiceModule ? route('admin.service.get-services') : url('/').'/admin/item/get-items' }}";

        function get_items()
        {
            var nurl = item_source_url + '?module_id='+module_id;

            if(!Array.isArray(zone_id))
            {
                nurl += '&zone_id='+zone_id;
            }

            $.get({
                url: nurl,
                dataType: 'json',
                success: function (data) {
                    $('#choice_item').empty().append(data.options);
                }
            });
        }
        $(document).on('ready', function () {
            banner_type_change('{{ in_array($banner->type, ['default', 'default_link']) ? 'default' : $banner->type }}');
            if($('#banner_type').val() !== 'item_wise' ){
                get_items();
            }
            $('#zone').on('change', function(){
                $('#store_id').val(null).trigger('change');
                if($(this).val())
                {
                    zone_id = $(this).val();
                    get_items();
                }
                else
                {
                    zone_id = [];
                }
            });

            $('.js-data-example-ajax').select2({
                placeholder: '{{ $isServiceModule ? translate('Select Provider') : translate('messages.select_store') }}',
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    data: function (params) {
                        return {
                            q: params.term, // search term
                            zone_ids: [zone_id],
                            page: params.page,
                            module_id: module_id,
                            include_addon_providers: 1 // service is an addon provider; opt in so its stores list
                        };
                    },
                    processResults: function (data) {
                        return {
                        results: data
                        };
                    },
                    __port: function (params, success, failure) {
                        var $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });



            $('.js-select2-custom').each(function () {
                var select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });


        @if($banner->type == 'item_wise')
        getRequest(item_source_url + '?module_id={{$banner->module_id}}&zone_id={{$banner->zone_id}}&data[]={{$banner->data}}','choice_item');
        @endif
        $('#banner_form').on('submit', function (e) {
            e.preventDefault();

            let $form = $(this);
            if (!$form.valid()) {
                return false;
            }

            let banner_type = $('#banner_type').val();
            if (banner_type === 'store_wise' && !$('#store_id').val()) {
                toastr.error('{{ $isServiceModule ? translate('Please select a provider') : translate('Please select a store') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                return false;
            }
            if (banner_type === 'item_wise' && !$('#choice_item').val()) {
                toastr.error('{{ $isServiceModule ? translate('Please select a service') : translate('Please select an item') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                return false;
            }

            var formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: "{{route('admin.banner.update', [$banner['id']])}}",
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    if (data.errors) {
                        for (var i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        toastr.success("{{translate('messages.banner_updated_successfully')}}", {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        setTimeout(function () {
                            location.href = "{{url()->full()}}";
                        }, 2000);
                    }
                }
            });

        });
    </script>
@endpush
