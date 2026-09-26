<div id="offcanvas__editfaq-{{ $faq->id }}" class="custom-offcanvas d-flex flex-column" style="--offcanvas-width: 480px">
    <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center flex-shrink-0">
        <div class="px-3 py-3 d-flex justify-content-between w-100">
            <h3 class="mb-0 fs-18 text-title fw-500 text-capitalize">{{ translate('messages.Edit_FAQ') }}</h3>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
        </div>
    </div>

    <form id="pro-faq-edit-form-{{ $faq->id }}"
          action="{{ route('admin.pro-customer.faq.update', $faq->id) }}"
          method="post"
          class="d-flex flex-column flex-grow-1 overflow-hidden">
        @csrf
        @method('put')
        <div class="custom-offcanvas-body p-20 overflow-auto flex-grow-1">
            <div class="bg-light2 p-xl-20 p-3 rounded">
                @if($language)
                    <div class="js-nav-scroller hs-nav-scroller-horizontal">
                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link lang_link active" href="#"
                                   id="default-link-faq-edit-{{ $faq->id }}">{{ translate('messages.Default') }}</a>
                            </li>
                            @foreach($language as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link" href="#"
                                       id="{{ $lang }}-link-faq-edit-{{ $faq->id }}">
                                        {{ \App\CentralLogics\Helpers::get_language_name($lang) . ' (' . strtoupper($lang) . ')' }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <input type="hidden" name="lang[]" value="default">
                <div class="lang_form" id="default-form-faq-edit-{{ $faq->id }}">
                    @include('admin-views.pro-customer.partials._faq-fields', [
                        'localeLabel'      => translate('messages.Default'),
                        'localeKey'        => 'default',
                        'questionRequired' => true,
                        'questionValue'    => $faq->getRawOriginal('question'),
                        'answerValue'      => $faq->getRawOriginal('answer'),
                        'showPriority'     => true,
                        'maxPriority'      => $faqCount,
                        'selectedPriority' => $faq->priority,
                    ])
                </div>

                @if($language)
                    @foreach($language as $lang)
                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                        <div class="d-none lang_form" id="{{ $lang }}-form-faq-edit-{{ $faq->id }}">
                            @include('admin-views.pro-customer.partials._faq-fields', [
                                'localeLabel'      => strtoupper($lang),
                                'localeKey'        => $lang,
                                'questionRequired' => false,
                                'questionValue'    => $faq->translations->where('key', 'pro_faq_question')->where('locale', $lang)->first()?->value ?? '',
                                'answerValue'      => $faq->translations->where('key', 'pro_faq_answer')->where('locale', $lang)->first()?->value ?? '',
                                'showPriority'     => false,
                            ])
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="align-items-center bg-white d-flex gap-3 justify-content-center offcanvas-footer p-3 flex-shrink-0 border-top">
            <button type="button" class="btn w-100 btn--reset offcanvas-close text-capitalize">{{ translate('messages.Cancel') }}</button>
            <button type="submit" class="btn w-100 btn--primary text-capitalize">{{ translate('messages.Update') }}</button>
        </div>
    </form>
</div>
