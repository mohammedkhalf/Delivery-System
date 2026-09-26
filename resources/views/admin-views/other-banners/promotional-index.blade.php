@extends('layouts.admin.app')

@section('title', translate('messages.banner'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/3rd-party.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.Other_Promotional_Content_Setup')}}
            </span>
        </h1>
    </div>
    <!-- End Page Header -->
    @include('admin-views.other-banners.partial._bottom-section-banner')
</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/other-banners.js"></script>
    <script>
        $('#reset_btn').click(function () {
            $('#viewer').attr('src', '{{asset('/public/assets/admin/img/upload-placeholder.png')}}');
        })
    </script>
@endpush
