"use strict";

/**
 * Centralized "verified store" badge rendering for every select2 dropdown.
 *
 * A dropdown shows the badge for an entry when EITHER:
 *   - the server-rendered <option> has data-verified="1", or
 *   - the AJAX result object carries verified: 1 (see get_stores()).
 *
 * It is registered as the global select2 default templateResult/templateSelection,
 * so any select2 (plain or AJAX) picks it up automatically. It is a no-op for
 * entries without a verified flag, returning the plain text exactly as the
 * select2 default does (so non-store dropdowns are unaffected and text is still
 * escaped by select2). HSSelect2 selects override templateResult, so the same
 * function is also passed explicitly to HSSelect2.init() in admin.js / vendor.js.
 */
(function () {
    function isVerified(data) {
        if (!data) {
            return false;
        }
        if (data.element && data.element.dataset && data.element.dataset.verified === "1") {
            return true;
        }
        return data.verified === 1 || data.verified === "1" || data.verified === true;
    }

    // Exposed so the HSSelect2.init() calls can reuse the exact same renderer.
    window.hsSelect2VerifiedTemplate = function (data) {
        // Placeholders / option groups have no usable id — leave them untouched.
        if (!data || data.id === undefined || data.id === null || data.id === "") {
            return data ? data.text : data;
        }

        var optionTemplate = data.element && data.element.dataset ? data.element.dataset.optionTemplate : null;

        if (!isVerified(data)) {
            // Preserve HSSelect2's data-option-template feature; otherwise return
            // the plain string so select2 escapes it just like the default does.
            return optionTemplate ? window.jQuery($.parseHTML(optionTemplate)) : data.text;
        }

        var $node = window.jQuery("<span>").addClass("select2-verified-option");
        if (optionTemplate) {
            $node.append($.parseHTML(optionTemplate));
        } else {
            $node.append(document.createTextNode(data.text == null ? "" : data.text));
        }
        $node.append(" ").append(
            window.jQuery('<i class="tio-verified text-success" title="Verified"></i>')
        );
        return $node;
    };

    // Register globally as soon as select2 is available (synchronous, before any
    // ready-deferred dropdown init runs).
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery.fn.select2.defaults) {
        window.jQuery.fn.select2.defaults.set("templateResult", window.hsSelect2VerifiedTemplate);
        window.jQuery.fn.select2.defaults.set("templateSelection", window.hsSelect2VerifiedTemplate);
    }
})();
