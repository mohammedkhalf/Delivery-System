{{--
    Demo-only promotional modal for the Website Builder addon.
    Rendered only when APP_MODE=demo (gated by the includer) and only on the
    Builder Inertia root (storefront + setup pages — Inertia is Builder-only
    in this app). Shows on first full page load, then stays silent for 5 minutes
    (localStorage cooldown) so repeated reloads don't re-trigger it.
--}}
<div id="bdpromo-overlay" class="bdpromo-overlay" aria-hidden="true">
    <div class="bdpromo-modal" role="dialog" aria-modal="true" aria-labelledby="bdpromo-title">
        <div class="bdpromo-head">
            <button type="button" id="bdpromo-close" class="bdpromo-close" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
            </button>
            <div class="bdpromo-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>
                </svg>
            </div>
            <h2 id="bdpromo-title" class="bdpromo-title">{{ translate('Build Your Own Website With Website Builder') }}</h2>
            <p class="bdpromo-subtitle">{{ translate('You can now grow your business with 6amMart Website Builder — design a stunning storefront, launch your own branded site, and boost your revenue.') }}</p>
        </div>

        <div class="bdpromo-body">
            <div class="bdpromo-features">
                <div class="bdpromo-feature">
                    <span class="bdpromo-feature-ic">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span>{{ translate('Engage customers with a branded storefront') }}</span>
                </div>
                <div class="bdpromo-feature">
                    <span class="bdpromo-feature-ic">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <span>{{ translate('Drive more sales with your own website') }}</span>
                </div>
                <div class="bdpromo-feature">
                    <span class="bdpromo-feature-ic">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </span>
                    <span>{{ translate('Boost repeat visits and brand loyalty') }}</span>
                </div>
                <div class="bdpromo-feature">
                    <span class="bdpromo-feature-ic">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </span>
                    <span>{{ translate('Launch your site in minutes, no coding') }}</span>
                </div>
            </div>

            <div class="bdpromo-rating">
                <span class="bdpromo-stars">
                    @for($i = 0; $i < 5; $i++)
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    @endfor
                </span>
                <span class="bdpromo-rating-text">{{ translate('Trusted By 3,000+ Vendors') }}</span>
            </div>

            <a href="https://store.6amtech.com/product/6ammart-vendor-website-builder/" target="_blank" rel="noopener noreferrer" class="bdpromo-cta">{{ translate('Get It Now!') }}</a>

            <p class="bdpromo-foot">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                {{ translate('This is an Add-on for 6amMart, purchase the add-on for your system.') }}
            </p>
        </div>
    </div>
</div>

<style>
    /* Palette is driven by the host brand color: prefer a live host CSS var
       (admin-v2 chrome, or the admin style.css :root) and fall back to the
       6amMart brand teal when neither stylesheet is loaded (Builder SPA root). */
    .bdpromo-overlay{--bdpromo-primary:var(--v2-primary,var(--primary-clr,#107980));--bdpromo-primary-deep:var(--v2-primary-deep,var(--primary,#006161));position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);}
    .bdpromo-overlay.bdpromo-show{display:flex;}
    .bdpromo-modal{width:100%;max-width:520px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;animation:bdpromo-in .25s ease;}
    @keyframes bdpromo-in{from{opacity:0;transform:translateY(12px) scale(.98);}to{opacity:1;transform:none;}}
    .bdpromo-head{position:relative;padding:34px 32px 28px;text-align:center;background:linear-gradient(135deg,var(--bdpromo-primary-deep) 0%,var(--bdpromo-primary) 100%);}
    .bdpromo-close{position:absolute;top:16px;right:16px;width:38px;height:38px;border:none;border-radius:50%;background:rgba(255,255,255,.18);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;}
    .bdpromo-close:hover{background:rgba(255,255,255,.32);}
    .bdpromo-icon{width:64px;height:64px;margin:0 auto 14px;border-radius:14px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;}
    .bdpromo-title{margin:0 0 10px;color:#fff;font-size:27px;line-height:1.2;font-weight:800;}
    .bdpromo-subtitle{margin:0;color:rgba(255,255,255,.92);font-size:14.5px;line-height:1.55;}
    .bdpromo-body{padding:24px 28px 22px;}
    .bdpromo-features{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .bdpromo-feature{display:flex;align-items:flex-start;gap:10px;padding:14px;background:#f1f5f9;border-radius:10px;font-size:13.5px;color:#1e293b;line-height:1.4;}
    .bdpromo-feature-ic{flex-shrink:0;width:30px;height:30px;border-radius:7px;background:#fff;display:flex;align-items:center;justify-content:center;}
    .bdpromo-rating{display:flex;align-items:center;justify-content:center;gap:10px;margin:20px 0;}
    .bdpromo-stars{display:inline-flex;gap:2px;}
    .bdpromo-rating-text{color:#475569;font-size:15px;font-weight:600;}
    .bdpromo-cta{display:block;width:100%;padding:15px;border-radius:10px;background:var(--bdpromo-primary);color:#fff;text-align:center;font-size:16px;font-weight:700;text-decoration:none;transition:background .15s;}
    .bdpromo-cta:hover{background:var(--bdpromo-primary-deep);color:#fff;text-decoration:none;}
    .bdpromo-foot{display:flex;align-items:center;justify-content:center;gap:7px;margin:14px 0 0;color:#dc2626;font-size:13px;text-align:center;}
    @media (max-width:480px){.bdpromo-features{grid-template-columns:1fr;}.bdpromo-title{font-size:23px;}}
</style>

<script>
    (function () {
        var KEY = 'builder_demo_promo_last_shown';
        var COOLDOWN = 5 * 60 * 1000; // 5 minutes

        function ready(fn) {
            if (document.readyState !== 'loading') { fn(); }
            else { document.addEventListener('DOMContentLoaded', fn); }
        }

        ready(function () {
            var overlay = document.getElementById('bdpromo-overlay');
            var closeBtn = document.getElementById('bdpromo-close');
            if (!overlay) return;

            function hide() { overlay.classList.remove('bdpromo-show'); }

            // First load shows it; reloads within the cooldown window stay silent.
            var last = parseInt(localStorage.getItem(KEY) || '0', 10);
            var now = Date.now();
            if (!last || (now - last) > COOLDOWN) {
                overlay.classList.add('bdpromo-show');
                try { localStorage.setItem(KEY, String(now)); } catch (e) {}
            }

            if (closeBtn) closeBtn.addEventListener('click', hide);
            overlay.addEventListener('click', function (e) { if (e.target === overlay) hide(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
        });
    })();
</script>
