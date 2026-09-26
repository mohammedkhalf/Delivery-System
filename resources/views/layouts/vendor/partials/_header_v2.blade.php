{{--
    v2 Vendor topbar.
    Single-store workspace, so no admin-style workspace tabs or module switcher.
    Topbar = mobile-toggle + view-mode + brand + search + language + messages + fullscreen.
    Profile pop lives in the rail bottom (see _v2_profile_pop_vendor).
--}}
@php
    use App\CentralLogics\Helpers;
    $store_data        = Helpers::get_store_data();
    $logged_in_user    = Helpers::get_loggedin_user();
    $local             = session()->has('vendor_local') ? session('vendor_local') : null;
    $lang_setting      = \App\Models\BusinessSetting::where('key', 'system_language')->first();
    $lang              = $lang_setting ? json_decode($lang_setting->value, true) : [];
    $unread_messages   = \App\Models\Conversation::whereUser($logged_in_user->id ?? 0)->where('unread_message_count', '>', 0)->count();
@endphp

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

<header class="v2-topbar" role="banner">
    <a class="v2-brand" href="{{ route('vendor.dashboard') }}" title="{{ $store_data->name ?? 'Store' }}">
        <img class="v2-brand-mark" src="{{ $store_data?->logo_full_url ?: asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $store_data->name ?? 'Store' }}" onerror="this.src='{{ asset('public/assets/admin/img/160x160/img2.jpg') }}'">
        <span class="v2-brand-name v2-desktop-only">{{ $store_data->name ?? '' }}</span>
    </a>

    <button type="button" class="v2-mode-btn v2-mobile-toggle" id="v2-mobile-toggle" aria-label="{{ translate('Toggle navigation drawer') }}">
        <i data-lucide="menu"></i>
    </button>
    @if($layout_features['compact_mode_toggle'] ?? true)
    <button type="button" class="v2-mode-btn v2-desktop-only" id="v2-mode-btn" title="{{ translate('Toggle view mode') }}" aria-label="{{ translate('Toggle view mode') }}">
        <i data-lucide="{{ session()->get('site_direction') === 'rtl' ? 'panel-right-open' : 'panel-left-open' }}" id="v2-mode-icon"></i>
    </button>
    @endif

    {{-- Builder button — left-aligned (sits before the flex spacer so it stays
         in the left cluster next to the brand, instead of being pushed right). --}}
    @if(Helpers::check_website_builder_status() && Helpers::employee_module_permission_check('custom_website'))
    <a href="{{ route('vendor.builder.index', ['page' => 'global-settings']) }}"
       class="website-builder-btn v2-builder-btn" id="vendor-dashboard-builder-button">
        <span class="website-builder-icon">
            <img src="{{ asset('public/assets/admin/img/builder.svg') }}" alt="">
        </span>
        <span class="v2-desktop-only">{{ translate('Build Your Custom Website') }}</span>
        @if(getEnvMode() == 'demo')
            <span class="v2-builder-addon-tag v2-desktop-only">{{ translate('Addon') }}</span>
        @endif
    </a>
    @endif

    <div class="v2-topbar-spacer"></div>

    <button type="button" class="v2-topbar-search" data-toggle="modal" data-target="#staticBackdrop" aria-label="{{ translate('Search_or') }}">
        <i data-lucide="search"></i>
        <span class="v2-search-placeholder">{{ translate('Search_by_keyword') }}</span>
        <kbd>Ctrl+K</kbd>
    </button>

    <div class="v2-topbar-actions">
        <button type="button" class="v2-icon-btn v2-mobile-only-search" data-toggle="modal" data-target="#staticBackdrop" aria-label="{{ translate('Search_or') }}">
            <i data-lucide="search"></i>
        </button>

        @if($lang)
            <div class="v2-lang-wrap">
                <button type="button" class="v2-icon-btn" id="v2-lang-btn" aria-haspopup="menu" aria-expanded="false" aria-label="{{ translate('Language') }}">
                    <i data-lucide="globe"></i>
                </button>
                <div class="v2-lang-pop" id="v2-lang-pop" role="menu">
                    <div class="v2-lang-pop-head">{{ translate('Language') }}</div>
                    @foreach($lang as $data)
                        @if(($data['status'] ?? 0) == 1)
                            <a class="v2-lang-item {{ ($data['code'] === $local) || (!$local && ($data['default'] ?? false) === true) ? 'is-active' : '' }}" href="{{ route('vendor.lang', [$data['code']]) }}">
                                <span class="v2-lang-code">{{ strtoupper($data['code']) }}</span>
                                <span class="v2-lang-name">{{ ucfirst($data['name'] ?? $data['code']) }}</span>
                                <i data-lucide="check" class="v2-lang-check"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if(Helpers::employee_module_permission_check('chat'))
        <a href="{{ route('vendor.message.list') }}" class="v2-icon-btn" aria-label="{{ translate('messages.message') }}">
            <i data-lucide="message-circle"></i>
            @if($unread_messages > 0)<span class="v2-badge">{{ $unread_messages }}</span>@endif
        </a>
        @endif

        @if($layout_features['fullscreen'] ?? true)
        <button type="button" class="v2-icon-btn v2-desktop-only" id="v2-fs-btn" aria-label="{{ translate('Toggle fullscreen') }}" title="{{ translate('Toggle fullscreen (F11)') }}">
            <i data-lucide="maximize-2" id="v2-fs-icon"></i>
        </button>
        @endif
    </div>
