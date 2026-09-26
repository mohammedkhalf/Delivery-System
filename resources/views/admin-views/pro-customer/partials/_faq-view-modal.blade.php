<div class="modal fade" id="quick_view_faq_{{ $faq->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-capitalize">{{ translate('messages.FAQ_Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="bg-light2 p-xl-20 p-3 rounded">
                    <div class="mb-3">
                        <label class="input-label fw-500 text-capitalize d-block">{{ translate('messages.Question') }}</label>
                        <p class="mb-0 text-dark">{{ $faq->question }}</p>
                    </div>
                    <hr>
                    <div>
                        <label class="input-label fw-500 text-capitalize d-block">{{ translate('messages.Answer') }}</label>
                        <p class="mb-0 text-dark">{{ $faq->answer }}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--reset text-capitalize" data-dismiss="modal">{{ translate('messages.Close') }}</button>
            </div>
        </div>
    </div>
</div>
