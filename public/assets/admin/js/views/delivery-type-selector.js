(function () {
    'use strict';

    const section = document.getElementById('delivery_type_section');
    if (!section) return;

    const getUrl       = section.dataset.getUrl  || '';
    const setUrl       = section.dataset.setUrl  || '';
    const csrf         = section.dataset.csrf    || '';
    const currencySym  = section.dataset.currencySymbol  || '';
    const currencyPos  = section.dataset.currencyPosition || 'left';
    const roundDigit   = parseInt(section.dataset.roundDigit || '2', 10);
    let zoneId         = parseInt(section.dataset.zoneId   || '0', 10);
    let moduleId       = parseInt(section.dataset.moduleId || '0', 10);
    let storeId        = parseInt(section.dataset.storeId  || '0', 10);
    let storeBaseRange = parseBase(section.dataset.storeDeliveryTime || '');

    const optionsEl       = section.querySelector('#delivery_type_options');
    const noteAddressEl   = section.querySelector('#delivery_type_note_address');
    const noteFreeEl      = section.querySelector('#delivery_type_note_free');
    const typeInput       = section.querySelector('input[name="delivery_type"]');
    const chargeInput     = section.querySelector('input[name="delivery_type_charge"]');

    const TYPE_STANDARD       = 'standard';
    const TYPE_EXPRESS        = 'express';
    const TYPE_SLIGHTLY_DELAY = 'slightly_delay';

    const STATE_HIDDEN              = 'hidden';
    const STATE_DISABLED_NO_ADDRESS = 'disabled_no_address';
    const STATE_DISABLED_FREE       = 'disabled_free';
    const STATE_ENABLED             = 'enabled';

    let cachedOptions       = [];
    let cachedMinTimeMin    = 0;
    let currentDeliveryFee  = readDeliveryFee();
    let currentOrderType    = readOrderType();
    let currentHasAddress   = readHasAddress();
    let currentState        = STATE_HIDDEN;
    let inflightFetch       = false;
    let lastFetchedKey      = '';

    function readDeliveryFee() {
        const el = document.getElementById('cart_delivery_fee');
        if (!el) return 0;
        const raw = el.getAttribute('data-value');
        const n = parseFloat(raw != null ? raw : el.value);
        return Number.isFinite(n) ? n : 0;
    }

    function readOrderType() {
        const checked = document.querySelector('input[name="order_type"]:checked');
        if (checked) return String(checked.value || 'delivery');
        const cartFlag = document.getElementById('cart_order_type');
        if (cartFlag) return String(cartFlag.getAttribute('data-value') || cartFlag.value || 'delivery');
        return 'delivery';
    }

    function readHasAddress() {
        const el = document.getElementById('cart_has_address');
        if (!el) return false;
        const raw = el.getAttribute('data-value');
        const v = raw != null ? raw : el.value;
        return String(v) === '1';
    }

    function parseBase(raw) {
        if (!raw) return null;
        const str    = String(raw);
        const tokens = str.split(/[-\s]+/).filter(Boolean);
        const nums   = tokens.filter(function (t) { return /^\d+$/.test(t); }).map(function (t) { return parseInt(t, 10); });
        if (nums.length < 2) return null;
        // The store delivery_time carries its unit ("30-60 min", "30-60 hours"
        // or "30-60 days"). Keep the numbers normalized to minutes (so the
        // minute-based express/slightly-delay adjustments stay in one space),
        // but remember the unit so we render in the unit the vendor chose.
        const unit   = /day/i.test(str) ? 'day' : (/hour/i.test(str) ? 'hour' : 'min');
        const factor = unit === 'day' ? 1440 : (unit === 'hour' ? 60 : 1);
        return { min: nums[0] * factor, max: nums[1] * factor, unit: unit };
    }

    function formatCurrency(value) {
        const v = parseFloat(value || 0).toFixed(roundDigit);
        return currencyPos === 'right' ? v + ' ' + currencySym : currencySym + ' ' + v;
    }

    // Matches StackFood's formatTime exactly: everything is held in minutes and
    // auto-promoted to hours once it reaches 60, regardless of the unit the
    // vendor originally typed. This keeps the rendered ranges identical to
    // StackFood (e.g. 300 -> "5 hours", 280 -> "4 hours 40 min").
    function fmtMinutes(m) {
        const total = Math.max(0, parseInt(m || 0, 10));
        if (total >= 60) {
            const hours = Math.floor(total / 60);
            const mins  = total % 60;
            if (mins === 0) {
                return hours + (hours === 1 ? ' hour' : ' hours');
            }
            return hours + (hours === 1 ? ' hour ' : ' hours ') + mins + ' min';
        }
        return total + ' min';
    }

    // Mirrors StackFood's POS delivery-time logic exactly. Everything is kept in
    // minutes (storeBaseRange and the option deltas are already normalized to
    // minutes server-side), with the zone minimum delivery time acting as the
    // floor the same way it does in StackFood's loadDeliveryTypes().
    function rangeText(deliveryType, opt) {
        if (!storeBaseRange) return '';
        const res_min  = storeBaseRange.min;   // minutes
        const res_max  = storeBaseRange.max;   // minutes
        const zone_min = cachedMinTimeMin;     // zone minimum_delivery_time, minutes

        let display_min = res_min;
        let display_max = res_max;

        // STANDARD: use the store delivery time as-is, but never below the zone floor.
        if (deliveryType === TYPE_STANDARD) {
            if (zone_min > res_max) {
                display_max = zone_min;
            } else if (zone_min > 0 && res_min > zone_min) {
                display_min = zone_min;
                display_max = res_max;
            }
        }
        // EXPRESS: reduce the max time, never letting it drop below the min.
        else if (deliveryType === TYPE_EXPRESS) {
            const reduce_val = parseInt(opt.reduce_delivery_time || 0, 10);
            if (zone_min > res_max) {
                display_max = zone_min - reduce_val;
            } else if (zone_min > 0 && res_min > zone_min) {
                display_min = zone_min;
                display_max = res_max - reduce_val;
            } else {
                display_max = res_max - reduce_val;
            }
            if (display_max < display_min) {
                display_max = display_min;
            }
        }
        // SLIGHTLY DELAY (saver): push the max time out by the configured amount.
        else if (deliveryType === TYPE_SLIGHTLY_DELAY) {
            const add_val = parseInt(opt.add_delivery_time || 0, 10);
            if (zone_min > res_max) {
                display_max = zone_min + add_val;
            } else if (zone_min > 0 && res_min > zone_min) {
                display_min = zone_min;
                display_max = res_max + add_val;
            } else {
                display_max = res_max + add_val;
            }
        }

        const timeRange = display_min === display_max
            ? 'upto ' + fmtMinutes(display_max)
            : fmtMinutes(display_min) + ' - ' + fmtMinutes(display_max);
        return '(' + timeRange + ')';
    }

    function chargeText(deliveryType, opt) {
        const base = Math.max(0, currentDeliveryFee);
        if (deliveryType === TYPE_EXPRESS) {
            const extra = parseFloat(opt.extra_charge || 0);
            return formatCurrency(base + extra);
        }
        if (deliveryType === TYPE_SLIGHTLY_DELAY) {
            const reduce = parseFloat(opt.reduce_charge || 0);
            return formatCurrency(Math.max(0, base - reduce));
        }
        return formatCurrency(base);
    }

    function deltaCharge(deliveryType, opt) {
        if (deliveryType === TYPE_EXPRESS)        return parseFloat(opt.extra_charge  || 0);
        if (deliveryType === TYPE_SLIGHTLY_DELAY) return parseFloat(opt.reduce_charge || 0);
        return 0;
    }

    function decideState() {
        if (currentOrderType !== 'delivery') return STATE_HIDDEN;
        if (!cachedOptions.length) return STATE_HIDDEN;
        if (!currentHasAddress) return STATE_DISABLED_NO_ADDRESS;
        if (currentDeliveryFee <= 0) return STATE_DISABLED_FREE;
        return STATE_ENABLED;
    }

    function setSectionVisibility(visible) {
        if (visible) section.classList.remove('d-none');
        else         section.classList.add('d-none');
    }

    function setNotes(noteKind) {
        if (noteAddressEl) noteAddressEl.classList.toggle('d-none', noteKind !== 'address');
        if (noteFreeEl)    noteFreeEl.classList.toggle('d-none',    noteKind !== 'free');
    }

    function renderHidden(forceServerReset) {
        setSectionVisibility(false);
        optionsEl.innerHTML = '';
        setNotes(null);
        if (forceServerReset && (typeInput.value !== '' || parseFloat(chargeInput.value || '0') !== 0)) {
            return pushSelection('', 0);
        }
        typeInput.value = '';
        chargeInput.value = (0).toFixed(roundDigit);
        return Promise.resolve();
    }

    function renderDisabled(noteKind, forceServerReset) {
        setSectionVisibility(true);
        renderOptionsHtml();
        optionsEl.classList.add('is-disabled');
        optionsEl.querySelectorAll('.delivery-type-radio').forEach(function (r) {
            r.disabled = true;
            r.checked = false;
        });
        setNotes(noteKind);
        if (forceServerReset && (typeInput.value !== '' || parseFloat(chargeInput.value || '0') !== 0)) {
            return pushSelection('', 0);
        }
        typeInput.value = '';
        chargeInput.value = (0).toFixed(roundDigit);
        return Promise.resolve();
    }

    function renderEnabled(autoSelectFirst) {
        setSectionVisibility(true);
        renderOptionsHtml();
        optionsEl.classList.remove('is-disabled');
        optionsEl.querySelectorAll('.delivery-type-radio').forEach(function (r) { r.disabled = false; });
        setNotes(null);

        const stored = typeInput.value || '';
        const known  = cachedOptions.some(function (o) { return o.delivery_type === stored; });
        let selected = known ? stored : (autoSelectFirst ? (cachedOptions[0] && cachedOptions[0].delivery_type) || TYPE_STANDARD : '');

        if (!selected) return Promise.resolve();

        const opt = cachedOptions.find(function (o) { return o.delivery_type === selected; });
        const delta = opt ? deltaCharge(selected, opt) : 0;
        const radios = optionsEl.querySelectorAll('.delivery-type-radio');
        radios.forEach(function (r) { r.checked = (r.value === selected); });

        if (selected !== stored || (selected && parseFloat(chargeInput.value || '0') !== delta)) {
            return pushSelection(selected, delta);
        }
        typeInput.value = selected;
        chargeInput.value = delta.toFixed(roundDigit);
        return Promise.resolve();
    }

    function renderOptionsHtml() {
        optionsEl.innerHTML = cachedOptions.map(function (opt) {
            const name   = opt.delivery_type_text;
            const range  = rangeText(opt.delivery_type, opt);
            const charge = chargeText(opt.delivery_type, opt);
            return '<label class="delivery-type-row">' +
                '<span class="delivery-type-row__main">' +
                '<input type="radio" name="delivery_type_option" class="delivery-type-row__radio delivery-type-radio" ' +
                'value="' + opt.delivery_type + '" data-delta="' + deltaCharge(opt.delivery_type, opt) + '">' +
                '<span class="delivery-type-row__name">' + name + '</span>' +
                (range ? '<span class="delivery-type-row__time">' + range + '</span>' : '') +
                '</span>' +
                '<span class="delivery-type-row__charge">' + charge + '</span>' +
                '</label>';
        }).join('');
    }

    function applyState(targetState, opts) {
        const wasState = currentState;
        currentState = targetState;
        const enteringEnabled = targetState === STATE_ENABLED && wasState !== STATE_ENABLED;
        const force = opts && opts.forceServerReset;

        if (targetState === STATE_HIDDEN)              return renderHidden(force);
        if (targetState === STATE_DISABLED_NO_ADDRESS) return renderDisabled('address', force);
        if (targetState === STATE_DISABLED_FREE)       return renderDisabled('free', force);
        return renderEnabled(enteringEnabled || (opts && opts.autoSelectFirst));
    }

    function pushSelection(deliveryType, delta) {
        typeInput.value = deliveryType || '';
        chargeInput.value = (delta || 0).toFixed(roundDigit);

        const fireEvent = function () {
            section.dispatchEvent(new CustomEvent('delivery-type:changed', {
                bubbles: true,
                detail: { deliveryType: deliveryType || '', delta: delta || 0 },
            }));
        };

        if (!setUrl) { fireEvent(); return Promise.resolve(); }

        const form = new FormData();
        form.append('_token', csrf);
        form.append('delivery_type', deliveryType || '');
        form.append('delivery_type_charge', String(delta || 0));

        return fetch(setUrl, { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .catch(function () { /* swallow */ })
            .finally(fireEvent);
    }

    function fetchOptions(opts) {
        const fee  = readDeliveryFee();
        const type = readOrderType();
        const has  = readHasAddress();
        const key  = storeId + '|' + moduleId + '|' + zoneId + '|' + (fee > 0 ? '1' : '0') + '|' + type + '|' + (has ? '1' : '0');
        if (inflightFetch || key === lastFetchedKey) {
            currentDeliveryFee = fee;
            currentOrderType   = type;
            currentHasAddress  = has;
            return applyState(decideState(), opts);
        }
        if (!getUrl || zoneId <= 0 || moduleId <= 0 || type !== 'delivery') {
            cachedOptions = [];
            currentDeliveryFee = fee;
            currentOrderType   = type;
            currentHasAddress  = has;
            return applyState(STATE_HIDDEN, opts);
        }

        inflightFetch = true;
        lastFetchedKey = key;
        const url = getUrl + (getUrl.indexOf('?') >= 0 ? '&' : '?')
            + 'zone_id=' + zoneId + '&module_id=' + moduleId + '&delivery_fee=' + fee
            + (storeId > 0 ? '&store_id=' + storeId : '');

        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : { enabled: false, options: [] }; })
            .then(function (data) {
                if (!data || !data.enabled || !Array.isArray(data.options) || !data.options.length) {
                    cachedOptions = [];
                    currentDeliveryFee = fee;
                    currentOrderType   = type;
                    currentHasAddress  = has;
                    return applyState(STATE_HIDDEN, opts);
                }
                cachedOptions       = data.options;
                cachedMinTimeMin    = parseInt(data.minimum_delivery_time   || 0, 10);
                currentDeliveryFee  = fee;
                currentOrderType    = type;
                currentHasAddress   = has;
                return applyState(decideState(), opts);
            })
            .catch(function () {
                cachedOptions = [];
                currentDeliveryFee = fee;
                currentOrderType   = type;
                currentHasAddress  = has;
                return applyState(STATE_HIDDEN, opts);
            })
            .finally(function () { inflightFetch = false; });
    }

    optionsEl.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('delivery-type-radio')) return;
        if (currentState !== STATE_ENABLED) return;
        const delta = parseFloat(target.dataset.delta || '0');
        pushSelection(target.value, delta);
    });

    function observeCart() {
        const cart = document.getElementById('cart');
        if (!cart || !window.MutationObserver) return;
        const obs = new MutationObserver(function () {
            const fee = readDeliveryFee();
            const type = readOrderType();
            const has = readHasAddress();
            if (fee === currentDeliveryFee && type === currentOrderType && has === currentHasAddress) return;
            currentDeliveryFee = fee;
            currentOrderType = type;
            currentHasAddress = has;
            fetchOptions({});
        });
        obs.observe(cart, { childList: true, subtree: true });
    }
    observeCart();

    function observeOrderType() {
        document.addEventListener('change', function (event) {
            const t = event.target;
            if (!t || t.name !== 'order_type') return;
            currentOrderType = String(t.value || 'delivery');
            fetchOptions({ forceServerReset: true });
        });
    }
    observeOrderType();

    window.deliveryTypeSelector = {
        setContext: function (next) {
            if (next && typeof next.zoneId !== 'undefined')             zoneId   = parseInt(next.zoneId   || '0', 10);
            if (next && typeof next.moduleId !== 'undefined')           moduleId = parseInt(next.moduleId || '0', 10);
            if (next && typeof next.storeId !== 'undefined')            storeId  = parseInt(next.storeId  || '0', 10);
            if (next && typeof next.storeDeliveryTime !== 'undefined')  storeBaseRange = parseBase(next.storeDeliveryTime || '');
            section.dataset.zoneId   = String(zoneId);
            section.dataset.moduleId = String(moduleId);
            section.dataset.storeId  = String(storeId);
            lastFetchedKey = '';
            return fetchOptions({ forceServerReset: true });
        },
        syncFromCart: function () {
            const fee = readDeliveryFee();
            const type = readOrderType();
            const has = readHasAddress();
            if (fee === currentDeliveryFee && type === currentOrderType && has === currentHasAddress) {
                return applyState(decideState(), {});
            }
            currentDeliveryFee = fee;
            currentOrderType = type;
            currentHasAddress = has;
            return fetchOptions({});
        },
        refresh: function () { lastFetchedKey = ''; return fetchOptions({}); },
        currentSelection: function () {
            return { type: typeInput.value, charge: parseFloat(chargeInput.value || 0) };
        },
        currentState: function () { return currentState; },
    };

    fetchOptions({});
})();
