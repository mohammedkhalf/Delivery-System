
<div class="js-nav-scroller hs-nav-scroller-horizontal mb-3 mt-2">
    <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
        <li class="nav-item">
            <a class="nav-link text-capitalize {{ request()->routeIs('admin.pro-customer.additional-setup') ? 'active' : '' }}"
                href="{{ route('admin.pro-customer.additional-setup') }}">
                {{ translate('messages.FAQ') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-capitalize {{ request()->routeIs('admin.pro-customer.terms-and-conditions') ? 'active' : '' }}"
                href="{{ route('admin.pro-customer.terms-and-conditions') }}">
                {{ translate('messages.Terms_and_Conditions') }}
            </a>
        </li>
    </ul>
</div>