</header>

<div class="v2-mobile-backdrop" id="v2-mobile-backdrop" aria-hidden="true"></div>

@push('script_2')
<script>
(function () {
    'use strict';

    function closePops() {
        document.querySelectorAll('.v2-lang-pop').forEach(function (p) { p.classList.remove('is-open'); });
        var lb = document.getElementById('v2-lang-btn');
        if (lb) lb.setAttribute('aria-expanded', 'false');
    }

    var langBtn = document.getElementById('v2-lang-btn');
    var langPop = document.getElementById('v2-lang-pop');
    if (langBtn && langPop) {
        langBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !langPop.classList.contains('is-open');
            closePops();
            if (open) { langPop.classList.add('is-open'); langBtn.setAttribute('aria-expanded', 'true'); }
        });
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('#v2-lang-pop, #v2-lang-btn')) return;
        closePops();
    });

    // View mode (compact / pinned)
    var modeBtn = document.getElementById('v2-mode-btn');
    var modeIcon = document.getElementById('v2-mode-icon');
    var savedMode = localStorage.getItem('v2_view_mode') || 'pinned';
    // POS auto-compact: force compact mode while on /vendor-panel/pos/* without
    // persisting, so leaving POS restores the user's previous preference.
    var isPosPage = /\/vendor-panel\/pos(\/|$)/.test(window.location.pathname);
    function v2IsRtl() { return (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl'; }
    function v2ModeIcon(mode) {
        var rtl = v2IsRtl();
        if (mode === 'compact') return rtl ? 'panel-right-close' : 'panel-left-close';
        return rtl ? 'panel-right-open' : 'panel-left-open';
    }
    function applyMode(m) {
        document.body.classList.toggle('v2-mode-compact', m === 'compact');
        // Lucide replaces our <i data-lucide=> with an <svg>, so re-inject a
        // fresh placeholder rather than setAttribute on the (now SVG) element.
        var ic = document.getElementById('v2-mode-icon');
        if (ic) {
            ic.outerHTML = '<i data-lucide="' + v2ModeIcon(m) + '" id="v2-mode-icon"></i>';
            modeIcon = document.getElementById('v2-mode-icon');
        }
        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    }
    applyMode(isPosPage ? 'compact' : savedMode);
    if (modeBtn) modeBtn.addEventListener('click', function () {
        var next = document.body.classList.contains('v2-mode-compact') ? 'pinned' : 'compact';
        if (!isPosPage) localStorage.setItem('v2_view_mode', next);
        applyMode(next);
    });

    // Fullscreen
    function v2ToggleFullscreen() {
        var el = document.documentElement;
        var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement);
        if (!isFs) {
            (el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen).call(el);
        } else {
            (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen).call(document);
        }
    }
    function v2SyncFullscreenIcon() {
        var icon = document.getElementById('v2-fs-icon');
        if (!icon) return;
        var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement);
        icon.setAttribute('data-lucide', isFs ? 'minimize-2' : 'maximize-2');
        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    }
    var fsBtn = document.getElementById('v2-fs-btn');
    if (fsBtn) fsBtn.addEventListener('click', v2ToggleFullscreen);
    ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'msfullscreenchange'].forEach(function (ev) {
        document.addEventListener(ev, v2SyncFullscreenIcon);
    });

    // Mobile drawer
    var mobileToggle = document.getElementById('v2-mobile-toggle');
    var backdrop = document.getElementById('v2-mobile-backdrop');
    function setDrawer(open) { document.body.classList.toggle('v2-drawer-open', !!open); }
    if (mobileToggle) mobileToggle.addEventListener('click', function () {
        setDrawer(!document.body.classList.contains('v2-drawer-open'));
    });
    if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
})();
</script>
@endpush
