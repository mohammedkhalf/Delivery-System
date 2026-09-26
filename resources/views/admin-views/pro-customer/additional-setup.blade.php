@extends('layouts.admin.app')

@section('title', translate('messages.Additional_Setup'))
@section('pro_customer_additional_setup', 'active')

@section('content')
@php($createMaxPriority = $faqCount + 1)

<div class="content container-fluid">
    <div class="page-header mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize fs-24">
                <span>{{ translate('messages.Additional_Setup') }}</span>
            </h1>
        </div>
    </div>

    @include('admin-views.pro-customer.partials._additional-tabs')

    <div id="pro-faq-section">
        {{-- Card 1: Add FAQ --}}
        <div class="card p-xxl-20 p-3 mb-15">
            <form action="{{ route('admin.pro-customer.faq.store') }}" method="post">
                @csrf
                <h3 class="mb-3 fs-16 text-capitalize">{{ translate('messages.Add_FAQ') }}</h3>
                <div class="bg-light2 p-xl-20 p-3 rounded">
                    <div class="card-body p-0">
                        @if ($language)
                            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                                <ul class="nav nav-tabs mb-4">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#" id="default-link-faq-create">{{ translate('messages.default') }}</a>
                                    </li>
                                    @foreach ($language as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#" id="{{ $lang }}-link-faq-create">
                                                {{ \App\CentralLogics\Helpers::get_language_name($lang) . ' (' . strtoupper($lang) . ')' }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <input type="hidden" name="lang[]" value="default">
                        <div class="lang_form" id="default-form-faq-create">
                            @include('admin-views.pro-customer.partials._faq-fields', [
                                'localeLabel'      => translate('messages.default'),
                                'localeKey'        => 'default',
                                'questionRequired' => true,
                                'maxPriority'      => $createMaxPriority,
                                'selectedPriority' => $createMaxPriority,
                                'showPriority'     => true,
                            ])
                        </div>

                        @if ($language)
                            @foreach ($language as $lang)
                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                                <div class="d-none lang_form" id="{{ $lang }}-form-faq-create">
                                    @include('admin-views.pro-customer.partials._faq-fields', [
                                        'localeLabel'      => strtoupper($lang),
                                        'localeKey'        => $lang,
                                        'questionRequired' => false,
                                        'showPriority'     => false,
                                    ])
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn--reset text-capitalize">{{ translate('messages.Reset') }}</button>
                    <button type="submit" class="btn btn--primary text-capitalize">{{ translate('messages.Add') }}</button>
                </div>
            </form>
        </div>

        {{-- Card 2: FAQ List --}}
        <div id="pro-faq-list" class="card p-xxl-20 p-3">
            <div class="search--button-wrapper mb-3">
                <h5 class="card-title d-flex align-items-center text-capitalize mb-0">
                    {{ translate('messages.FAQ_List') }}
                    <span class="badge badge-soft-dark ml-2">{{ $faqs->total() }}</span>
                </h5>
                <form class="search-form" method="get" action="{{ route('admin.pro-customer.additional-setup') }}">
                    <div class="input-group input--group">
                        <input id="datatableSearch_" type="search" name="search"
                            value="{{ request()->search ?? '' }}" class="form-control"
                            placeholder="{{ translate('messages.Search_Here') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="table-responsive datatable-custom py-0">
                <table class="table table-borderless table-thead-borderless table-align-middle table-nowrap card-table">
                    <thead class="thead-light border-0">
                        <tr>
                            <th class="border-top-0 text-capitalize">{{ translate('messages.SL') }}</th>
                            <th class="border-top-0 text-capitalize">{{ translate('messages.Question') }}</th>
                            <th class="border-top-0 text-capitalize">{{ translate('messages.Answer') }}</th>
                            <th class="border-top-0 text-capitalize">{{ translate('messages.Priority') }}</th>
                            <th class="border-top-0 text-capitalize">{{ translate('messages.Status') }}</th>
                            <th class="text-center border-top-0 text-capitalize">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($faqs as $k => $faq)
                            <tr>
                                <td>{{ $k + $faqs->firstItem() }}</td>
                                <td>
                                    <div class="text--title word-break min-w-100px line-limit-2 max-w-220px text-wrap">{{ $faq->question }}</div>
                                </td>
                                <td>
                                    <div class="word-break min-w-170px line-limit-3 max-w-450px text-wrap">{{ $faq->answer }}</div>
                                </td>
                                <td>{{ $faq->priority }}</td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm mb-0">
                                        <input type="checkbox" class="toggle-switch-input pro-faq-status-toggle"
                                            data-url-on="{{ route('admin.pro-customer.faq.status', [$faq->id, 1]) }}"
                                            data-url-off="{{ route('admin.pro-customer.faq.status', [$faq->id, 0]) }}"
                                            {{ $faq->status ? 'checked' : '' }}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary"
                                            href="javascript:"
                                            data-toggle="modal"
                                            data-target="#quick_view_faq_{{ $faq->id }}"
                                            title="{{ translate('messages.Quick_View') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                        <a class="btn action-btn btn--primary btn-outline-primary offcanvas-trigger"
                                            href="javascript:"
                                            data-target="#offcanvas__editfaq-{{ $faq->id }}"
                                            title="{{ translate('messages.Edit') }}">
                                            <i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                                            href="javascript:"
                                            data-id="pro-faq-delete-{{ $faq->id }}"
                                            data-message="{{ translate('messages.Want_to_delete_this_FAQ') }}?"
                                            title="{{ translate('messages.Delete') }}">
                                            <i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{ route('admin.pro-customer.faq.delete', $faq->id) }}" method="post"
                                            id="pro-faq-delete-{{ $faq->id }}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($faqs->isEmpty())
                <div class="empty--data text-center py-5 my-4 bg-light2 rounded">
                    <img src="{{ asset('public/assets/admin/img/no-data.png') }}" alt="empty"
                        style="max-width:140px;height:auto;" class="mb-3">
                    <h5 class="fs-16 mb-1 text-capitalize">{{ translate('messages.No_FAQs_Yet') }}</h5>
                    <p class="fs-12 gray-dark mb-0">{{ translate('messages.Add_your_first_FAQ_above_to_help_pro_customers_understand_the_program.') }}</p>
                </div>
            @endif
            <div class="page-area mt-3">{!! $faqs->links() !!}</div>
        </div>
    </div>
</div>

@foreach($faqs as $faq)
    @include('admin-views.pro-customer.partials._faq-edit-offcanvas', ['faq' => $faq, 'language' => $language, 'faqCount' => $faqCount])
    @include('admin-views.pro-customer.partials._faq-view-modal', ['faq' => $faq])
@endforeach
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

@push('script_2')
<script>
    "use strict";

    function bindCounter(scope) {
        $(scope).find('.pro-faq-question-input').off('input.proFaq').on('input.proFaq', function () {
            $(this).closest('.form-group').find('.pro-faq-question-counter').text($(this).val().length + '/150');
        }).trigger('input.proFaq');
        $(scope).find('.pro-faq-answer-input').off('input.proFaq').on('input.proFaq', function () {
            $(this).closest('.form-group').find('.pro-faq-answer-counter').text($(this).val().length + '/500');
        }).trigger('input.proFaq');
    }

    $(function () {
        bindCounter('#pro-faq-list');
        $('[id^="offcanvas__editfaq-"]').each(function () {
            bindCounter(this);
        });

        // Intercept search form — append fragment so browser auto-scrolls after reload
        $(document).on('submit', '.search-form', function (e) {
            e.preventDefault();
            var params = $(this).serialize();
            window.location.href = $(this).attr('action') + (params ? '?' + params : '') + '#pro-faq-list';
        });

        // Intercept pagination links — append fragment
        $(document).on('click', '.page-area a[href]', function () {
            var href = $(this).attr('href');
            if (href && href !== '#' && href.indexOf('#') === -1) {
                this.href = href + '#pro-faq-list';
            }
        });
    });

    $(document).on('change', '.pro-faq-status-toggle', function () {
        window.location.href = this.checked ? $(this).data('url-on') : $(this).data('url-off');
    });
</script>
@endpush
