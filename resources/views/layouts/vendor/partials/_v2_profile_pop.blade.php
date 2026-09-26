{{-- Vendor v2 profile popover (rail bottom). --}}
@php $vendor_user = \App\CentralLogics\Helpers::get_loggedin_user(); @endphp
<div class="v2-profile-pop" id="v2-profile-pop" role="menu">
    <div class="v2-profile-pop-head">
        <span class="v2-avatar v2-avatar--lg">{{ strtoupper(substr($vendor_user->f_name ?? 'V', 0, 1) . substr($vendor_user->l_name ?? '', 0, 1)) }}</span>
        <div class="v2-meta">
            <div class="v2-name">{{ trim(($vendor_user->f_name ?? '') . ' ' . ($vendor_user->l_name ?? '')) ?: 'Vendor' }}</div>
            <div class="v2-email">{{ $vendor_user->email ?? '' }}</div>
        </div>
    </div>
    <a class="v2-profile-pop-item" href="{{ route('vendor.profile.view') }}">
        <i data-lucide="user-cog"></i><span>{{ translate('messages.settings') }}</span>
    </a>
    <button type="button" class="v2-profile-pop-item v2-profile-pop-item--danger log-out">
        <i data-lucide="log-out"></i><span>{{ translate('messages.sign_out') }}</span>
    </button>
</div>
