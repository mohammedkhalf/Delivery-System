@extends('layouts.admin.app')

@section('title', \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Categories'))

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
                    {{ \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Categories') }}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $categories->total() }}</span>
                </span>
            </h1>
        </div>
        <!-- End Page Header -->

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.store-category.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row align-items-lg-center">
                        <div class="col-md-8 col-lg-8">
                            <div class="bg-light rounded p-20 mb-3">
                                @if ($language)
                                    <ul class="nav nav-tabs mb-4 border-0">
                                        <li class="nav-item">
                                            <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('messages.default') }}</a>
                                        </li>
                                        @foreach ($language as $lang)
                                            <li class="nav-item">
                                                <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($language)
                                    <div class="form-group m-0 lang_form" id="default-form">
                                        <label class="input-label">
                                            {{ translate('messages.name') }} ({{ translate('messages.default') }})
                                            <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Required.') }}"> *</span>
                                        </label>
                                        <input type="text" name="name[]" value="{{ old('name.0') }}" class="form-control" placeholder="{{ translate('messages.Type') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Category_Name') }}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                    @foreach ($language as $key => $lang)
                                        <div class="form-group m-0 d-none lang_form" id="{{ $lang }}-form">
                                            <label class="input-label">
                                                {{ translate('messages.name') }} ({{ strtoupper($lang) }})
                                            </label>
                                            <input type="text" name="name[]" value="{{ old('name.' . $key + 1) }}" class="form-control" placeholder="{{ translate('messages.Type') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Category_Name') }}" maxlength="191">
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    @endforeach
                                @else
                                    <div class="form-group m-0">
                                        <label class="input-label">{{ translate('messages.name') }}</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{ translate('messages.Type') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Category_Name') }}" value="{{ old('name.0') }}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                @endif
                            </div>
                            <div class="bg-light rounded p-20">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group m-0">
                                            <label class="input-label">{{ \App\CentralLogics\Helpers::moduleStoreLabel() }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select required name="store_id" class="form-control js-store-select2-ajax" data-placeholder="{{ translate('messages.Select') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() }}"></select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group m-0">
                                            <label class="input-label">{{ translate('messages.Priority') }}</label>
                                            <select required name="priority" class="custom-select">
                                                <option value="0">{{ translate('messages.Normal') }}</option>
                                                <option value="1">{{ translate('messages.Medium') }}</option>
                                                <option value="2">{{ translate('messages.High') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-4">
                            <div class="bg-light rounded p-20 h-100">
                                <div class="text-center py-1">
                                    <div class="mx-auto text-center">
                                        <div class="mb-4">
                                            <h5 class="mb-1">{{ \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Category_Image') }}
                                                <span class="text-danger">*</span>
                                            </h5>
                                            <p class="mb-0 fs-12 gray-dark">{{ translate('messages.Upload_image') }}</p>
                                        </div>
                                        @include('admin-views.partials._image-uploader', [
                                            'id' => 'store-category-image-input',
                                            'name' => 'image',
                                            'ratio' => '1:1',
                                            'isRequired' => true,
                                            'existingImage' => '',
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
                        <button type="reset" class="btn btn--reset">{{ translate('messages.reset') }}</button>
                        <button type="submit" class="btn btn--primary">{{ translate('messages.add') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    <h5 class="card-title">
                        {{ \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.Category_List') }}
                        <span class="badge badge-soft-dark ml-2">{{ $categories->total() }}</span>
                    </h5>
                    <form class="search-form w-340-lg">
                        <div class="input-group input--group">
                            <input type="search" name="search" value="{{ request()?->search ?? null }}" class="form-control h-40" placeholder="{{ translate('messages.search') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.categories') }}">
                            <button type="submit" class="btn btn--primary h-40"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold ml-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white text-title dropdown-toggle font-medium min-height-40"
                            href="javascript:;"
                            data-hs-unfold-options='{"target":"#storeCategoryExportDropdown","type":"css-animation"}'>
                            <i class="tio-download-to mr-1 text-title"></i> {{ translate('messages.export') }}
                        </a>
                        <div id="storeCategoryExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                            <a class="dropdown-item" href="{{ route('admin.store-category.export', array_merge(['type' => 'excel'], request()->query())) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                                {{ translate('messages.excel') }}
                            </a>
                            <a class="dropdown-item" href="{{ route('admin.store-category.export', array_merge(['type' => 'csv'], request()->query())) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                                {{ translate('messages.csv') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-align-middle">
                        <thead class="bg-table-head">
                            <tr>
                                <th class="text-title border-0">{{ translate('sl') }}</th>
                                <th class="text-title border-0">{{ translate('messages.name') }}</th>
                                <th class="text-title border-0">{{ \App\CentralLogics\Helpers::moduleStoreLabel() }}</th>
                                <th class="text-title border-0 text-center">{{ translate('messages.status') }}</th>
                                <th class="text-title border-0 text-center">{{ translate('messages.priority') }}</th>
                                <th class="text-title border-0 text-center">{{ translate('messages.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $key => $category)
                                <tr>
                                    <td>{{ $key + $categories->firstItem() }}</td>
                                    <td>
                                        <div class="media-area d-flex gap-2 align-items-center">
                                            <div class="w-40px min-w-40px h-40px rounded overflow-hidden border">
                                                <img src="{{ $category['image_full_url'] }}" alt="" class="w-100 rounded object-cover">
                                            </div>
                                            <div>
                                                <span class="fs-14 line--limit-2 text-title max-w-250 min-w-160">
                                                    {{ Str::limit($category['name'], 20, '...') }}
                                                </span>
                                                <p class="m-0">#{{ $category->id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $category->store?->name ?? translate('messages.N/A') }}</td>
                                    <td class="text-center">
                                        <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{ $category->id }}">
                                            <input type="checkbox" data-url="{{ route('admin.store-category.status', ['id' => $category->id, 'status' => $category->status ? 0 : 1]) }}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{ $category->id }}" {{ $category->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label mx-auto">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <form action="{{ route('admin.store-category.priority', $category->id) }}" class="priority-form">
                                            <select name="priority" class="form-control form--control-select priority-select mx-auto {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                                                <option value="0" {{ $category->priority == 0 ? 'selected' : '' }}>{{ translate('messages.normal') }}</option>
                                                <option value="1" {{ $category->priority == 1 ? 'selected' : '' }}>{{ translate('messages.medium') }}</option>
                                                <option value="2" {{ $category->priority == 2 ? 'selected' : '' }}>{{ translate('messages.high') }}</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn btn-outline-theme-dark offcanvas-trigger data-info-show" href="javascript:void(0)" data-id="{{ $category['id'] }}" data-url="{{ route('admin.store-category.edit', [$category['id']]) }}" data-target="#offcanvas__storeCategoryBtn">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:" data-id="store-category-{{ $category['id'] }}" data-message="{{ translate('messages.Want_to_delete_this') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('messages.category') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.store-category.delete') }}" method="post" id="store-category-{{ $category['id'] }}">
                                                @csrf @method('delete')
                                                <input type="hidden" name="id" value="{{ $category['id'] }}">
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if (count($categories) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                    <h5>{{ translate('no_data_found') }}</h5>
                </div>
            @endif
            <div class="page-area px-4 pb-3">
                <div class="d-flex align-items-center justify-content-end">
                    <div>{!! $categories->withQueryString()->links() !!}</div>
                </div>
            </div>
        </div>
    </div>

    <div id="offcanvas__storeCategoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100"></div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/category-index.js"></script>
    <script>
        "use strict";

        function initStoreSelect2Ajax(selector) {
            $(selector).select2({
                placeholder: $(selector).data('placeholder') || '{{ translate('messages.Select') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() }}',
                allowClear: false,
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term,
                        module_type: '{{ config('module.current_module_type') }}',
                        include_addon_providers: 1,
                        show_active: 1,
                        page: params.page
                    }),
                    processResults: data => ({ results: data })
                }
            });
        }

        initStoreSelect2Ajax('.js-store-select2-ajax');

        function initLangTabs() {
            const langLinks = document.querySelectorAll('#data-view .lang_link1');
            langLinks.forEach(function (langLink) {
                langLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    langLinks.forEach(link => link.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelectorAll('#data-view .lang_form1').forEach(form => form.classList.add('d-none'));
                    const lang = this.id.substring(0, this.id.length - 5);
                    $('#' + lang + '-form1').removeClass('d-none');
                });
            });
        }

        function initSelect2Dropdowns() {
            $('.offcanvas-close, #offcanvasOverlay').off('click.offcanvas').on('click.offcanvas', function () {
                $('.custom-offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
                $('#content-disable').removeClass('disabled');
            });
        }

        $(document).on('click', '.data-info-show', function () {
            let id = $(this).data('id');
            let url = $(this).data('url');
            $('#content-disable').addClass('disabled');
            fetch_data(id, url);
        });

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $("#data-view").html(data.view);
                    if (typeof initLangTabs === 'function') initLangTabs();
                    if (typeof initSelect2Dropdowns === 'function') initSelect2Dropdowns();
                    if (typeof checkPreExistingImages === 'function') checkPreExistingImages();
                    initStoreSelect2Ajax('#data-view .js-store-select2-ajax');
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        }

        $('.priority-select').on('change', function () {
            $(this).closest('form').submit();
        });
    </script>
@endpush
