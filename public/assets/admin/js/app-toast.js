"use strict";

/**
 * Global toast notification.
 * @param {string} type         success | info | warning | danger | error
 * @param {string} [title]       optional heading (falls back to a default per type)
 * @param {string} [description] optional body text (row omitted when empty)
 * @param {Object} [options]     optional { duration, onClose }
 * @returns {jQuery} the toast element
 */
function new_tostar(type, title, description, options) {
    var TYPES = {
        success: { icon: 'tio-checkmark-circle', title: 'Success' },
        info: { icon: 'tio-info', title: 'Info' },
        warning: { icon: 'tio-warning', title: 'Warning' },
        danger: { icon: 'tio-clear-circle', title: 'Error' }
    };

    if (type === 'error') type = 'danger';
    type = TYPES[type] ? type : 'success';
    options = options || {};
    var duration = options.duration || 6000;
    var heading = (title === undefined || title === null || title === '') ? TYPES[type].title : title;

    var container = document.getElementById('app-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'app-toast-container';
        container.className = 'app-toast-container';
        document.body.appendChild(container);
    }

    var $toast = $(
        '<div class="app-toast app-toast--' + type + '" role="alert" aria-live="polite">' +
            '<div class="app-toast__track"><div class="app-toast__progress"></div></div>' +
            '<div class="app-toast__body">' +
                '<div class="app-toast__icon"><i class="' + TYPES[type].icon + '"></i></div>' +
                '<div class="app-toast__content">' +
                    '<h3 class="app-toast__title"></h3>' +
                    (description ? '<p class="app-toast__text"></p>' : '') +
                '</div>' +
                '<button type="button" class="app-toast__close" aria-label="Close"><i class="tio-clear"></i></button>' +
            '</div>' +
        '</div>'
    );

    // set as text to avoid HTML injection
    $toast.find('.app-toast__title').text(heading);
    if (description) {
        $toast.find('.app-toast__text').text(description);
    }

    $(container).append($toast);
    $toast.hide().fadeIn(250);
    $toast.find('.app-toast__progress').css('width', '100%').animate({ width: '0%' }, duration, 'linear');

    var dismissed = false;
    var timer = setTimeout(dismiss, duration);

    function dismiss() {
        if (dismissed) return;
        dismissed = true;
        clearTimeout(timer);
        $toast.stop(true, true).fadeOut(200, function () {
            $toast.remove();
        });
        if (typeof options.onClose === 'function') {
            options.onClose();
        }
    }

    $toast.find('.app-toast__close').on('click', dismiss);

    return $toast;
}
window.new_tostar = new_tostar;
