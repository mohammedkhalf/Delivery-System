"use strict";

(function ($) {
    if (!window.smartBannerConfig) {
        return;
    }
    const config = window.smartBannerConfig;
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    const $formDrawer = $('#smartBannerForm_offcanvas');
    const $viewDrawer = $('#smartBannerView_offcanvas');
    const $form = $('#smart_banner_form');
    const $dateWrapper = $('#smart_banner_date_wrapper');
    const $dateInput = $('#smart_banner_date_range');
    const $timeInput = $('#smart_banner_time_range');
    const $moduleSelect = $('#smart_banner_module');
    const $positionSelect = $('#smart_banner_position');
    const $redirectTypeSelect = $('#smart_banner_redirect_type');
    const $targetWrapper = $('#smart_banner_target_wrapper');
    const $targetLabel = $('#smart_banner_target_label');
    const $targetSelect = $('#smart_banner_target');
    const $imageInput = $('#smart_banner_image_input');

    function setImagePartialPreview(url) {
        const $card = $imageInput.closest('.upload-file_custom');
        if (!$card.length) return;
        const $img = $card.find('.upload-file-img');
        const $textbox = $card.find('.upload-file-textbox');
        const $overlay = $card.find('.overlay');
        const $removeBtn = $card.find('.remove_btn');
        $imageInput.val('');
        if (url) {
            $img.attr('src', url).show();
            $textbox.hide();
            $overlay.addClass('show');
            $removeBtn.css('opacity', 1);
            $card.addClass('input-disabled');
        } else {
            $img.hide().attr('src', '');
            $textbox.show();
            $overlay.removeClass('show');
            $removeBtn.css('opacity', 0);
            $card.removeClass('input-disabled');
        }
    }
    const $formTitle = $('#smart_banner_form_title');
    const $submitBtn = $('#smart_banner_submit_btn');
    const $bannerId = $('#smart_banner_id');
    let currentBanner = null;

    function initSelect2(elements) {
        elements.each(function () {
            const $el = $(this);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            $el.select2({
                placeholder: $el.data('placeholder') || $el.attr('data-placeholder') || 'Select',
                dropdownParent: $el.closest('.custom-offcanvas').length
                    ? $el.closest('.custom-offcanvas')
                    : $(document.body),
                width: '100%'
            });
        });
    }

    function initDatePicker() {
        if (!$dateInput.length) return;
        $dateInput.daterangepicker({
            minDate: new Date(),
            autoUpdateInput: false,
            locale: { cancelLabel: 'Clear', format: 'MM/DD/YYYY' }
        });
        $dateInput.on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
        });
        $dateInput.on('cancel.daterangepicker', function () {
            $(this).val('');
        });
    }

    function initTimePicker() {
        if (!$timeInput.length) return;
        $timeInput.daterangepicker({
            timePicker: true,
            timePicker24Hour: false,
            timePickerIncrement: 1,
            autoUpdateInput: false,
            locale: { format: 'h:mm A', cancelLabel: 'Clear' },
            singleDatePicker: false,
            showDropdowns: false
        });
        $timeInput.on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('h:mm A') + ' - ' + picker.endDate.format('h:mm A'));
        });
        $timeInput.on('show.daterangepicker', function (ev, picker) {
            picker.container.find('.calendar-table').hide();
            picker.container.find('.calendar-time').css('visibility', 'visible');
        });
        $timeInput.on('cancel.daterangepicker', function () {
            $(this).val('');
        });
    }

    function toggleDateWrapper() {
        const value = $('input[name="active_days"]:checked').val();
        if (value === 'everyday') {
            $dateWrapper.addClass('d-none');
            $dateInput.val('');
        } else {
            $dateWrapper.removeClass('d-none');
        }
    }

    const MODULE_REDIRECT_RULES = {
        'parcel': { allowed: ['module_home'] },
        'ride-share': { allowed: ['module_home'] },
        'rental': { allowed: ['module_home', 'store_page'], useProviderLabel: true },
        'service': { allowed: ['module_home', 'store_page'], useProviderLabel: true }
    };

    function getModuleRedirectRule() {
        const type = $moduleSelect.find('option:selected').data('type');
        return MODULE_REDIRECT_RULES[type] || null;
    }

    function applyModuleTypeRestrictions() {
        const rule = getModuleRedirectRule();
        const allowed = rule ? rule.allowed : null;

        $redirectTypeSelect.find('option').each(function () {
            const $opt = $(this);
            $opt.prop('disabled', !!allowed && allowed.indexOf($opt.val()) === -1);
        });

        const storePageLabel = (rule && rule.useProviderLabel && config.labels)
            ? config.labels.providerPage
            : (config.labels && config.labels.storePage);
        if (storePageLabel) {
            $redirectTypeSelect.find('option[value="store_page"]').text(storePageLabel);
        }

        if (allowed && allowed.indexOf($redirectTypeSelect.val()) === -1) {
            $redirectTypeSelect.val(allowed[0]);
        }

        initSelect2($redirectTypeSelect);
    }

    function applyTargetMode(redirectType, selectedId, selectedLabel) {
        if (redirectType === 'module_home' || redirectType === 'offer_page') {
            $targetWrapper.addClass('d-none');
            $targetSelect.prop('required', false);
            $targetSelect.prop('disabled', true);
            $targetSelect.empty().append(new Option('', '', false, false));
            $targetSelect.trigger('change');
            return;
        }

        $targetWrapper.removeClass('d-none');
        $targetSelect.prop('disabled', false);

        if (redirectType === 'store_page') {
            const rule = getModuleRedirectRule();
            const label = (rule && rule.useProviderLabel && config.labels)
                ? config.labels.selectProvider
                : ((config.labels && config.labels.selectStore) || 'Select Store');
            $targetLabel.html(label + ' <span class="text-danger">*</span>');
            $targetSelect.prop('required', true);
        } else {
            $targetLabel.text((config.labels && config.labels.selectCategory) || 'Select Category');
            $targetSelect.prop('required', false);
        }

        const moduleId = $moduleSelect.val();
        if (!moduleId || moduleId === 'all') {
            $targetSelect.empty().append(new Option('', '', false, false));
            $targetSelect.trigger('change');
            return;
        }

        const baseUrl = redirectType === 'store_page'
            ? $targetSelect.data('stores-url').replace('MODULE_ID', moduleId).replace('ZONE_ID', config.zoneId)
            : $targetSelect.data('categories-url').replace('MODULE_ID', moduleId);

        $.ajax({
            url: baseUrl,
            method: 'GET',
            success: function (res) {
                const list = (res && res.data) || res || [];
                $targetSelect.empty().append(new Option('', '', false, false));
                list.forEach(function (item) {
                    const opt = new Option(item.name || item.title || item.label, item.id, false, false);
                    $targetSelect.append(opt);
                });
                if (selectedId) {
                    if (!$targetSelect.find('option[value="' + selectedId + '"]').length && selectedLabel) {
                        $targetSelect.append(new Option(selectedLabel, selectedId, true, true));
                    } else {
                        $targetSelect.val(String(selectedId));
                    }
                }
                $targetSelect.trigger('change');
            },
            error: function () {
                $targetSelect.empty().append(new Option('', '', false, false));
                if (selectedId && selectedLabel) {
                    $targetSelect.append(new Option(selectedLabel, selectedId, true, true));
                }
                $targetSelect.trigger('change');
            }
        });
    }

    function bindCharCounters() {
        $('.smart-banner-char-counter').each(function () {
            const $input = $(this);
            const max = parseInt($input.data('max'), 10) || 50;
            const $label = $input.closest('.form-group').find('.char-counter-label');
            const update = function () {
                if ($input.val().length > max) {
                    $input.val($input.val().substring(0, max));
                }
                $label.text($input.val().length + '/' + max);
            };
            $input.off('input.sbcounter').on('input.sbcounter', update);
            update();
        });
    }

    function resetOffcanvasScroll($drawer) {
        $drawer.scrollTop(0);
        $drawer.find('.custom-offcanvas-body').scrollTop(0);
    }

    function resetForm() {
        resetOffcanvasScroll($formDrawer);
        $form[0].reset();
        $bannerId.val('');
        $form.attr('action', config.storeAction);
        $formTitle.text('Create Smart Banner');
        $submitBtn.text('Add');
        setImagePartialPreview(null);
        $imageInput.attr('required', true);
        $('#active_days_custom').prop('checked', true);
        $('.lang_link').removeClass('active');
        $('#default-link').addClass('active');
        $('.lang_form').addClass('d-none');
        $('#default-form').removeClass('d-none');
        toggleDateWrapper();
        applyModuleTypeRestrictions();
        applyTargetMode($redirectTypeSelect.val() || 'category');
        bindCharCounters();
        initSelect2($('#smartBannerForm_offcanvas select.js-select2-custom'));
    }

    function populateForm(banner) {
        resetForm();
        currentBanner = banner;
        $bannerId.val(banner.id);
        $form.attr('action', config.updateAction.replace('BANNER_ID', banner.id));
        $formTitle.text('Edit Smart Banner');
        $submitBtn.text('Update');
        $imageInput.removeAttr('required');

        setImagePartialPreview(banner.image_full_url || null);

        $moduleSelect.val(banner.module_id || '').trigger('change');
        $positionSelect.val(banner.position).trigger('change');

        if (banner.active_days === 'everyday') {
            $('#active_days_everyday').prop('checked', true);
        } else {
            $('#active_days_custom').prop('checked', true);
            if (banner.start_date && banner.end_date) {
                $dateInput.val(banner.start_date_formatted + ' - ' + banner.end_date_formatted);
            }
        }
        toggleDateWrapper();

        if (banner.time_range_formatted) {
            $timeInput.val(banner.time_range_formatted);
        }

        $redirectTypeSelect.val(banner.redirect_type).trigger('change');
        applyTargetMode(
            banner.redirect_type,
            banner.redirect_target_id,
            banner.redirect_target_label
        );

        const titles = banner.titles || {};
        const subtitles = banner.subtitles || {};
        $('#smartBannerForm_offcanvas .lang_form').each(function () {
            const formId = $(this).attr('id');
            const lang = formId.replace('-form', '');
            $(this).find('input[name="title[]"]').val(titles[lang] || '');
            $(this).find('input[name="subtitle[]"]').val(subtitles[lang] || '');
        });
        bindCharCounters();
    }

    function openCreate() {
        currentBanner = null;
        resetForm();
    }

    function openEdit(url) {
        $.ajax({
            url: url,
            method: 'GET',
            success: function (res) {
                const banner = (res && res.data) || res;
                populateForm(banner);
            },
            error: function (xhr) {
                if (window.toastr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load banner');
                }
            }
        });
    }

    function openView(url) {
        resetOffcanvasScroll($viewDrawer);
        const $body = $('#smart_banner_view_body');
        $body.html('<div class="text-center py-5"><img width="60" src="' + escapeHtml(config.loaderImg || '') + '" alt="loading"></div>');
        $('#smart_banner_view_edit_btn').data('id', '').data('url', '');

        $.ajax({
            url: url,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function (res) {
                const banner = (res && res.data) || res;
                $body.html(renderViewBody(banner));
                $('#smart_banner_view_edit_btn')
                    .data('id', banner.id)
                    .data('url', config.editUrl.replace('BANNER_ID', banner.id));
            },
            error: function (xhr) {
                $body.html('<div class="text-center py-5 text-danger">' +
                    ((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load banner') +
                    '</div>');
            }
        });
    }

    function renderViewBody(banner) {
        const date = banner.active_days === 'everyday'
            ? 'Everyday'
            : (banner.start_date_formatted + ' - ' + banner.end_date_formatted);
        const time = banner.time_range_formatted || '';
        const target = banner.redirect_target_label
            ? banner.redirect_target_label + '(' + (banner.redirect_type_label || banner.redirect_type) + ')'
            : (banner.redirect_type_label || banner.redirect_type);
        const img = banner.image_full_url || config.uploadPlaceholder;

        return ''
            + '<div class="bg-light rounded p-3 mb-3 d-flex gap-3 align-items-center">'
            +   '<img src="' + escapeHtml(img) + '" alt="banner" class="rounded" style="width:64px;height:64px;object-fit:cover;">'
            +   '<div>'
            +     '<h6 class="mb-1">' + escapeHtml(banner.title || '') + '</h6>'
            +     '<p class="fs-12 text-muted mb-0">' + escapeHtml(banner.subtitle || '') + '</p>'
            +   '</div>'
            + '</div>'
            + '<div class="bg-light rounded p-3">'
            +   row('Module', banner.module_name)
            +   row('Date', date)
            +   row('Time', time)
            +   row('Redirect to', target)
            +   row('Position', (banner.position || '').replace(/^./, function (c) { return c.toUpperCase(); }))
            + '</div>';
    }

    function row(label, value) {
        return '<div class="d-flex mb-2"><span class="text-muted">' +
            escapeHtml(label) + ':</span><span class="text-title ps-3">' + escapeHtml(value || '-') + '</span></div>';
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    function closeAllOffcanvas() {
        $('.custom-offcanvas').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
        $('body').removeClass('modal-open');
    }

$(document).on('click', '.smart-banner-create-trigger', function () {
        openCreate();
    });

    $(document).on('click', '.smart-banner-edit-trigger', function (e) {
        e.preventDefault();
        const url = $(this).data('url');
        $formDrawer.addClass('open');
        $('#offcanvasOverlay').addClass('show');
        $('body').addClass('modal-open');
        openEdit(url);
    });

    $(document).on('click', '.smart-banner-view-trigger', function (e) {
        e.preventDefault();
        const url = $(this).data('url');
        $viewDrawer.addClass('open');
        $('#offcanvasOverlay').addClass('show');
        $('body').addClass('modal-open');
        openView(url);
    });

    $(document).on('click', '#smart_banner_view_edit_btn', function () {
        const url = $(this).data('url');
        if (!url) return;
        $viewDrawer.removeClass('open');
        $formDrawer.addClass('open');
        $('#offcanvasOverlay').addClass('show');
        openEdit(url);
    });

    $(document).on('change', 'input[name="active_days"]', toggleDateWrapper);

    $(document).on('change', '#smart_banner_module', function () {
        applyModuleTypeRestrictions();
        applyTargetMode($redirectTypeSelect.val());
    });

    $(document).on('change', '#smart_banner_redirect_type', function () {
        applyTargetMode($(this).val());
    });

$(document).on('click', '#smartBannerForm_offcanvas .lang_link', function (e) {
        e.preventDefault();
        $('#smartBannerForm_offcanvas .lang_link').removeClass('active');
        $(this).addClass('active');
        $('#smartBannerForm_offcanvas .lang_form').addClass('d-none');
        const lang = $(this).attr('id').replace('-link', '');
        $('#smartBannerForm_offcanvas #' + lang + '-form').removeClass('d-none');
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        if (!$moduleSelect.val()) {
            if (window.toastr) toastr.error($moduleSelect.data('placeholder'));
            $moduleSelect.select2('open');
            return;
        }

        if ($redirectTypeSelect.val() === 'store_page' && !$targetSelect.val()) {
            const rule = getModuleRedirectRule();
            const message = (rule && rule.useProviderLabel && config.labels)
                ? config.labels.pleaseSelectProvider
                : ((config.labels && config.labels.pleaseSelectStore) || 'Please select a store.');
            if (window.toastr) toastr.error(message);
            $targetSelect.select2('open');
            return;
        }

        const formData = new FormData(this);
        $submitBtn.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            success: function (res) {
                const msg = (res && res.message) || '';
                if (msg) {
                    sessionStorage.setItem('smart_banner_flash', msg);
                }
                closeAllOffcanvas();
                window.location.reload();
            },
            error: function (xhr) {
                $submitBtn.prop('disabled', false);
                if (xhr.status === 422) {
                    const errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                    Object.values(errors).forEach(function (list) {
                        list.forEach(function (msg) { if (window.toastr) toastr.error(msg); });
                    });
                } else if (xhr.status === 409) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || '';
                    if (msg) $('#smart-banner-error-message').html('<p>' + escapeHtml(msg) + '</p>');
                    $('#smart-banner-error-modal').modal('show');
                } else if (window.toastr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'An unexpected error occurred.');
                }
            }
        });
    });

    $(document).on('click', '#smart_banner_reset_btn', function (e) {
        e.preventDefault();
        if (currentBanner) {
            populateForm(currentBanner);
        } else {
            resetForm();
        }
    });

    $(function () {
        const flash = sessionStorage.getItem('smart_banner_flash');
        if (flash) {
            sessionStorage.removeItem('smart_banner_flash');
            if (window.toastr) toastr.success(flash);
        }
        initDatePicker();
        initTimePicker();
        initSelect2($('#smartBannerForm_offcanvas select.js-select2-custom'));
        bindCharCounters();
        toggleDateWrapper();
        applyModuleTypeRestrictions();
        applyTargetMode($redirectTypeSelect.val() || 'category');
    });
})(jQuery);
