@extends('layouts.vendor.app')

@section('title', translate('messages.My_Category'))

@push('css_or_js')
@endpush

@section('content')
    <div id="content-disable" class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title mb-0">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/category.png') }}" class="w--20" alt="">
                </span>
                <span>
                    {{ translate('messages.My_Category') }}
                    @if(count($categories) > 0)
                        <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $categories->total() }}</span>
                    @endif
                </span>
            </h1>

            <div class="d-flex flex-wrap  gap-2 ml-auto">
                @if(count($categories) > 0)
                    <form id="vendorStoreCategoryFilterForm" class="d-flex flex-wrap align-items-center gap-2 mb-0">


                        <div class="input-group input--group w-340-lg">
                            <input type="search" name="search" value="{{ request('search') }}" class="form-control h--40px"
                                placeholder="{{ translate('messages.search_categories') }}">
                            <button type="submit" class="btn btn--primary h--40px"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-white text-title dropdown-toggle font-medium h--40px"
                            href="javascript:;"
                            data-hs-unfold-options='{"target":"#storeCategoryExportDropdown","type":"css-animation"}'>
                            <i class="tio-download-to mr-1 text-title"></i> {{ translate('messages.export') }}
                        </a>
                        <div id="storeCategoryExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                            <a class="dropdown-item" href="{{ route('vendor.store-category.export', array_merge(['type' => 'excel'], request()->query())) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                                {{ translate('messages.excel') }}
                            </a>
                            <a class="dropdown-item" href="{{ route('vendor.store-category.export', array_merge(['type' => 'csv'], request()->query())) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                                {{ translate('messages.csv') }}
                            </a>
                        </div>
                    </div>
                @endif

                <a href="javascript:void(0)" class="btn btn--primary h--40px offcanvas-trigger create-category-trigger"
                    data-target="#offcanvas__storeCategoryBtn" data-action="create">
                    <i class="tio-add-circle mr-1"></i> {{ translate('Add My Category') }}
                </a>
            </div>
        </div>
        <!-- End Page Header -->

        <div class="card">
            <div class="card-body p-0">
                @if(count($categories) > 0)
                    <div class="alert d-flex align-items-start gap-2 m-3 mb-0 py-2 px-3" role="alert"
                        style="background-color: #FFF8E5; border: 1px solid #FFE6A8; border-radius: 8px;">
                        <i class="tio-info mt-1" style="color: #F2A93B;"></i>
                        <div class="fs-12 text-body">
                            {{ translate('Once you create store categories, you must add your items to those categories. Without assigning items, they will not appear on your store details page. If you want to proceed with the main category, you can skip adding any store categories.') }}
                        </div>
                    </div>

                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-align-middle">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center w-5p">{{ translate('SL') }}</th>
                                    <th class="w-30p">{{ translate('Category Info') }}</th>
                                    <th class="text-center w-15p">{{ translate('Priority Level') }}</th>
                                    <th class="text-center w-10p">{{ translate('messages.status') }}</th>
                                    <th class="text-center w-10p">{{ translate('messages.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $key => $category)
                                    <tr>
                                        <td class="text-center">{{ $key + $categories->firstItem() }}</td>
                                        <td>
                                            <div class="media-area d-flex gap-2 align-items-center">
                                                <div class="w-40px min-w-40 h--40pxpx rounded overflow-hidden border">
                                                    <img src="{{ $category['image_full_url'] }}" alt="" class="w-100 rounded object-cover">
                                                </div>
                                                <div>
                                                    <span class="fs-14 line--limit-2 text-title max-w-250 min-w-160">
                                                        {{ Str::limit($category['name'], 32, '...') }}
                                                    </span>
                                                    <p class="m-0 fs-12 text-muted">{{ translate('ID') }} #{{ $category->id }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <form action="{{ route('vendor.store-category.priority', $category->id) }}" class="priority-form">
                                                <select name="priority" class="form-control form--control-select priority-select mx-auto {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                                                    <option value="0" {{ $category->priority == 0 ? 'selected' : '' }}>{{ translate('messages.normal') }}</option>
                                                    <option value="1" {{ $category->priority == 1 ? 'selected' : '' }}>{{ translate('messages.medium') }}</option>
                                                    <option value="2" {{ $category->priority == 2 ? 'selected' : '' }}>{{ translate('messages.high') }}</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{ $category->id }}">
                                                <input type="checkbox"
                                                    data-url="{{ route('vendor.store-category.status', ['id' => $category->id, 'status' => $category->status ? 0 : 1]) }}"
                                                    class="toggle-switch-input redirect-url"
                                                    id="stocksCheckbox{{ $category->id }}"
                                                    {{ $category->status ? 'checked' : '' }}>
                                                <span class="toggle-switch-label mx-auto">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="btn action-btn btn--warning btn-outline-warning assign-items-trigger"
                                                    href="javascript:void(0)"
                                                    data-url="{{ route('vendor.store-category.items', $category->id) }}"
                                                    data-target="#offcanvas__assignItemsBtn"
                                                    title="{{ translate('Assign Items') }}">
                                                    <i class="tio-add-circle-outlined"></i>
                                                </a>
                                                <a class="btn action-btn btn-outline-theme-dark offcanvas-trigger data-info-show"
                                                    href="javascript:void(0)"
                                                    data-id="{{ $category['id'] }}"
                                                    data-url="{{ route('vendor.store-category.edit', [$category['id']]) }}"
                                                    data-target="#offcanvas__storeCategoryBtn"
                                                    data-action="edit"
                                                    title="{{ translate('Edit My Category') }}">
                                                    <i class="tio-edit"></i>
                                                </a>
                                                <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                                                    href="javascript:"
                                                    data-id="store-category-{{ $category['id'] }}"
                                                    data-message="{{ translate('messages.Want_to_delete_this_category') }}"
                                                    title="{{ translate('Delete My Category') }}">
                                                    <i class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{ route('vendor.store-category.delete') }}" method="post" id="store-category-{{ $category['id'] }}">
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
                @else
                    <div class="my-category-empty p-4 p-md-5 text-center">
                        <img src="{{ asset('public/assets/admin/img/empty-my-category.svg') }}"
                            alt="{{ translate('No My Category') }}"
                            class="mb-3" width="64" height="64">
                        <h5 class="mb-2 font-weight-bold">{{ translate('Add My Store Category') }}</h5>
                        <p class="text-muted mb-3 mx-auto" style="max-width: 560px;">
                            {{ translate('Organize your items with custom categories to make your menu easier for customers to browse.') }}
                        </p>
                        <div class="alert alert-warning d-inline-flex align-items-start gap-2 text-left mx-auto py-2 px-3 mb-0"
                            style="max-width: 640px; background-color: #FFF8E5; border: 1px solid #FFE6A8;" role="alert">
                            <i class="tio-info mt-1" style="color: #F2A93B;"></i>
                            <div class="fs-12 text-body">
                                {{ translate('Once you create store categories, you must add your items to those categories. Without assigning items, they will not appear on your store details page. If you want to proceed with the main category, you can skip adding any store categories.') }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if(count($categories) > 0)
                <div class="card-footer page-area">
                    {!! $categories->withQueryString()->links() !!}
                </div>
            @endif
        </div>
    </div>

    <div id="offcanvas__storeCategoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100"></div>
    </div>

    <div id="offcanvas__assignItemsBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="assign-items-view" class="h-100"></div>
    </div>

    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script>
        "use strict";

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

        $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
            $('.custom-offcanvas').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
            $('#content-disable').removeClass('disabled');
        });

        $('.priority-select').on('change', function () {
            $(this).closest('form').submit();
        });

        document.addEventListener('DOMContentLoaded', function () {
            $('#vendorPriorityFilterSelect').on('change', function () {
                $('#vendorStoreCategoryFilterForm').submit();
            });

            // The upload-single-image.js only runs initFileUpload() on doc-ready if
            // a .upload-file_custom element is already present. Our form loads via
            // AJAX, so the change/edit/remove handlers are never bound. Call it
            // explicitly here — the handlers are delegated and idempotent.
            if (typeof initFileUpload === 'function') {
                initFileUpload();
            }
        });

        // ----- Create / Edit category off-canvas -----
        $(document).on('click', '.create-category-trigger', function () {
            $('#content-disable').addClass('disabled');
            fetch_data(null, "{{ route('vendor.store-category.create') }}", '#offcanvas__storeCategoryBtn');
        });

        $(document).on('click', '.data-info-show', function () {
            const id = $(this).data('id');
            const url = $(this).data('url');
            const target = $(this).data('target') || '#offcanvas__storeCategoryBtn';
            $('#content-disable').addClass('disabled');
            fetch_data(id, url, target);
        });

        // The default-language name input is `required`, but switching to another
        // language tab hides it (`d-none`). The browser cannot focus a hidden
        // required field, so it silently blocks submission with no message. Guard
        // the submit: if the default name is empty, reveal the default tab, focus
        // the field, and surface the validation message instead of failing silently.
        $(document).on('click', '#data-view form button[type="submit"]', function (e) {
            const $form = $(this).closest('form');
            const $defaultInput = $form.find('#default-form1 input[name="name[]"]');
            if ($defaultInput.length && !$defaultInput.val().trim()) {
                e.preventDefault();
                $form.find('.lang_link1').removeClass('active');
                $form.find('#default-link').addClass('active');
                $form.find('.lang_form1').addClass('d-none');
                $form.find('#default-form1').removeClass('d-none');
                $defaultInput.trigger('focus');
                toastr.error("{{ translate('messages.default_name_is_required') }}");
            }
        });

        function fetch_data(id, url, target) {
            target = target || '#offcanvas__storeCategoryBtn';
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function () { $('#loading').show(); },
                success: function (data) {
                    $("#data-view").html(data.view);
                    $(target).addClass('open');
                    $('#offcanvasOverlay').addClass('show');
                    initLangTabs();
                    // Re-bind file uploader handlers and run the pre-existing image
                    // check for the newly injected form HTML.
                    if (typeof initFileUpload === 'function') initFileUpload();
                    if (typeof checkPreExistingImages === 'function') checkPreExistingImages();
                },
                complete: function () { $('#loading').hide(); }
            });
        }

        // ----- Assign Items to Category flow -----
        $(document).on('click', '.assign-items-trigger', function () {
            const url = $(this).data('url');
            const target = $(this).data('target') || '#offcanvas__assignItemsBtn';
            fetchAssignItemsOffcanvas(url, target);
        });

        function fetchAssignItemsOffcanvas(url, target) {
            $.ajax({
                url: url,
                type: 'get',
                beforeSend: function () {
                    $('#assign-items-view').empty();
                    $('#loading').show();
                },
                success: function (data) {
                    $('#assign-items-view').html(data.view);
                    $(target).addClass('open');
                    $('#offcanvasOverlay').addClass('show');
                    $('#content-disable').addClass('disabled');
                    initAssignItemsHandlers();
                },
                complete: function () { $('#loading').hide(); }
            });
        }

        // Source-of-truth Set of currently-selected item ids (as strings).
        // Survives across server-side search refreshes so the user's pending
        // changes (checks/unchecks) are not lost when the list reloads.
        let assignItemsSelectedIds = new Set();

        function syncAssignItemsDomToSet() {
            $('#assignItemsForm .assign-item-checkbox').each(function () {
                const id = String(this.value);
                // Locked rows (server-rendered as disabled) are always selected
                // and cannot be toggled off — make sure they stay in the Set.
                if (this.disabled) {
                    assignItemsSelectedIds.add(id);
                    this.checked = true;
                    $(this).closest('.assign-item-row').addClass('is-selected');
                    return;
                }
                const shouldBeChecked = assignItemsSelectedIds.has(id);
                this.checked = shouldBeChecked;
                $(this).closest('.assign-item-row').toggleClass('is-selected', shouldBeChecked);
            });
        }

        function initAssignItemsHandlers() {
            const $form = $('#assignItemsForm');
            if (!$form.length) return;

            const $searchInput = $form.find('input[name="assign_search"]');
            const $listContainer = $('#assignItemsListContainer');
            const searchUrl = $form.data('search-url');
            let searchTimer = null;

            // Seed the selection set from whatever the server marked checked on
            // initial render (i.e. items currently assigned to this category).
            assignItemsSelectedIds = new Set();
            $form.find('.assign-item-checkbox:checked').each(function () {
                assignItemsSelectedIds.add(String(this.value));
            });
            updateAssignItemsSelectedCount();
            updateAssignItemsSaveState();

            const assignItemsLoaderHtml =
                '<div class="d-flex flex-column align-items-center justify-content-center text-muted py-5">' +
                    '<div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>' +
                    '<span class="fs-12">{{ translate('Searching') }}...</span>' +
                '</div>';

            $searchInput.off('input').on('input', function () {
                clearTimeout(searchTimer);
                const term = $(this).val().trim();

                if (term.length === 1) {
                    return;
                }

                searchTimer = setTimeout(function () {
                    $.ajax({
                        url: searchUrl,
                        type: 'get',
                        data: { search: term },
                        beforeSend: function () {
                            $listContainer.html(assignItemsLoaderHtml);
                        },
                        success: function (data) {
                            $listContainer.html(data.view);
                            // Re-apply the user's pending selection to the freshly
                            // rendered rows so checks/unchecks aren't lost.
                            syncAssignItemsDomToSet();
                            updateAssignItemsSelectedCount();
                            updateAssignItemsSaveState();
                        }
                    });
                }, 300);
            });

            $form.off('change', '.assign-item-checkbox').on('change', '.assign-item-checkbox', function () {
                const id = String(this.value);
                if (this.checked) {
                    assignItemsSelectedIds.add(id);
                } else {
                    assignItemsSelectedIds.delete(id);
                }
                $(this).closest('.assign-item-row').toggleClass('is-selected', this.checked);
                updateAssignItemsSelectedCount();
            });

            $form.find('.reset-assign-btn').off('click').on('click', function () {
                // Reset only clears the newly-checked (non-locked) items.
                // Locked rows are already saved to this category and stay selected.
                $form.find('.assign-item-checkbox:not(:disabled)').each(function () {
                    assignItemsSelectedIds.delete(String(this.value));
                    this.checked = false;
                    $(this).closest('.assign-item-row').removeClass('is-selected');
                });
                updateAssignItemsSelectedCount();

                if ($searchInput.val() !== '') {
                    $searchInput.val('').trigger('input');
                } else {
                    updateAssignItemsSaveState();
                }
            });

            $form.off('submit').on('submit', function (e) {
                e.preventDefault();
                const $submitBtn = $form.find('button[type="submit"]');
                $submitBtn.prop('disabled', true);

                // Build payload from the Set, not from DOM — items checked but
                // currently filtered out by search must still be submitted.
                const formData = new FormData();
                formData.append('_token', $form.find('input[name="_token"]').val());
                assignItemsSelectedIds.forEach(function (id) {
                    formData.append('item_ids[]', id);
                });

                $.ajax({
                    url: $form.attr('action'),
                    type: 'post',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response && response.success) {
                            toastr.success(response.message || '{{ translate('Items assigned successfully') }}');
                            $('.custom-offcanvas').removeClass('open');
                            $('#offcanvasOverlay').removeClass('show');
                            $('#content-disable').removeClass('disabled');
                            setTimeout(function () { location.reload(); }, 600);
                        } else {
                            toastr.error((response && response.message) || '{{ translate('Something went wrong') }}');
                            $submitBtn.prop('disabled', false);
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || '{{ translate('Something went wrong') }}';
                        toastr.error(msg);
                        $submitBtn.prop('disabled', false);
                    }
                });
            });
        }

        function updateAssignItemsSelectedCount() {
            // Count from the source-of-truth Set (includes selections that may
            // currently be filtered out of view by the search box).
            $('#assignItemsSelectedCount').text(assignItemsSelectedIds.size);
        }

        function updateAssignItemsSaveState() {
            const isEmpty = $('#assignItemsListContainer').find('.assign-item-row').length === 0;
            $('#assignItemsForm').find('button[type="submit"]').prop('disabled', isEmpty);
        }

        // Auto-open assign-items offcanvas after a category is just created
        document.addEventListener('DOMContentLoaded', function () {
            const params = new URLSearchParams(window.location.search);
            const newId = params.get('assign_items');
            if (newId) {
                const url = "{{ url('vendor-panel/store-category/items') }}/" + newId;
                fetchAssignItemsOffcanvas(url, '#offcanvas__assignItemsBtn');

                params.delete('assign_items');
                const cleanUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, document.title, cleanUrl);
            }
        });
    </script>
@endpush
