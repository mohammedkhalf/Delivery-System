<div class="d-flex flex-wrap justify-content-between align-items-start __gap-15px">
    <div class="d-flex flex-wrap align-items-center __gap-5px">
        <h1 class="page-header-title mb-0">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/email-setting.png') }}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('messages.Email Templates') }}
            </span>
        </h1>
        <div class="see-how-it-works text--primary-2 py-1 d-inline-flex align-items-center" type="button" id="see-how-it-works">
            <i class="tio-info-outined"></i>
            <span class="see-how-it-works__text"><strong>{{ translate('See_how_it_works!') }}</strong></span>
        </div>
    </div>
    @include('admin-views.business-settings.email-format-setting.partials.email-template-options', ['moveSeeHowToTitle' => true])
</div>
