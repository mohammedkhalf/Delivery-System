@extends('layouts.vendor.app')

@section('title', translate('messages.Main_Category'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/category.png') }}" class="w--20" alt="">
                </span>
                <span>
                    {{ translate('messages.Main Category List') }} <span class="badge badge-soft-dark ml-2"
                        id="itemCount">{{ $categories->total() }}</span>
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper justify-content-end">
                            <form class="search-form">

                                <!-- Search -->
                                <div class="input-group input--group">
                                    <input type="search" value="{{ request()?->search ?? null }}" name="search"
                                        class="form-control min-h-40px"
                                        placeholder="{{ translate('messages.search_main_categories') }}"
                                        aria-label="{{ translate('messages.ex_:_categories') }}">
                                    <button type="submit" class="btn btn--secondary py-2 min-h-40px"><i
                                            class="tio-search"></i></button>
                                </div>
                                <!-- End Search -->
                            </form>
                            <!-- Unfold -->
                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px"
                                    href="javascript:"
                                    data-hs-unfold-options='{
                                        "target": "#usersExportDropdown",
                                        "type": "css-animation"
                                    }'>
                                    <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                                </a>

                                <div id="usersExportDropdown"
                                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                                    <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                                    <a id="export-excel" class="dropdown-item"
                                        href="{{ route('vendor.category.export-categories', ['type' => 'excel', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                            alt="Image Description">
                                        {{ translate('messages.excel') }}
                                    </a>
                                    <a id="export-csv" class="dropdown-item"
                                        href="{{ route('vendor.category.export-categories', ['type' => 'csv', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
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
                                class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                                data-hs-datatables-options='{
                                    "search": "#datatableSearch",
                                    "entries": "#datatableEntries",
                                    "isResponsive": false,
                                    "isShowPaging": false,
                                    "paging":false,
                                }'>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="w-33p px-4 border-0">{{ translate('messages.#') }}</th>
                                        <th class="w-33p border-0">
                                            {{ translate('messages.Main_Category_Name') }}
                                        </th>

                                        @if ($categoryWiseTax)
                                            <th class="border-0 ">{{ translate('messages.Vat/Tax') }}</th>
                                        @endif
                                        <th class="w-33p border-0 text-center">
                                            {{ translate('messages.priority') }}
                                        </th>
                                    </tr>
                                </thead>

                                <tbody id="table-div">
                                    @foreach ($categories as $key => $category)
                                        <tr>
                                            <td class="px-4">{{ $key + $categories->firstItem() }}</td>
                                            <td class="">
                                                <div class="media-area d-flex gap-2 align-items-center">
                                                    <div class="w-40px min-w-40 h-40px rounded overflow-hidden border">
                                                        <img src="{{  $category['image_full_url'] }}" alt="" class="w-100 rounded object-cover">
                                                    </div>
                                                    <div>
                                                        <span class="fs-14 line--limit-2 text-title max-w-250 min-w-160">
                                                            {{ Str::limit($category['name'], 20, '...') }}
                                                        </span>
                                                        <p class="m-0">{{ translate('ID') }} #{{ $category->id }}</p>
                                                    </div>
                                                </div>

                                            </td>



                                            @if ($categoryWiseTax)
                                            <td>
                                                <span class="d-block font-size-sm text-body">
                                                    @forelse ($category?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $tax)
                                                        <span class="bg-light rounded py-2 px-3">
                                                             {{ $tax }} :
                                                             <span class="font-light">
                                                                ({{ $key }}%)
                                                            </span>
                                                        </span>
                                                        <br>
                                                    @empty
                                                        <span> {{ translate('messages.no_tax') }} </span>
                                                    @endforelse
                                                </span>
                                            </td>
                                            @endif
                                            <td class="px-4 text-center">
                                                <span class="d-inline-block {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                                                    @if ($category->priority == 2)
                                                        {{ translate('messages.high') }}
                                                    @elseif ($category->priority == 1)
                                                        {{ translate('messages.medium') }}
                                                    @else
                                                        {{ translate('messages.normal') }}
                                                    @endif
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer page-area">
                        <!-- Pagination -->
                        {!! $categories->links() !!}
                        <!-- Pagination -->
                        @if (count($categories) === 0)
                            <div class="empty--data">
                                <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                                <h5>
                                    {{ translate('no_data_found') }}
                                </h5>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>


    </div>

    <div id="offcanvas__categoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

