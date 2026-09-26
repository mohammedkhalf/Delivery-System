@extends('layouts.admin.app')

@section('title', translate('messages.Add new main category'))

@push('css_or_js')
@endpush

@section('content')
    <div id="content-disable" class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/category.png') }}" class="w--20" alt="">
                </span>
                <span>
                    {{ translate('Add Main Category') }}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->

        <div class="card">
            <div class="card-body">
                <form
                    action="{{ isset($category) ? route('admin.category.update', [$category['id']]) : route('admin.category.store') }}"
                    method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row align-items-lg-center">
                        <div class="col-md-8 col-lg-8">
                            <div class="bg-light rounded p-20 mb-3">
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
                                @if ($language)
                                    <div class="form-group m-0 lang_form" id="default-form">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.name') }}
                                            ({{ translate('messages.default') }})
                                            <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>

                                        </label>
                                        <input type="text" name="name[]" value="{{ old('name.0') }}" class="form-control"
                                            placeholder="{{ translate('messages.new_main_category') }}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                    @foreach ($language as $key => $lang)
                                        <div class="form-group m-0 d-none lang_form" id="{{ $lang }}-form">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.name') }}
                                                ({{ strtoupper($lang) }})
                                            </label>
                                            <input type="text" name="name[]" value="{{ old('name.' . $key + 1) }}"
                                                class="form-control" placeholder="{{ translate('messages.new_main_category') }}"
                                                maxlength="191">
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    @endforeach
                                @else
                                    <div class="form-group m-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.name') }}</label>
                                        <input type="text" name="name" class="form-control"
                                            placeholder="{{ translate('messages.new_main_category') }}" value="{{ old('name') }}"
                                            maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                @endif
                            </div>
                            <div class="bg-light rounded p-20">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <input name="position" value="0" class="initial-hidden">
                                        <div class="form-group m-0">
                                            <label class="input-label" for="">
                                                {{ translate('messages.Priority') }}
                                            </label>
                                            <select required name="priority"
                                                data-original-title="{{ translate('messages.Select_Priority') }}"
                                                class="custom-select">
                                                <option value="0">{{ translate('messages.Normal') }}</option>
                                                <option value="1">{{ translate('messages.Medium') }}</option>
                                                <option value="2">{{ translate('messages.High') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        @if ($categoryWiseTax)
                                            <span class="mb-2 d-block title-clr fw-normal">{{ translate('Select Tax Rate') }}</span>
                                            <select name="tax_ids[]" id="tax__rate"
                                                class="form-control js-select2-custom js-select2-counting" multiple="multiple" required
                                                placeholder="Type & Select Tax Rate">
                                                @foreach ($taxVats as $taxVat)
                                                    <option value="{{ $taxVat->id }}"> {{ $taxVat->name }}
                                                        ({{ $taxVat->tax_rate }}%)
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="col-md-4 col-lg-4">
                            <div class="bg-light rounded p-20 h-100">



                                <div class="text-center py-1">
                                    <div class="mx-auto text-center">
                                        <div class="mb-4">
                                            <h5 class="mb-1">{{ translate('Main Category Image') }}
                                                <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                            </h5>
                                            <p class="mb-0 fs-12 gray-dark">{{ translate('Upload image') }}</p>
                                        </div>
                                        @include('admin-views.partials._image-uploader', [
                                            'id' => 'category-image-input',
                                            'name' => 'image',
                                            'ratio' => '1:1',
                                            'isRequired' => true,
                                            'existingImage' => isset($category) ? ($category['image_full_url'] ?? '') : '',
                                            'imageExtension' => IMAGE_EXTENSION,
                                            'imageFormat' => IMAGE_FORMAT,
                                            'maxSize' => MAX_FILE_SIZE,
                                            'textPosition' => 'bottom',
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-20">
                        <button type="reset" id="reset_btn"
                            class="btn btn--reset">{{ translate('messages.reset') }}</button>
                        <button type="submit"
                            class="btn btn--primary">{{ isset($category) ? translate('messages.update') : translate('messages.add') }}</button>
                    </div>

                </form>
            </div>
        </div>

        <div id="category-list-wrapper">
            @include('admin-views.category.partials._list-main', compact('categories', 'status', 'categoryWiseTax'))
        </div>

    </div>

    <div id="offcanvas__categoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/category-index.js"></script>
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/category-list-ajax.js"></script>
    <script>
        "use strict";

        initCategoryListAjax('category-list-wrapper', {
            areYouSure: '{{ translate('messages.Are you sure?') }}',
            no: '{{ translate('messages.no') }}',
            yes: '{{ translate('messages.Yes') }}',
            offDangerImage: '{{ asset('public/assets/admin/img/off-danger.png') }}'
        });

        // The All/Active/Inactive list tabs (and search/pagination) are plain links that fully
        // reload this page. On every reload the language tab markup hardcodes "Default" as
        // active, so switching a list tab looked like it was resetting whichever language
        // (EN/AR) tab was selected above. Remember the selected language tab across the reload
        // and restore it here — independent of common.js's own click handler (which attaches its
        // listener in a later DOMContentLoaded callback than this one, so re-dispatching a click
        // here would fire before that handler exists).
        (function() {
            const STORAGE_KEY = 'admin_category_lang_tab_main';

            document.addEventListener('DOMContentLoaded', function() {
                const langLinks = document.querySelectorAll('.lang_link');
                if (!langLinks.length) return;

                langLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        sessionStorage.setItem(STORAGE_KEY, this.id);
                    });
                });

                const savedId = sessionStorage.getItem(STORAGE_KEY);
                if (!savedId || savedId === 'default-link') return;

                const savedLink = document.getElementById(savedId);
                if (!savedLink) return;

                langLinks.forEach(function(link) {
                    link.classList.remove('active');
                });
                savedLink.classList.add('active');

                document.querySelectorAll('.lang_form').forEach(function(form) {
                    form.classList.add('d-none');
                });

                const lang = savedId.split('-link')[0];
                const form = document.getElementById(lang + '-form');
                if (form) {
                    form.classList.remove('d-none');
                }
            });
        })();

        $('.location-reload-to-category').on('click', function() {
            const url = $(this).data('url');
            let nurl = new URL(url);
            nurl.searchParams.delete('search');
            location.href = nurl;
        });

        $("#customFileEg1").change(function() {
            readURL(this);
            $('#viewer').show(1000)
        });

        $('#reset_btn').click(function() {
            $('#exampleFormControlSelect1').val(null).trigger('change');
            $('#viewer').attr('src', "{{ asset('public/assets/admin/img/upload-img.png') }}");
        })


        $(document).on('click', '.data-info-show', function() {
            let id = $(this).data('id');
            let url = $(this).data('url');
            $('#content-disable').addClass('disabled');
            fetch_data(id, url)
        })

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show()
                },
                success: function(data) {
                    $("#data-view").append(data.view);
                    initLangTabs();
                    initSelect2Dropdowns();
                    if (typeof checkPreExistingImages === 'function') {
                        checkPreExistingImages();
                    }
                },
                complete: function() {
                    $('#loading').hide()
                }
            })
        }


        function initLangTabs() {
            const langLinks = document.querySelectorAll(".lang_link1");
            langLinks.forEach(function(langLink) {
                langLink.addEventListener("click", function(e) {
                    e.preventDefault();
                    langLinks.forEach(function(link) {
                        link.classList.remove("active");
                    });
                    this.classList.add("active");
                    document.querySelectorAll(".lang_form1").forEach(function(form) {
                        form.classList.add("d-none");
                    });
                    let form_id = this.id;
                    let lang = form_id.substring(0, form_id.length - 5);
                    $("#" + lang + "-form1").removeClass("d-none");
                    if (lang === "default") {
                        $(".default-form1").removeClass("d-none");
                    }
                });
            });
        }

        function initSelect2Dropdowns() {
            $('.js-select2-custom1').select2({
                placeholder: 'Select tax rate',
                allowClear: true
            });

            $('.offcanvas-close, #offcanvasOverlay').on('click', function() {
                $('.custom-offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
                $('#content-disable').removeClass('disabled');
            });
        }
    </script>
@endpush
