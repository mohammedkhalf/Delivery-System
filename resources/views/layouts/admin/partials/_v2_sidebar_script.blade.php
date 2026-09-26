{{--
    Shared v2 sidebar JS. Reads the workspace key from #v2-shell[data-workspace]
    so a single script powers grocery/food/pharmacy/.../users/finance/dispatch/settings.
    Pin keys are scoped per workspace::panel so pins don't bleed across workspaces.
--}}
@push('script_2')
<script>
(function () {
    'use strict';

    var shell = document.getElementById('v2-shell');
    if (!shell) return;
    var workspace = shell.dataset.workspace || 'module';
    var rail = document.getElementById('v2-rail');
    var panel = document.getElementById('v2-panel');
    if (!rail || !panel) return;

    var PIN_KEY = 'v2_pins_v1';
    var COLLAPSED_KEY = 'v2_collapsed_v1';
    var PARENTS_KEY = 'v2_parents_v1';

    function load(k) { try { return JSON.parse(localStorage.getItem(k) || '{}'); } catch (e) { return {}; } }
    function save(k, v) { localStorage.setItem(k, JSON.stringify(v)); }

    var pins = load(PIN_KEY);
    var collapsed = load(COLLAPSED_KEY);
    var parentsState = load(PARENTS_KEY);

    function pinKeyFor(panelId) { return workspace + '::' + panelId; }

    function activateRailSection(btn) {
        var sect = btn.dataset.section;
        rail.querySelectorAll('.v2-rail-btn[data-section]').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
        });
        panel.querySelectorAll('.v2-panel-content').forEach(function (p) {
            if (p.dataset.panel === sect) p.removeAttribute('hidden');
            else p.setAttribute('hidden', '');
        });
    }

    rail.addEventListener('click', function (e) {
        var btn = e.target.closest('.v2-rail-btn[data-section]');
        if (!btn) return;
        activateRailSection(btn);
    });

    // Hover-to-switch: as soon as the cursor enters a rail icon, switch the
    // panel to that section so the user can scan menus without clicking.
    // Touch devices won't fire mouseenter so the click handler still covers
    // tap interactions; if both fire (some hybrid devices) the call is
    // idempotent.
    var hoverSwitchTimer = null;
    rail.addEventListener('mouseover', function (e) {
        var btn = e.target.closest('.v2-rail-btn[data-section]');
        if (!btn) return;
        // Small debounce so dragging the cursor across icons doesn't
        // thrash through every section's panel render.
        if (hoverSwitchTimer) clearTimeout(hoverSwitchTimer);
        hoverSwitchTimer = setTimeout(function () { activateRailSection(btn); }, 80);
    });
    rail.addEventListener('mouseleave', function () {
        if (hoverSwitchTimer) { clearTimeout(hoverSwitchTimer); hoverSwitchTimer = null; }
    });

    // The rail section that matches the current URL. The Blade layout marks
    // one rail button with .is-active on render based on $active_section;
    // we capture that here so we can restore it when the user hovers another
    // section but then walks away without clicking a nav-item.
    var anchoredSection = shell.dataset.activeSection
        || (function () {
            var b = rail.querySelector('.v2-rail-btn[data-section].is-active');
            return b ? b.dataset.section : null;
        })();

    // When the cursor leaves the whole rail+panel shell, snap the active
    // section back to the URL-anchored one. Without this, hovering Marketing
    // (or any other section) while you're actually on a Catalog page leaves
    // the rail stuck on Marketing until you either click a real nav item or
    // refresh. Listening on the shell — not the rail — means moving from
    // rail to panel to read a menu does NOT trigger a snap-back.
    shell.addEventListener('mouseleave', function () {
        if (hoverSwitchTimer) { clearTimeout(hoverSwitchTimer); hoverSwitchTimer = null; }
        if (!anchoredSection) return;
        var btn = rail.querySelector('.v2-rail-btn[data-section="' + anchoredSection + '"]');
        if (btn && !btn.classList.contains('is-active')) {
            activateRailSection(btn);
        }
    });

    panel.addEventListener('click', function (e) {
        var hdr = e.target.closest('[data-group-toggle]');
        if (hdr) {
            var key = hdr.dataset.groupToggle;
            var group = hdr.parentElement;
            var items = group.querySelector('.v2-group-items');
            var isCollapsed = group.classList.toggle('is-collapsed');
            if (items) items.style.display = isCollapsed ? 'none' : '';
            collapsed[key] = isCollapsed;
            save(COLLAPSED_KEY, collapsed);
            return;
        }
        var par = e.target.closest('[data-parent-toggle]');
        if (par) {
            var pkey = par.dataset.parentToggle;
            var children = par.nextElementSibling;
            var isOpen = par.classList.toggle('is-open');
            if (children) {
                if (isOpen) children.removeAttribute('hidden');
                else children.setAttribute('hidden', '');
            }
            parentsState[pkey] = isOpen;
            save(PARENTS_KEY, parentsState);
            return;
        }
        var pin = e.target.closest('.v2-pin');
        if (pin) {
            e.preventDefault();
            e.stopPropagation();
            var visiblePanel = panel.querySelector('.v2-panel-content:not([hidden])');
            var panelKey = pinKeyFor(visiblePanel ? visiblePanel.dataset.panel : 'dashboard');
            var id = pin.dataset.pin;
            var list = pins[panelKey] || [];
            var idx = list.indexOf(id);
            if (idx === -1) list.push(id); else list.splice(idx, 1);
            pins[panelKey] = list;
            save(PIN_KEY, pins);
            renderPinned(panelKey);
            panel.querySelectorAll('.v2-pin[data-pin="' + id + '"]').forEach(function (b) {
                b.classList.toggle('is-pinned', list.indexOf(id) !== -1);
            });
        }
    });

    panel.querySelectorAll('[data-group-toggle]').forEach(function (hdr) {
        if (collapsed[hdr.dataset.groupToggle]) {
            var group = hdr.parentElement;
            group.classList.add('is-collapsed');
            var items = group.querySelector('.v2-group-items');
            if (items) items.style.display = 'none';
        }
    });
    panel.querySelectorAll('[data-parent-toggle]').forEach(function (par) {
        if (parentsState[par.dataset.parentToggle] && !par.classList.contains('is-open')) {
            par.classList.add('is-open');
            var children = par.nextElementSibling;
            if (children) children.removeAttribute('hidden');
        }
    });

    function renderPinned(panelKey) {
        var sect = panelKey.split('::')[1];
        var p = panel.querySelector('.v2-panel-content[data-panel="' + sect + '"]');
        if (!p) return;
        var card = p.querySelector('.v2-pinned-card');
        if (!card) return;
        var list = pins[panelKey] || [];
        var inner = card.querySelector('.v2-pinned-list');
        if (!inner) return;
        if (list.length === 0) {
            inner.innerHTML = '<div class="v2-pinned-empty">' + (card.dataset.emptyText || 'Hover any item and tap the pin to add a shortcut here.') + '</div>';
            return;
        }
        var html = '';
        list.forEach(function (id) {
            var src = p.querySelector('.v2-nav-item[data-id="' + id + '"]');
            if (src) html += '<div class="v2-pinned-item">' + src.innerHTML + '</div>';
        });
        inner.innerHTML = html || '<div class="v2-pinned-empty">' + (card.dataset.emptyText || '') + '</div>';
        inner.querySelectorAll('.v2-pinned-item').forEach(function (it, i) {
            var src = p.querySelector('.v2-nav-item[data-id="' + list[i] + '"]');
            if (src && src.href) it.addEventListener('click', function () { location.href = src.href; });
        });
    }

    panel.querySelectorAll('.v2-panel-content').forEach(function (p) {
        var key = pinKeyFor(p.dataset.panel);
        var list = pins[key] || [];
        list.forEach(function (id) {
            var btn = p.querySelector('.v2-pin[data-pin="' + id + '"]');
            if (btn) btn.classList.add('is-pinned');
        });
        renderPinned(key);
    });

    // Rail tooltip — body-level fixed element so it escapes the rail's overflow clipping.
    var railTooltip = document.querySelector('.v2-rail-tooltip');
    if (!railTooltip) {
        railTooltip = document.createElement('div');
        railTooltip.className = 'v2-rail-tooltip';
        document.body.appendChild(railTooltip);
    }
    function showRailTooltip(btn) {
        var label = btn.getAttribute('data-label') || btn.getAttribute('aria-label');
        if (!label) return;
        railTooltip.textContent = label;
        var rect = btn.getBoundingClientRect();
        var isRtl = document.documentElement.getAttribute('dir') === 'rtl';
        if (isRtl) {
            // Rail is on the right edge in RTL — tooltip extends to the left.
            railTooltip.style.left = '';
            railTooltip.style.right = (window.innerWidth - rect.left + 12) + 'px';
        } else {
            railTooltip.style.right = '';
            railTooltip.style.left = (rect.right + 12) + 'px';
        }
        railTooltip.style.top  = (rect.top + rect.height / 2) + 'px';
        railTooltip.classList.add('is-visible');
    }
    function hideRailTooltip() { railTooltip.classList.remove('is-visible'); }
    rail.querySelectorAll('.v2-rail-btn[data-label]').forEach(function (btn) {
        btn.addEventListener('mouseenter', function () { showRailTooltip(btn); });
        btn.addEventListener('mouseleave', hideRailTooltip);
        btn.addEventListener('click', hideRailTooltip);
        btn.addEventListener('focus', function () { showRailTooltip(btn); });
        btn.addEventListener('blur', hideRailTooltip);
    });

    // Scroll the active nav item into view inside the panel after page reload.
    // Uses panel.scrollTop directly so we only scroll the panel, not the page.
    function scrollActiveIntoView() {
        var visiblePanel = panel.querySelector('.v2-panel-content:not([hidden])');
        if (!visiblePanel) return;
        var active = visiblePanel.querySelector('.v2-nav-item.is-active');
        if (!active) return;
        var panelRect = panel.getBoundingClientRect();
        var activeRect = active.getBoundingClientRect();
        if (activeRect.top < panelRect.top + 8 || activeRect.bottom > panelRect.bottom - 8) {
            var delta = activeRect.top - panelRect.top - (panel.clientHeight / 2) + (active.clientHeight / 2);
            panel.scrollTop = panel.scrollTop + delta;
        }
    }
    scrollActiveIntoView();
    rail.addEventListener('click', function () { setTimeout(scrollActiveIntoView, 0); });

    var profileBtn = document.getElementById('v2-rail-profile');
    var profilePop = document.getElementById('v2-profile-pop');
    if (profileBtn && profilePop) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = profilePop.classList.toggle('is-open');
            profileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#v2-profile-pop, #v2-rail-profile')) {
                profilePop.classList.remove('is-open');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
})();
</script>
@endpush
