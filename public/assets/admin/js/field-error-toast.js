"use strict";

(function () {
    function revealField($field) {
        var $langForm = $field.closest('.lang_form');
        if ($langForm.length && $langForm.hasClass('d-none')) {
            var $scope = $field.closest('form');
            $scope = $scope.length ? $scope : $(document);
            $scope.find('.lang_form').addClass('d-none');
            $scope.find('.lang_link').removeClass('active');
            var tabId = $langForm.attr('id');
            $('#' + tabId).removeClass('d-none');
            $('#' + tabId.replace('-form', '-link')).addClass('active');
        }

        var $pane = $field.closest('.tab-pane');
        if ($pane.length && !$pane.hasClass('active')) {
            var paneId = $pane.attr('id');
            $('[data-bs-toggle="tab"][href="#' + paneId + '"],[data-toggle="tab"][href="#' + paneId + '"],'
                + '[data-bs-toggle="tab"][data-bs-target="#' + paneId + '"],[data-toggle="tab"][data-target="#' + paneId + '"]')
                .first().trigger('click');
        }
    }

    window.getFieldLabel = function (field) {
        var el = field instanceof jQuery ? field[0] : field;
        if (!el) return '';
        var $el = $(el);
        var text = '';

        if (el.type === 'file') {
            var fileText = el.getAttribute('aria-label')
                || (el.getAttribute('name') || '').replace(/\[.*?\]/g, '').replace(/[_-]+/g, ' ');
            return fileText.replace(/\*/g, '').replace(/\s+/g, ' ').replace(/[:：]\s*$/, '').trim();
        }

        if (el.id) {
            try {
                var forLabel = document.querySelector('label[for="' + el.id + '"]');
                if (forLabel) text = forLabel.textContent;
            } catch (e) {}
        }

        if (!text) text = el.getAttribute('aria-label') || '';

        if (!text) {
            var $label = $el.closest('label');
            if ($label.length) {
                text = $label.text();
            } else {
                var $group = $el.closest('.error-wrapper, .form-group, .input-group');
                if ($group.length) {
                    var $groupLabel = $group.find('label').first();
                    if ($groupLabel.length) text = $groupLabel.text();
                }
            }
        }

        if (!text) text = el.getAttribute('placeholder') || '';

        if (!text) {
            text = (el.getAttribute('name') || '').replace(/\[.*?\]/g, '').replace(/[_-]+/g, ' ');
        }

        return text.replace(/\*/g, '').replace(/\s+/g, ' ').replace(/[:：]\s*$/, '').trim();
    };

    window.showFieldErrorToast = function (field, message) {
        var $field = field instanceof jQuery ? field : $(field);

        if ($field.length) {
            revealField($field);
        }

        if (message) {
             if (typeof toastr !== 'undefined') {
                toastr.error(message);
            } else if (typeof window.new_tostar === 'function') {
                new_tostar('error', '', message);
            }
        }

        if ($field.length) {
            setTimeout(function () {
                try {
                    var $target = $field;
                    if ($field.is('select.select2-hidden-accessible')) {
                        var $container = $field.next('.select2-container');
                        if ($container.length) $target = $container;
                    } else if (!$field.is(':visible')) {
                        var $visible = $field.closest(':visible');
                        if ($visible.length) $target = $visible;
                    }
                    $target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    $field.trigger('focus');
                } catch (e) {}
            }, 100);
        }
    };

    var handledInPass = false;
    document.addEventListener('invalid', function (e) {
        var el = e.target;
        if (!el || !el.willValidate) return;

        e.preventDefault();

        if (handledInPass) return;
        handledInPass = true;
        setTimeout(function () { handledInPass = false; }, 0);

        var label = window.getFieldLabel(el);
        var message = el.validationMessage;
        if (label) {
            message = label.charAt(0).toUpperCase() + label.slice(1) + ' — ' + message;
        }

        showFieldErrorToast($(el), message);
    }, true);
})();
