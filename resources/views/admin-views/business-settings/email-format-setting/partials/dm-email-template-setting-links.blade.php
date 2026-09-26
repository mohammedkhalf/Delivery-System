<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-2 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <!-- Nav -->
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/registration') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','registration']) }}">
                {{ \App\CentralLogics\Helpers::formatDeliverymanText(translate('New_Deliveryman_Registration'), null, true) }}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','approve']) }}">
                {{ \App\CentralLogics\Helpers::formatDeliverymanText(translate('New_Deliveryman_Approval'), null, true) }}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','deny']) }}">
                {{ \App\CentralLogics\Helpers::formatDeliverymanText(translate('New_Deliveryman_Rejection'), null, true) }}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/suspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','suspend']) }}">
                    {{translate('Account_Suspension')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/unsuspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','unsuspend']) }}">
                    {{translate('Account_Unsuspension')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/cash-collect') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','cash-collect']) }}">
                    {{translate('Cash_Collection')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/forgot-password') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','forgot-password']) }}">
                    {{translate('Forgot_Password')}}
                </a>
            </li>
              <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/withdraw-approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','withdraw-approve']) }}">
                    {{translate('Withdraw_Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/dm/withdraw-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['dm','withdraw-deny']) }}">
                    {{translate('Withdraw_Rejection')}}
                </a>
            </li>
        </ul>
        <!-- End Nav -->
    </div>
    <div class="arrow-area">
        <div class="button-prev align-items-center">
            <button type="button"
                class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                <i class="tio-chevron-left fs-24"></i>
            </button>
        </div>
        <div class="button-next align-items-center">
            <button type="button"
                class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                <i class="tio-chevron-right fs-24"></i>
            </button>
        </div>
    </div>
</div>
