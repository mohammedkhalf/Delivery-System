@extends('layouts.admin.app')

@section('title', translate('messages.Terms_and_Conditions'))
@section('pro_customer_additional_setup', 'active')

@section('content')
<div class="content container-fluid">
    <div class="page-header mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize fs-24">
                <span>{{ translate('messages.Terms_and_Conditions') }}</span>
            </h1>
        </div>
    </div>

    @include('admin-views.pro-customer.partials._additional-tabs')

    <form action="{{ route('admin.pro-customer.terms-and-conditions.update') }}" method="post" id="pro-terms-form" enctype="multipart/form-data">
        @csrf
        <div class="card card-body">

            {{-- Availability --}}
            <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
                <div class="row g-3 align-items-center">
                    <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                        <div>
                            <h3 class="mb-1">{{ translate('messages.Availability') }}</h3>
                            <p class="mb-0 fs-12">
                                {{ translate('messages.If_you_turn_off_the_availability_status_this_page_will_not_show_in_the_Subscription_Plan') }}
                            </p>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                        <div class="form-group mb-0">
                            <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                <span class="pr-1 d-flex align-items-center switch--label">
                                    <span class="line--limit-1">{{ translate('messages.Status') }}</span>
                                </span>
                                <input type="checkbox" name="page_status" value="1"
                                    class="toggle-switch-input" {{ $termsStatus ? 'checked' : '' }}>
                                <span class="toggle-switch-label text">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Title Background Image --}}
            <div class="mb-20">
                <h5 class="font-medium mb-3">{{ translate('messages.Title_Background_Image') }}</h5>
                <div class="bg-light2 p-xl-4 p-4 rounded">
                    <div class="text-center">
                        @include('admin-views.partials._image-uploader', [
                            'name'          => 'page_image',
                            'id'            => 'pro-terms-image',
                            'existingImage' => $termsImageUrl,
                            'ratio'         => '7:1',
                            'isRequired'    => false,
                            'imageExtension'=> IMAGE_EXTENSION,
                            'imageFormat'   => IMAGE_FORMAT,
                            'maxSize'       => MAX_FILE_SIZE,
                            'textPosition'  => 'bottom',
                            'show_clear_button' => false,
                        ])
                    </div>
                </div>
            </div>

            {{-- Language tabs + fields --}}
            <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
                <div class="card-body p-0">
                    @if ($language)
                        <div class="js-nav-scroller hs-nav-scroller-horizontal">
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link-terms">{{ translate('messages.Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{ $lang }}-link-terms">
                                            {{ \App\CentralLogics\Helpers::get_language_name($lang) . ' (' . strtoupper($lang) . ')' }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <input type="hidden" name="lang[]" value="default">
                    <div class="lang_form" id="default-form-terms">
                        <div class="row g-1">
                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label class="input-label fw-400 text-capitalize" for="default_terms_title">
                                        {{ translate('messages.Page_Title') }} ({{ translate('messages.Default') }})
                                        <span class="text-danger">*</span>
                                        <span data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Type_page_title_within_100_characters') }}">
                                            <i class="tio-info text-muted fs-16"></i>
                                        </span>
                                    </label>
                                    <input type="text" name="page_title[]" id="default_terms_title" maxlength="100"
                                        class="form-control pro-terms-title-input"
                                        placeholder="{{ translate('Pro Customer Terms and Conditions') }}"
                                        value="{{ $termsTitleRow?->getRawOriginal('value') }}" required>
                                    <div class="d-flex justify-content-end">
                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1 pro-terms-title-counter">0/100</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label class="input-label fw-400 text-capitalize">
                                        {{ translate('messages.Page_Description') }} ({{ translate('messages.Default') }})
                                        <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="pro-terms-editor form-control" id="pro-terms-desc-default" name="page_description[]" required>{{ $termsDescRow?->getRawOriginal('value') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($language)
                        @php($termsTranslations = collect())
                        @if ($termsTitleRow)
                            @php($termsTranslations = $termsTranslations->merge(\App\Models\Translation::where('translationable_type', \App\Models\DataSetting::class)->where('translationable_id', $termsTitleRow->id)->get()))
                        @endif
                        @if ($termsDescRow)
                            @php($termsTranslations = $termsTranslations->merge(\App\Models\Translation::where('translationable_type', \App\Models\DataSetting::class)->where('translationable_id', $termsDescRow->id)->get()))
                        @endif

                        @foreach ($language as $lang)
                            @php($titleTrans = $termsTranslations->first(fn($t) => $t->locale === $lang && $t->key === 'pro_terms_page_title'))
                            @php($descTrans = $termsTranslations->first(fn($t) => $t->locale === $lang && $t->key === 'pro_terms_page_description'))
                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                            <div class="d-none lang_form" id="{{ $lang }}-form-terms">
                                <div class="row g-1">
                                    <div class="col-md-12">
                                        <div class="form-group mb-3">
                                            <label class="input-label fw-400 text-capitalize">
                                                {{ translate('messages.Page_Title') }} ({{ strtoupper($lang) }})
                                            </label>
                                            <input type="text" name="page_title[]" maxlength="100" class="form-control"
                                                value="{{ $titleTrans?->value }}">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group mb-0">
                                            <label class="input-label fw-400 text-capitalize">
                                                {{ translate('messages.Page_Description') }} ({{ strtoupper($lang) }})
                                            </label>
                                            <textarea class="pro-terms-editor form-control" id="pro-terms-desc-{{ $lang }}" name="page_description[]">{{ $descTrans?->value }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="btn--container justify-content-end">
                <button type="reset" class="btn min-w-120 btn--reset text-capitalize">{{ translate('messages.Reset') }}</button>
                <button type="submit" class="btn min-w-120 btn--primary text-capitalize">
                    <i class="tio-save"></i> {{ translate('messages.Save_Information') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('css_or_js')
<style>
    .cke_dialog_container,
    .cke_dialog_background_cover,
    .cke_dialog,
    .cke_panel,
    .cke_combopanel { z-index: 2147483640 !important; }
</style>
@endpush

@push('script_2')
<script src="{{ asset('public/assets/admin/ckeditor/ckeditor.js') }}"></script>
<script>
    "use strict";

    const PRO_TERMS_CKEDITOR_CONFIG = {
        toolbarGroups: [
            { name: 'clipboard', groups: ['undo'] },
            { name: 'basicstyles', groups: ['basicstyles'] },
            { name: 'paragraph', groups: ['list', 'align'] },
            { name: 'insert' },
            { name: 'styles' }
        ],
        removeButtons: 'Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Scayt,'
            + 'Anchor,Image,Iframe,Table,HorizontalRule,PageBreak,'
            + 'Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,'
            + 'Strike,Subscript,Superscript,RemoveFormat,Outdent,Indent,Blockquote,CreateDiv,'
            + 'BidiLtr,BidiRtl,Language,Source,Maximize,ShowBlocks,About,Templates,Preview,Print,'
            + 'NewPage,Save,Styles,Font,FontSize,TextColor,BGColor',
        removePlugins: 'elementspath',
        resize_enabled: false,
        height: 220,
        baseFloatZIndex: 2000000000
    };

    const proTermsOriginalContent = {};

    $(document).ready(function () {
        $('.pro-terms-editor').each(function () {
            var id = this.id;
            if (!id) return;
            proTermsOriginalContent[id] = $(this).val();
            if (!CKEDITOR.instances[id]) {
                CKEDITOR.replace(id, PRO_TERMS_CKEDITOR_CONFIG);
            }
        });
    });

    $('#pro-terms-form').on('reset', function () {
        setTimeout(function () {
            Object.keys(proTermsOriginalContent).forEach(function (id) {
                var editor = CKEDITOR.instances[id];
                if (editor) editor.setData(proTermsOriginalContent[id] || '');
            });
        }, 0);
    });

    $('.pro-terms-title-input').on('input', function () {
        $(this).closest('.form-group').find('.pro-terms-title-counter').text($(this).val().length + '/100');
    }).trigger('input');
</script>
@endpush
