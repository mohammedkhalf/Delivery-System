@extends('layouts.admin.app')

@section('title', translate('messages.settings'))

@section('3rd_party')
    active
@endsection
@section('openAI')
    active
@endsection

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-robot"></i>
                </span>
                <span>{{ translate('OpenAI_Configuration') }}
                </span>
            </h1>
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-5 mt-4 __gap-12px">
                <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                    <!-- Nav -->
                    <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
                        <li class="nav-item">
                            <a class="nav-link   {{ Request::is('admin/business-settings/open-ai') ? 'active' : '' }}"
                                href="{{ route('admin.business-settings.openAI') }}"
                                aria-disabled="true">{{ translate('AI Configuration') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('admin/business-settings/open-ai-settings') ? 'active' : '' }}"
                                href="{{ route('admin.business-settings.openAISettings') }}"
                                aria-disabled="true">{{ translate('AI Settings') }}</a>
                        </li>
                    </ul>
                    <!-- End Nav -->
                </div>
            </div>
        </div>
        <!-- End Page Header -->


        <div class="col-12">

            <form action="{{ route('admin.business-settings.openAISettingsUpdate') }}" method="post">
                @csrf
                @method('put')

                @if (addon_published_status('AI'))
                    @php($ai_chat_status = $data['ai_chat_status'] ?? 0)
                    <div class="card mt-2">
                        <div class="card-body p-20">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1">
                                        <span class="page-header-icon mr-1">
                                            <i class="tio-robot"></i>
                                        </span>
                                        {{ translate('AI Chat Agent') }}
                                    </h4>
                                    <p class="fs-12 m-0">
                                        {{ translate('Allow customers to use the AI chat assistant from the customer app and web.') }}
                                    </p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-3">
                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox"
                                                data-id="ai_chat_status" data-type="toggle"
                                                data-image-on="{{ asset('/public/assets/admin/img/modal/mail-success.png') }}"
                                                data-image-off="{{ asset('/public/assets/admin/img/modal/mail-warning.png') }}"
                                                data-title-on="<strong>{{ translate('Turn on the AI Chat Agent?') }}</strong>"
                                                data-title-off="<strong>{{ translate('Turn off the AI Chat Agent?') }}</strong>"
                                                data-text-on="<p>{{ translate('Customers will be able to ask questions and get conversational replies from the AI assistant on both the customer app and the website. A valid OpenAI configuration is required (see the AI Configuration tab); without it, requests will fail even when this toggle is on.') }}</p>"
                                                data-text-off="<p>{{ translate('The chat assistant will be unavailable to customers on both the app and the website, and all AI chat API requests will be rejected. Existing conversations are kept and will reappear once you turn the agent back on.') }}</p>"
                                                class="status toggle-switch-input dynamic-checkbox-toggle"
                                                name="ai_chat_status" id="ai_chat_status" value="1"
                                                {{ $ai_chat_status == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card mt-2">
                    <div class="card-header card-header-shadow">
                        <h5 class="card-title">
                            <span>
                                <span class="page-header-icon">
                                    <i class="tio-robot"></i>
                                </span>
                                {{ translate('Vendor_limits_on_using_AI') }}
                            </span>

                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="py-2">
                            <div class="row g-3 align-items-end">

                                <div class="align-self-center  col-4">
                                    <div class="text-left">
                                        <h4 class="align-items-center">
                                            <span>
                                                {{ translate('Section_wise_data_generation') }}
                                            </span>
                                        </h4>
                                        <p>
                                            {{ translate('Set how many times  AI can generate data for each element of the vendor panel or app') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-8">
                                    <div class="card __bg-F8F9FC-card text-left">
                                        <div class="card-body">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="section_wise_ai_limit">
                                                    {{ translate('Section_wise_data_generation_limit') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input id="section_wise_ai_limit" type="number" min="0" required
                                                    max="99999999999" class="form-control" name="section_wise_ai_limit"
                                                    value="{{ $data['section_wise_ai_limit'] ?? '' }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 align-items-end">
                                <div class="align-self-center  col-4">
                                    <div class="text-left">
                                        <h4 class="align-items-center">
                                            <span>
                                                {{ translate('Image_based_data_generation') }}
                                            </span>
                                        </h4>
                                        <p>
                                            {{ translate('Set how many times AI can generate data from an image upload ') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-8">
                                    <div class="card __bg-F8F9FC-card text-left">
                                        <div class="card-body">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="image_upload_limit_for_ai">
                                                    {{ translate('Image_upload_generation_limit') }}
                                                     <span class="text-danger">*</span>
                                                </label>
                                                <input id="image_upload_limit_for_ai" type="number" min="0" required
                                                    max="99999999999" class="form-control" name="image_upload_limit_for_ai"
                                                    value="{{ $data['image_upload_limit_for_ai'] ?? '' }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                @include('admin-views.partials._floating-submit-button')
            </form>
        </div>
    </div>
@endsection

