{{--
    Variables expected:
      $localeLabel      – display label, e.g. "Default" or "EN"
      $localeKey        – slug used for unique IDs, e.g. "default" or "en"
      $questionRequired – bool, add required attr on question input
      $questionValue    – string, pre-fill value for question input (optional)
      $answerValue      – string, pre-fill value for answer textarea (optional)
      $maxPriority      – int, max value for the priority select
      $selectedPriority – int, pre-selected priority
      $showPriority     – bool, whether to render the priority select
--}}
<div class="row g-2">
    <div class="col-12">
        <div class="form-group mb-3">
            <label class="input-label fw-400 text-capitalize">
                {{ translate('messages.Question') }} ({{ $localeLabel }})
                @if($questionRequired ?? false)<span class="text-danger">*</span>@endif
                @if(($localeKey ?? '') === 'default')
                    <span class="" data-toggle="tooltip" data-placement="right"
                        data-original-title="{{ translate('messages.This_is_the_default_content_shown_when_no_translation_is_available') }}">
                        <i class="tio-info text-muted"></i>
                    </span>
                @endif
            </label>
            <input type="text"
                name="question[]"
                maxlength="150"
                data-locale="{{ $localeKey }}"
                class="form-control pro-faq-question-input"
                placeholder="{{ translate('messages.Enter_FAQ_question') }}"
                value="{{ $questionValue ?? '' }}"
                {{ ($questionRequired ?? false) ? 'required' : '' }}>
            <div class="d-flex justify-content-end">
                <span class="text-body-light text-right d-block mt-1 pro-faq-question-counter">0/150</span>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="form-group mb-0">
            <label class="input-label fw-400 text-capitalize">
                {{ translate('messages.Answer') }} ({{ $localeLabel }})
                @if($questionRequired ?? false)<span class="text-danger">*</span>@endif
                @if(($localeKey ?? '') === 'default')
                    <span class="" data-toggle="tooltip" data-placement="right"
                        data-original-title="{{ translate('messages.This_is_the_default_content_shown_when_no_translation_is_available') }}">
                        <i class="tio-info text-muted"></i>
                    </span>
                @endif
            </label>
            <textarea name="answer[]"
                maxlength="500"
                rows="3"
                data-locale="{{ $localeKey }}"
                class="form-control pro-faq-answer-input"
                placeholder="{{ translate('messages.Enter_FAQ_answer') }}"
                {{ ($questionRequired ?? false) ? 'required' : '' }}>{{ $answerValue ?? '' }}</textarea>
            <div class="d-flex justify-content-end">
                <span class="text-body-light text-right d-block mt-1 pro-faq-answer-counter">0/500</span>
            </div>
        </div>
    </div>
    @if($showPriority ?? false)
    <div class="col-12">
        <div class="form-group mb-0">
            <label class="input-label fw-400 text-capitalize">{{ translate('messages.Priority') }}</label>
            <select name="priority" class="form-control js-select2-custom">
                @for($i = 1; $i <= ($maxPriority ?? 1); $i++)
                    <option value="{{ $i }}" {{ ($selectedPriority ?? 1) == $i ? 'selected' : '' }}>{{ $i }}</option>
                @endfor
            </select>
        </div>
    </div>
    @endif
</div>
