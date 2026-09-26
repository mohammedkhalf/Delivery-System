@extends('layouts.admin.app')

@section('title', translate('messages.Additional Setup'))

@section('content')


<div class="content container-fluid">
    <div class="page-header mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize fs-24">
                <span>
                    {{ translate('Additional Setup') }}
                </span>
            </h1>
        </div>
    </div>
    <div class="js-nav-scroller hs-nav-scroller-horizontal mb-3">
        <ul class="nav nav-tabs border-0 nav--tabs nav--pills nav--theme-version">
            <li class="nav-item">
                <a class="nav-link active" href="">
                    {{ translate('FAQ') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link " href="">
                    {{ translate('Terms & Condition') }}
                </a>
            </li>
        </ul>
    </div>

    @include('admin-views.test.version-four-test.additional-setup.partials._faq-section')
    <!-- Terms & Conditions -->    
    <h1 class="my-4">Terms & Conditions Section</h1>
    @include('admin-views.test.version-four-test.additional-setup.partials._terms-condition-section')
 
</div>

@include('admin-views.test.version-four-test.additional-setup.partials._edit-faq-offcanvas')

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/ckeditor/ckeditor.js')}}"></script>
@endpush
