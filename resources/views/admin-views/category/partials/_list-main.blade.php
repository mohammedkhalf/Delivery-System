<div class="card mt-3">
    <ul class="nav nav-tabs flex-wrap tabs-inner border-0 nav--tabs px-3 pt-3">
        <li class="nav-item">
            <a class="nav-link {{ $status === 'all' ? 'active' : '' }}"
                href="{{ route('admin.category.add', ['position' => 0, 'status' => 'all']) }}">{{ translate('messages.all') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'active' ? 'active' : '' }}"
                href="{{ route('admin.category.add', ['position' => 0, 'status' => 'active']) }}">{{ translate('messages.active') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'inactive' ? 'active' : '' }}"
                href="{{ route('admin.category.add', ['position' => 0, 'status' => 'inactive']) }}">{{ translate('messages.inactive') }}</a>
        </li>
    </ul>
    <div class="card-header py-2 border-0">
        <div class="search--button-wrapper">
            <h5 class="card-title">{{ translate('messages.main_category_list') }}<span
                    class="badge badge-soft-dark ml-2" id="itemCount">{{ $categories->total() }}</span></h5>

            <form class="search-form w-340-lg">
                <!-- Search -->
                <div class="input-group input--group">
                    <input type="search" name="search" value="{{ request()?->search ?? null }}"
                        class="form-control h-40" placeholder="{{ translate('messages.search_main_categories') }}"
                        aria-label="{{ translate('messages.ex_:_categories') }}">
                    <input type="hidden" name="position" value="0">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="btn btn--primary h-40"><i class="tio-search"></i></button>
                </div>
                <!-- End Search -->
            </form>
            @if (request()->input('search'))
                <button type="reset" class="btn btn--primary ml-2 location-reload-to-category"
                    data-url="{{ url()->full() }}">{{ translate('messages.reset') }}</button>
            @endif
            <!-- Unfold -->
            <div class="hs-unfold mr-2">
                <a class="js-hs-unfold-invoker btn btn-sm btn-white text-title dropdown-toggle font-medium min-height-40"
                    href="javascript:;"
                    data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                        }'>
                    <i class="tio-download-to mr-1 text-title"></i> {{ translate('messages.export') }}
                </a>

                <div id="usersExportDropdown"
                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                    <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                    <a id="export-excel" class="dropdown-item"
                        href="{{ route('admin.category.export-categories', ['type' => 'excel', request()->getQueryString()]) }}">
                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                            src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                            alt="Image Description">
                        {{ translate('messages.excel') }}
                    </a>
                    <a id="export-csv" class="dropdown-item"
                        href="{{ route('admin.category.export-categories', ['type' => 'csv', request()->getQueryString()]) }}">
                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                            src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                            alt="Image Description">
                        {{ translate('messages.csv') }}
                    </a>

                </div>
            </div>
            <!-- End Unfold -->
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive datatable-custom">
            <table id="columnSearchDatatable"
                class="table table-borderless table-thead-bordered table-align-middle"
                data-hs-datatables-options='{
                    "isResponsive": false,
                    "isShowPaging": false,
                    "paging":false,
                }'>
                <thead class="bg-table-head">
                    <tr>
                        <th class=" text-title border-0">{{ translate('sl') }}</th>
                        <th class=" text-title border-0 w--1">{{ translate('messages.name') }}</th>
                        <th class=" text-title border-0 text-center">{{ translate('messages.status') }}</th>

                        @if (Config::get('module.current_module_type') == 'ecommerce')
                        <th class=" text-title border-0 text-center">{{ translate('messages.featured') }}</th>
                        @endif
                        @if ($categoryWiseTax)
                            <th class=" text-title border-0 ">{{ translate('messages.Vat/Tax') }}</th>
                        @endif
                        <th class=" text-title border-0 text-center">{{ translate('messages.priority') }}
                                <span class="input-label-secondary"
                                    data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Categories will be displayed based on priority order: High first, then Medium, and finally Low ')}}"><img src="{{asset('public/assets/admin/img/info-circle.svg')}}"
                                    alt="public/img"></span>

                        </th>
                        <th class=" text-title border-0 text-center">{{ translate('messages.action') }}</th>
                    </tr>
                </thead>

                <tbody id="table-div">
                    @foreach ($categories as $key => $category)
                        <tr>
                            <td>{{ $key + $categories->firstItem() }}</td>
                            <td>
                                <div class="media-area d-flex gap-2 align-items-center">
                                    <div class="w-40px min-w-40px h-40px rounded overflow-hidden border">
                                        <img src="{{  $category['image_full_url'] }}" alt="" class="w-100 rounded object-cover">
                                    </div>
                                    <div>
                                        <span class="fs-14 line--limit-2 text-title max-w-250 min-w-160">
                                            {{ Str::limit($category['name'], 20, '...') }}
                                        </span>
                                        <p class="m-0">#{{ $category->id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <label class="toggle-switch toggle-switch-sm"
                                    for="stocksCheckbox{{ $category->id }}">
                                    <input type="checkbox"
                                        data-url="{{ route('admin.category.status', [$category['id'], $category->status ? 0 : 1]) }}"
                                        class="toggle-switch-input redirect-url"
                                        id="stocksCheckbox{{ $category->id }}"
                                        {{ $category->status ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            @if (Config::get('module.current_module_type') == 'ecommerce')

                            <td>
                                <label class="toggle-switch toggle-switch-sm"
                                    for="featuredCheckbox{{ $category->id }}">
                                    <input type="checkbox" data-id="featuredCheckbox{{ $category->id }}"
                                        data-type="status"
                                        data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                        data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                        data-title-on="{{ translate('Do you want to Featured this main category ?') }}"
                                        data-title-off="{{ translate('Do you want to remove this main category from featured ?') }}"
                                        data-text-on="<p>{{ translate('If you turn on this main category as a featured category it will show in customer app landing page.') }}"
                                        data-text-off="<p>{{ translate('If you turn off this main category from featured category it will not show in customer app landing page.') }}</p>"
                                        class="toggle-switch-input dynamic-checkbox"
                                        id="featuredCheckbox{{ $category->id }}"
                                        {{ $category->featured ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>

                                <form
                                    action="{{ route('admin.category.featured', [$category['id'], $category->featured ? 0 : 1]) }}"
                                    method="get" id="featuredCheckbox{{ $category->id }}_form">
                                </form>
                            </td>
                            @endif


                            @if ($categoryWiseTax)
                                <td>
                                    <span class="d-block fs-14 text-title text-body ">
                                        @forelse ($category?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $tax)
                                            <span class="bg-light rounded py-2 px-3">
                                                {{ $tax }} :
                                                <span class="font-light">
                                                    ({{ $key }}%)
                                                </span>
                                            </span>
                                            <br>
                                        @empty
                                            <span> {{ translate('messages.N/A') }} </span>
                                        @endforelse
                                    </span>
                                </td>
                            @endif
                            <td>
                                <form action="{{ route('admin.category.priority', $category->id) }}"
                                    class="priority-form">
                                    <select name="priority" id="priority"
                                        class="form-control form--control-select  priority-select  mx-auto {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                                        <option value="0" class="text--title"
                                            {{ $category->priority == 0 ? 'selected' : '' }}>
                                            {{ translate('messages.normal') }}</option>
                                        <option value="1" class="text--title"
                                            {{ $category->priority == 1 ? 'selected' : '' }}>
                                            {{ translate('messages.medium') }}</option>
                                        <option value="2" class="text--title"
                                            {{ $category->priority == 2 ? 'selected' : '' }}>
                                            {{ translate('messages.high') }}</option>
                                    </select>
                                </form>

                            </td>
                            <td>
                                <div class="btn--container justify-content-center">

                                    <a class="btn action-btn btn-outline-theme-dark offcanvas-trigger data-info-show"
                                        href="javascript:void(0)" data-id="{{ $category['id'] }}"
                                        data-url="{{ route('admin.category.edit', [$category['id']]) }}"
                                        data-target="#offcanvas__categoryBtn">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                                        href="javascript:" data-id="category-{{ $category['id'] }}"
                                        data-message="{{ translate('Want to delete this main category') }}"
                                        title="{{ translate('messages.delete_main_category') }}"><i
                                            class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.category.delete', [$category['id']]) }}"
                                        method="post" id="category-{{ $category['id'] }}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if (count($categories) !== 0)
        <hr>
    @endif

    @if (count($categories) === 0)
        <div class="empty--data">
            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
            <h5>
                {{ translate('no_data_found') }}
            </h5>
        </div>
    @endif
    <div class="page-area px-4 pb-3">
        <div class="d-flex align-items-center justify-content-end">
            <div>
                {!! $categories->withQueryString()->links() !!}
            </div>
        </div>
    </div>
</div>
