{{--
    v2 admin chrome tour — driver.js based.
    Auto-starts once per browser (localStorage.v2_tour_seen_v1).
    Re-triggerable via window.startV2Tour() — wired to the existing
    `.restart-Tour` link in _header.blade.php (toggle-tour panel).

    Mobile (<720px) gets a different step list:
      - Skips desktop-only chrome (compact mode, desktop search bar)
      - Adds steps for the hamburger toggle and the mobile search button
      - For steps that target elements inside the slide-in drawer (rail,
        panel, pin, profile avatar) the drawer is opened automatically
        on highlight and closed again on deselect

    Step list is also config-aware: pin and compact-mode steps are skipped
    when those features are disabled in config/layout.php.
--}}
@php
    $tour_features   = config('layout.features', []);
    $tour_pin        = $tour_features['pin'] ?? true;
    $tour_compact    = $tour_features['compact_mode_toggle'] ?? true;
    $tour_fullscreen = $tour_features['fullscreen'] ?? true;
    $tour_rideshare  = addon_published_status('RideShare');
@endphp

{{-- driver.js v1 (~5kb min, MIT) — same CDN strategy as Lucide --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>

<script>
(function () {
    'use strict';

    var STORAGE_KEY = 'v2_tour_seen_v1';
    var MOBILE_MQ = '(max-width: 720px)';

    function el(selector) {
        return document.querySelector(selector);
    }
    function isMobile() {
        return window.matchMedia(MOBILE_MQ).matches;
    }
    function setDrawerOpen(open) {
        document.body.classList.toggle('v2-drawer-open', !!open);
    }
    function isDrawerOpen() {
        return document.body.classList.contains('v2-drawer-open');
    }

    // Step hooks that open the mobile drawer before highlighting an element
    // inside the rail/panel and leave it open until a non-drawer step is
    // reached. The driver.js `onHighlightStarted` / `onDeselected` hooks fire
    // once per step transition, giving us a clean place to manage the state.
    function drawerStepHooks() {
        return {
            onHighlightStarted: function () {
                if (isMobile() && !isDrawerOpen()) setDrawerOpen(true);
            }
        };
    }
    function nonDrawerStepHooks() {
        return {
            onHighlightStarted: function () {
                if (isMobile() && isDrawerOpen()) setDrawerOpen(false);
            }
        };
    }

    // Build the list of steps for the current viewport. Skips steps whose
    // target element isn't present, or that don't make sense on this viewport.
    function defineSteps() {
        var mobile = isMobile();
        var steps = [];

        // 1. Welcome
        steps.push({
            element: '.v2-brand',
            popover: {
                title: '{{ translate('Welcome to the new admin') }}',
                description: '{{ translate('We have redesigned the layout. Here is a quick tour of what changed — it takes under a minute.') }}',
                side: 'bottom', align: 'start'
            },
            ...nonDrawerStepHooks()
        });

        // 2. Workspace tabs (on mobile these live in a horizontal strip
        //    below the topbar; on desktop they're inline in the topbar)
        steps.push({
            element: '.v2-topnav',
            popover: {
                title: '{{ translate('Workspaces') }}',
                description: '{{ translate('Switch between Module, Users, Finance, Reports, Dispatch and Settings from here.') }}',
                side: 'bottom', align: mobile ? 'start' : 'center'
            },
            ...nonDrawerStepHooks()
        });

        // 3. Active module pill
        if (el('.v2-ws-tab--module')) {
            steps.push({
                element: '.v2-ws-tab--module',
                popover: {
                    title: '{{ translate('Active module') }}',
                    description: '{{ translate('The Module tab shows what you are currently working in.') }}',
                    side: 'bottom', align: 'start'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 4. Module switcher
        if (el('#v2-mod-switch')) {
            steps.push({
                element: '#v2-mod-switch',
                popover: {
                    title: '{{ translate('Module switcher') }}',
                    description: '{{ translate('Switch between Grocery, Food, Pharmacy, Parcel and other business modules from here.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 5. (mobile only) Hamburger button — how to reach the sidebar
        if (mobile && el('#v2-mobile-toggle')) {
            steps.push({
                element: '#v2-mobile-toggle',
                popover: {
                    title: '{{ translate('Open the sidebar') }}',
                    description: '{{ translate('Tap the menu icon any time to slide the sidebar in or out.') }}',
                    side: 'bottom', align: 'start'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 6. Rail (drawer opens automatically on mobile)
        if (el('#v2-rail')) {
            steps.push({
                element: '#v2-rail',
                popover: {
                    title: '{{ translate('The rail') }}',
                    description: '{{ translate('Each workspace has sections. Click an icon to switch panels.') }}',
                    side: mobile ? 'bottom' : 'right',
                    align: 'start'
                },
                ...drawerStepHooks()
            });
        }

        // 7. Panel
        if (el('#v2-panel')) {
            steps.push({
                element: '#v2-panel',
                popover: {
                    title: '{{ translate('The panel') }}',
                    description: '{{ translate('Navigation items live here. The active item stays highlighted. Click a chevron to collapse a group.') }}',
                    side: mobile ? 'bottom' : 'right',
                    align: 'start'
                },
                ...drawerStepHooks()
            });
        }

        // 8. Pinning (config-gated)
        @if($tour_pin)
        if (true) {
            var pinBtn = document.querySelector('.v2-panel-content:not([hidden]) .v2-pin')
                      || document.querySelector('.v2-pin');
            if (pinBtn) {
                steps.push({
                    element: pinBtn,
                    popover: {
                        title: '{{ translate('Pinning') }}',
                        description: '{{ translate('Tap the pin on any item to add a shortcut at the top of this panel.') }}',
                        side: mobile ? 'bottom' : 'right',
                        align: 'start'
                    },
                    ...drawerStepHooks()
                });
            }
        }
        @endif

        // 9. Search — desktop uses the topbar search bar; mobile uses the
        //    smaller search icon next to language.
        var searchBar = mobile
            ? el('.v2-mobile-only-search')
            : el('.v2-topbar-search');
        if (searchBar) {
            steps.push({
                element: searchBar,
                popover: {
                    title: '{{ translate('Search') }}',
                    description: mobile
                        ? '{{ translate('Tap the search icon to jump to any admin page by keyword.') }}'
                        : '{{ translate('Press Ctrl+K (or ⌘+K) to jump to any admin page by keyword.') }}',
                    side: 'bottom', align: 'center'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 10. Language
        if (el('#v2-lang-btn')) {
            steps.push({
                element: '#v2-lang-btn',
                popover: {
                    title: '{{ translate('Language') }}',
                    description: '{{ translate('Switch the admin UI language.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 11. Notifications (message icon)
        var msgBell = document.querySelector('a.v2-icon-btn[href*="message"]')
                   || document.querySelector('.v2-topbar-actions .v2-icon-btn');
        if (msgBell) {
            steps.push({
                element: msgBell,
                popover: {
                    title: '{{ translate('Notifications') }}',
                    description: '{{ translate('Messages and safety alerts show up here.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 12. Safety alerts (only when RideShare addon is published)
        @if($tour_rideshare)
        var safetyLink = el('#v2-safety-link');
        if (safetyLink) {
            steps.push({
                element: safetyLink,
                popover: {
                    title: '{{ translate('Safety alerts') }}',
                    description: '{{ translate('Live ride-share safety alerts surface here. The badge shows how many are pending.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }
        @endif

        // 13. (desktop only) Compact mode toggle — hidden on mobile
        @if($tour_compact)
        if (!mobile) {
            var modeBtn = el('#v2-mode-btn');
            if (modeBtn) {
                steps.push({
                    element: modeBtn,
                    popover: {
                        title: '{{ translate('Compact mode') }}',
                        description: '{{ translate('Toggle compact mode to hide the panel and hover-to-peek when you need more screen space.') }}',
                        side: 'bottom', align: 'start'
                    },
                    ...nonDrawerStepHooks()
                });
            }
        }
        @endif

        // 14. (desktop only) Fullscreen toggle
        @if($tour_fullscreen)
        if (!mobile) {
            var fsBtn = el('#v2-fs-btn');
            if (fsBtn) {
                steps.push({
                    element: fsBtn,
                    popover: {
                        title: '{{ translate('Fullscreen') }}',
                        description: '{{ translate('Enter fullscreen for an immersive view. Press F11 or click this button to toggle.') }}',
                        side: 'bottom', align: 'end'
                    },
                    ...nonDrawerStepHooks()
                });
            }
        }
        @endif

        // 13. Profile avatar (lives at the bottom of the rail — drawer opens
        //     automatically on mobile so it's reachable)
        var profileBtn = el('#v2-rail-profile');
        if (profileBtn) {
            steps.push({
                element: profileBtn,
                popover: {
                    title: '{{ translate('Profile') }}',
                    description: '{{ translate('Open your profile, settings or log out from the avatar at the bottom of the rail.') }}',
                    side: mobile ? 'top' : 'right',
                    align: mobile ? 'start' : 'end'
                },
                ...drawerStepHooks()
            });
        }

        return steps;
    }

    function markSeen() {
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
        // Always tidy up the drawer after the tour ends on mobile so we don't
        // leave it open in the user's face.
        if (isMobile()) setDrawerOpen(false);
    }

    function buildDriver() {
        if (typeof window.driver === 'undefined' || !window.driver.js) {
            return null;
        }
        var mobile = isMobile();
        return window.driver.js.driver({
            showProgress: true,
            allowClose: true,
            stagePadding: mobile ? 4 : 6,
            stageRadius: 10,
            smoothScroll: true,
            popoverClass: 'v2-tour-popover',
            nextBtnText: '{{ translate('Next') }}',
            prevBtnText: '{{ translate('Back') }}',
            doneBtnText: '{{ translate('Done') }}',
            steps: defineSteps(),
            onDestroyed: markSeen
        });
    }

    window.startV2Tour = function (opts) {
        opts = opts || {};
        var inst = buildDriver();
        if (!inst) return;
        if (opts.restart) {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
        }
        inst.drive();
    };

    // First-visit auto-start. Only on v2 chrome, only if not seen.
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('v2-chrome')) return;

        // "Replay tour" entry — wired from the legacy floating tour panel
        // (.restart-Tour link) and from the v2 profile popover (.v2-replay-tour).
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.v2-replay-tour');
            if (!trigger) return;
            e.preventDefault();
            // Close the profile popover so the tour can target rail elements.
            var pop = document.getElementById('v2-profile-pop');
            if (pop) pop.classList.remove('is-open');
            window.startV2Tour({ restart: true });
        });

        var seen = false;
        try { seen = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) {}
        if (seen) return;
        // Wait a tick so Lucide icons + rail panels finish rendering.
        setTimeout(function () { window.startV2Tour(); }, 600);
    });
})();
</script>
