"use strict";

(function () {
    var cfgEl = document.getElementById('order-page-config');
    if (!cfgEl) {
        return;
    }

    var routes = JSON.parse(cfgEl.dataset.routes || '{}');
    var t = JSON.parse(cfgEl.dataset.translations || '{}');
    var orderId = cfgEl.dataset.orderId;
    var orderProofCount = parseInt(cfgEl.dataset.orderProofCount || '0', 10);
    var shouldOpenEditOffcanvas = cfgEl.dataset.openEditOffcanvas === '1';
    var imgUploadPlaceholder = cfgEl.dataset.imgUpload;
    var imgItemPlaceholder = cfgEl.dataset.imgPlaceholder;

    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    var stagedUidCounter = 0;
    var stagedDeletionKeys = new Set();

    function safeParseJson(raw, fallback) {
        if (raw === undefined || raw === null || raw === '') return fallback;
        if (typeof raw === 'object') return raw;
        try {
            var parsed = JSON.parse(raw);
            return parsed === null ? fallback : parsed;
        } catch (e) {
            return fallback;
        }
    }

    function toArray(value) {
        if (Array.isArray(value)) return value;
        if (value === undefined || value === null || value === '') return [];
        if (typeof value === 'object') return Object.values(value);
        return [];
    }

    function parseAmount(str) {
        if (typeof str === 'number') return str;
        if (!str) return 0;
        var cleaned = String(str).replace(/[^0-9.\-]/g, '');
        var value = parseFloat(cleaned);
        return isNaN(value) ? 0 : value;
    }

    function formatLikeTemplate(template, amount) {
        var tpl = (template === undefined || template === null) ? '' : String(template).trim();
        var match = tpl.match(/[0-9][0-9.,\s]*/);
        if (!match) {
            return amount.toFixed(2);
        }
        var numberPart = match[0];
        var prefix = tpl.slice(0, match.index);
        var suffix = tpl.slice(match.index + numberPart.length);
        var dotIndex = numberPart.lastIndexOf('.');
        var decimals = dotIndex === -1 ? 0 : numberPart.length - dotIndex - 1;
        if (decimals > 4) decimals = 2;
        return prefix + amount.toFixed(decimals) + suffix;
    }

    function normalizeAddOns(rawAddOns) {
        var ids = [];
        var qtys = [];
        var list = toArray(safeParseJson(rawAddOns, []));
        list.forEach(function (entry) {
            if (entry === null || entry === undefined) return;
            if (typeof entry === 'object') {
                if (entry.id === undefined) return;
                ids.push(entry.id);
                qtys.push(entry.quantity !== undefined ? entry.quantity : 1);
            } else {
                ids.push(entry);
                qtys.push(1);
            }
        });
        return { ids: ids, qtys: qtys };
    }

    function computeDedupKey(itemId, variation, addOnIds, addOnQtys) {
        var variationCanon = '';
        try {
            var v = toArray(variation).map(function (entry) {
                if (entry && typeof entry === 'object') {
                    if (entry.type !== undefined) {
                        return { type: entry.type };
                    }
                    var labels = [];
                    toArray(entry.values).forEach(function (val) {
                        if (val && typeof val === 'object' && val.label !== undefined) {
                            labels.push(String(val.label));
                        } else if (val !== undefined && val !== null) {
                            labels.push(String(val));
                        }
                    });
                    labels.sort();
                    return { name: entry.name, values: labels };
                }
                return entry;
            });
            variationCanon = JSON.stringify(v);
        } catch (e) {
            variationCanon = '';
        }
        var pairs = (addOnIds || []).map(function (id, i) {
            return [String(id), String((addOnQtys || [])[i] !== undefined ? addOnQtys[i] : 1)];
        });
        pairs.sort(function (a, b) { return a[0] < b[0] ? -1 : (a[0] > b[0] ? 1 : 0); });
        return String(itemId) + '|' + variationCanon + '|' + JSON.stringify(pairs);
    }

    function rowLineTotal($row, quantity) {
        var unit = parseFloat($row.attr('data-unit-price')) || 0;
        var addon = parseFloat($row.attr('data-addon-total')) || 0;
        return unit * quantity + addon;
    }

    function refreshRowTotal($row) {
        var quantity = parseInt($row.find('.update-Quantity').val(), 10) || 1;
        var $cell = $row.find('[id^="item_total_price_"]');
        var template = $cell.text();
        $cell.text(formatLikeTemplate(template, rowLineTotal($row, quantity)));
    }

    function reindexStagedRows() {
        var $rows = $('#data-view tbody tr').filter(':visible');
        $rows.each(function (index) {
            $(this).find('td:first-child .text-dark').text(index + 1);
        });
        updateStagedCartCount();
        toggleDeleteButtons();
    }

    function toggleDeleteButtons() {
        var $rows = $('#data-view tbody tr').filter(':visible');
        var disable = $rows.length <= 1;
        $rows.find('.removeFromCart').prop('disabled', disable).toggleClass('disabled', disable);
    }

    function updateStagedCartCount() {
        var count = $('#data-view tbody tr').filter(':visible').length;
        $('.bg-list-count').first().text(count);
    }

    function rowDedupKey($row) {
        var addons = normalizeAddOns($row.attr('data-add-ons'));
        return computeDedupKey(
            $row.attr('data-item-id'),
            safeParseJson($row.attr('data-variation'), []),
            addons.ids,
            addons.qtys
        );
    }

    function buildStagedRowHtml(line) {
        var uid = ++stagedUidCounter;
        var quantity = Math.max(1, parseInt(line.quantity, 10) || 1);
        var unit = parseFloat(line.unitPrice) || 0;
        var addon = parseFloat(line.addonTotal) || 0;
        var total = formatLikeTemplate(line.priceTemplate || '', unit * quantity + addon);
        var img = line.image || imgItemPlaceholder;
        var addOnsJson = JSON.stringify(line.addOns || []).replace(/'/g, '&#39;');
        var variationJson = JSON.stringify(line.variation || []).replace(/'/g, '&#39;');
        var variantJson = JSON.stringify(line.variant || []).replace(/'/g, '&#39;');
        var html = '';
        html += '<tr class="custom__tr" data-key="' + uid + '" data-product-missing="0"';
        html += ' data-order-details-id=""';
        html += ' data-item-id="' + line.itemId + '"';
        html += ' data-item-campaign-id=""';
        html += ' data-quantity="' + quantity + '"';
        html += ' data-unit-price="' + unit + '"';
        html += ' data-addon-total="' + addon + '"';
        html += " data-variation='" + variationJson + "'";
        html += " data-variant='" + variantJson + "'";
        html += " data-add-ons='" + addOnsJson + "'>";
        html += '<td><div class="text-dark"></div></td>';
        html += '<td><div class="list-items-media min-w-176px d-flex align-items-center gap-2 cursor-pointer quick-view-cart-item" data-key="' + uid + '">';
        html += '<img width="44" height="44" src="' + img + '" alt="image" class="rounded onerror-image" data-onerror-image="' + imgItemPlaceholder + '">';
        html += '<div class="cont d-flex flex-column gap-1"><p class="fs-12 text-dark mb-0 max-w-187px line--limit-1">' + (line.name || '') + '</p></div>';
        html += '</div></td>';
        html += '<td><div class="product-quantity w-105px mx-auto"><div class="input-group bg-white rounded border d-flex flex-nowrap justify-content-center align-items-center">';
        html += '<span class="input-group-btn w-30px"><button class="btn px-2 btn-number w-30px decrease-quantity-button" type="button" data-type="minus" data-key="' + uid + '"><i class="tio-remove fs-16"></i></button></span>';
        html += '<input type="number" class="w-30px p-0 border-0 text-center fs-18 update-Quantity text-dark" name="qty[' + uid + ']" value="' + quantity + '" min="1">';
        html += '<span class="input-group-btn w-30px"><button class="btn px-2 btn-number increase-quantity-button w-30px" type="button" data-type="plus" data-key="' + uid + '"><i class="tio-add fs-16"></i></button></span>';
        html += '</div></div></td>';
        html += '<td class="fs-14 text-right text-dark"><div id="item_total_price_' + uid + '">' + total + '</div></td>';
        html += '<td class="text-center"><button type="button" class="btn rounded-circle mx-auto p-1 d-flex align-items-center justify-content-center w-25px h-25px btn-sm btn--danger removeFromCart" data-key="' + uid + '"><i class="tio-delete text-white"></i></button></td>';
        html += '</tr>';
        return { uid: uid, html: html };
    }

    function stageAddLine(line) {
        var addons = normalizeAddOns(line.addOns || []);
        var dedup = computeDedupKey(line.itemId, line.variation || [], addons.ids, addons.qtys);
        var $existing = null;
        $('#data-view tbody tr').each(function () {
            var $row = $(this);
            if (($row.attr('data-product-missing') === '1')) return;
            if (rowDedupKey($row) === dedup) {
                $existing = $row;
                return false;
            }
        });
        if ($existing && $existing.length) {
            var current = parseInt($existing.find('.update-Quantity').val(), 10) || 1;
            var next = current + Math.max(1, parseInt(line.quantity, 10) || 1);
            $existing.find('.update-Quantity').val(next);
            $existing.attr('data-quantity', next);
            refreshRowTotal($existing);
            highlightRow($existing);
            return;
        }
        var built = buildStagedRowHtml(line);
        var $tbody = $('#data-view tbody');
        if (!$tbody.length) {
            $tbody = $('<tbody></tbody>').appendTo($('#data-view table'));
        }
        $tbody.append(built.html);
        reindexStagedRows();
        highlightRow($('#data-view tbody tr[data-key="' + built.uid + '"]'));
    }

    function highlightRow($row) {
        if (!$row || !$row.length) return;
        $row[0].offsetHeight;
        $row.addClass('soft-blink');
        setTimeout(function () { $row.removeClass('soft-blink'); }, 1700);
    }

    function buildCommitPayload() {
        var carts = [];
        $('#data-view tbody tr').each(function () {
            var $row = $(this);
            if ($row.css('display') === 'none') return;
            var itemId = $row.attr('data-item-id');
            if (!itemId) return;
            var addons = normalizeAddOns($row.attr('data-add-ons'));
            var orderDetailsId = $row.attr('data-order-details-id');
            carts.push({
                order_details_id: orderDetailsId ? parseInt(orderDetailsId, 10) : null,
                item_id: parseInt(itemId, 10),
                quantity: Math.max(1, parseInt($row.find('.update-Quantity').val(), 10) || 1),
                variation: safeParseJson($row.attr('data-variation'), []),
                variant: safeParseJson($row.attr('data-variant'), []),
                add_on_ids: addons.ids,
                add_on_qtys: addons.qtys,
                unavailable: $row.attr('data-product-missing') === '1' ? 1 : 0
            });
        });
        return carts;
    }

    $('.self-delivery-warning').on('click', function (event) {
        event.preventDefault();
        toastr.info(t.self_delivery_disable, {
            CloseButton: true,
            ProgressBar: true
        });
    });

    $('.cancelled-status').on('click', function () {
        var reasonsTpl = document.getElementById('cancel-reasons-template');
        var selectHtml = reasonsTpl ? reasonsTpl.innerHTML : '';
        Swal.fire({
            title: t.are_you_sure,
            text: t.change_status_canceled,
            type: 'warning',
            html: '<select class="form-control js-select2-custom mx-1" name="reason" id="reason">' + selectHtml + '</select>',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: t.no,
            confirmButtonText: t.yes,
            reverseButtons: true,
            onOpen: function () {
                $('.js-select2-custom').select2({
                    minimumResultsForSearch: 5,
                    width: '100%',
                    placeholder: t.select_reason || 'Select Reason',
                    language: 'en'
                });
            }
        }).then(function (result) {
            if (result.value) {
                var reason = document.getElementById('reason').value;
                location.href = routes.orderStatus + '&reason=' + reason;
            }
        });
    });

    $('.order-status-change-alert').on('click', function () {
        var route = $(this).data('url');
        var message = $(this).data('message');
        var verification = $(this).data('verification');
        var processing = $(this).data('processing-time') || false;

        if (verification) {
            Swal.fire({
                title: t.enter_verification_code,
                input: 'text',
                inputAttributes: { autocapitalize: 'off' },
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                confirmButtonText: t.submit,
                showLoaderOnConfirm: true,
                preConfirm: function (otp) {
                    location.href = route + '&otp=' + otp;
                },
                allowOutsideClick: function () { return !Swal.isLoading(); }
            });
        } else if (processing) {
            Swal.fire({
                title: t.are_you_sure_q,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: t.cancel,
                confirmButtonText: t.submit,
                inputPlaceholder: t.enter_processing_time,
                input: 'text',
                html: message + '<br/><label>' + t.enter_processing_time_label + '</label>',
                inputValue: processing,
                preConfirm: function (processing_time) {
                    location.href = route + '&processing_time=' + processing_time;
                },
                allowOutsideClick: function () { return !Swal.isLoading(); }
            });
        } else {
            Swal.fire({
                title: t.are_you_sure_q,
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: t.no_cap,
                confirmButtonText: t.yes_cap,
                reverseButtons: true
            }).then(function (result) {
                if (result.value) {
                    location.href = route;
                }
            });
        }
    });

    $(function () {
        if (typeof $.fn.spartanMultiImagePicker === 'function') {
            $('#coba').spartanMultiImagePicker({
                fieldName: 'order_proof[]',
                maxCount: 6 - orderProofCount,
                rowHeight: '176px !important',
                groupClassName: 'spartan_item_wrapper min-w-176px max-w-176px',
                maxFileSize: '',
                placeholderImage: {
                    image: imgUploadPlaceholder,
                    width: '176px'
                },
                dropFileLabel: 'Drop Here',
                onAddRow: function () {},
                onRenderedPreview: function () {},
                onRemoveRow: function () {},
                onExtensionErr: function () {
                    toastr.error(t.invalid_image_type, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function () {
                    toastr.error(t.file_too_big, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        }

        toggleDeleteButtons();
    });

    $(document).on('click', '.addon-quantity-input-toggle', function (event) {
        var cb = $(event.target);
        if (cb.is(':checked')) {
            cb.siblings('.addon-quantity-input').css({ visibility: 'visible' });
        } else {
            cb.siblings('.addon-quantity-input').css({ visibility: 'hidden' });
        }
    });

    $(document).on('click', '.decrease-button', function () {
        var addonId = $(this).data('id');
        var addonQty = $('input[name="addon-quantity' + addonId + '"]');
        var currentValue = parseInt(addonQty.val(), 10);
        if (currentValue > 1) {
            addonQty.val(currentValue - 1);
            getVariantPrice();
        }
    });

    $(document).on('click', '.increase-button', function () {
        var addonId = $(this).data('id');
        var addonQty = $('input[name="addon-quantity' + addonId + '"]');
        var currentValue = parseInt(addonQty.val(), 10);
        addonQty.val(currentValue + 1);
        getVariantPrice();
    });

    $('.addon_quantity_input_toggle').on('change', function (event) {
        addonQuantityInputToggle(event);
    });

    function addonQuantityInputToggle(e) {
        var cb = $(e.target);
        if (cb.is(':checked')) {
            cb.siblings('.addon-quantity-input').css({ visibility: 'visible' });
        } else {
            cb.siblings('.addon-quantity-input').css({ visibility: 'hidden' });
        }
    }

    function bumpQuickViewBackdrop() {
        setTimeout(function () {
            $('.modal-backdrop').last().addClass('quick-view-backdrop');
        }, 10);
    }

    var editingRowKey = null;

    $(document).on('click', '.quick-view-cart-item', function () {
        var $row = $(this).closest('tr');
        var itemId = $row.attr('data-item-id');
        if (!itemId || $row.attr('data-product-missing') === '1') {
            return;
        }
        editingRowKey = $row.attr('data-key');
        var addons = normalizeAddOns($row.attr('data-add-ons'));
        quickView(itemId, {
            quantity: parseInt($row.attr('data-quantity'), 10) || 1,
            variation: safeParseJson($row.attr('data-variation'), []),
            addOnIds: addons.ids,
            addOnQtys: addons.qtys
        });
    });

    $('.quick-view').on('click', function () {
        editingRowKey = null;
        quickView($(this).data('product-id'));
    });

    window.quickView = function (product_id, prefill) {
        $.get({
            url: routes.quickView,
            dataType: 'json',
            data: { product_id: product_id, order_id: orderId },
            beforeSend: function () { $('#loading').show(); },
            success: function (data) {
                $('#quick-view').modal('show');
                bumpQuickViewBackdrop();
                $('#quick-view-modal').empty().html(data.view);
                setQuickViewButtonLabel(editingRowKey !== null);
                if (prefill) {
                    applyQuickViewPrefill(prefill);
                }
            },
            complete: function () { $('#loading').hide(); }
        });
    };

    function setQuickViewButtonLabel(isUpdate) {
        var label = isUpdate ? (t.update_cart || 'Update Cart') : (t.add_to_cart || 'Add to Cart');
        $('#add-to-cart-btn').html('<i class="tio-shopping-cart"></i> ' + label);
    }

    function applyQuickViewPrefill(prefill) {
        var $form = $('#add-to-cart-form');
        if (!$form.length) {
            return;
        }
        if (prefill.quantity) {
            $form.find('input[name="quantity"]').val(prefill.quantity);
        }
        toArray(prefill.variation).forEach(function (entry) {
            if (!entry || typeof entry !== 'object') {
                return;
            }
            if (entry.name !== undefined && entry.values !== undefined) {
                var labels = toArray(entry.values).map(function (v) {
                    return (v && typeof v === 'object' && v.label !== undefined) ? String(v.label) : String(v);
                });
                var prefix = null;
                $form.find('input[type="hidden"]').each(function () {
                    var nm = $(this).attr('name') || '';
                    if (nm.slice(-6) === '[name]' && $(this).val() === entry.name) {
                        prefix = nm.slice(0, -6);
                        return false;
                    }
                });
                if (prefix !== null) {
                    var target = prefix + '[values][label][]';
                    $form.find('input.form-check-input').each(function () {
                        if (($(this).attr('name') || '') === target && labels.indexOf(String($(this).val())) !== -1) {
                            $(this).prop('checked', true);
                        }
                    });
                }
            } else if (entry.type !== undefined) {
                var parts = String(entry.type).split('-');
                $form.find('input[type="radio"]').each(function () {
                    if (parts.indexOf(String($(this).val()).replace(/\s+/g, '')) !== -1) {
                        $(this).prop('checked', true);
                    }
                });
            }
        });
        (prefill.addOnIds || []).forEach(function (id, i) {
            var $cb = null;
            $form.find('input.addon-chek').each(function () {
                if (String($(this).val()) === String(id)) {
                    $cb = $(this);
                    return false;
                }
            });
            if (!$cb) {
                return;
            }
            $cb.prop('checked', true);
            $form.find('label.addon-quantity-input[for="' + $cb.attr('id') + '"]').removeClass('d-none');
            $form.find('input[name="addon-quantity' + id + '"]').val((prefill.addOnQtys || [])[i] || 1);
        });
        $form.find('input[name="quantity"]').trigger('change');
    }

    window.cartQuantityInitialize = function () {
        $('.btn-number').click(function (e) {
            e.preventDefault();
            var fieldName = $(this).attr('data-field');
            var type = $(this).attr('data-type');
            var input = $("input[name='" + fieldName + "']");
            var currentVal = parseInt(input.val());
            if (!isNaN(currentVal)) {
                if (type == 'minus') {
                    if (currentVal > input.attr('min')) {
                        input.val(currentVal - 1).change();
                    }
                    if (parseInt(input.val()) == input.attr('min')) {
                        $(this).attr('disabled', true);
                    }
                } else if (type == 'plus') {
                    if (currentVal < input.attr('max')) {
                        input.val(currentVal + 1).change();
                    }
                    if (parseInt(input.val()) == input.attr('max')) {
                        $(this).attr('disabled', true);
                    }
                }
            } else {
                input.val(0);
            }
        });

        $('.input-number').focusin(function () {
            $(this).data('oldValue', $(this).val());
        });

        $('.input-number').change(function () {
            var minValue = parseInt($(this).attr('min'));
            var maxValue = parseInt($(this).attr('max'));
            var valueCurrent = parseInt($(this).val());
            var name = $(this).attr('name');
            if (valueCurrent >= minValue) {
                $(".btn-number[data-type='minus'][data-field='" + name + "']").removeAttr('disabled');
            } else {
                Swal.fire({ icon: 'error', title: 'Cart', text: 'Sorry, the minimum value was reached' });
                $(this).val($(this).data('oldValue'));
            }
            if (valueCurrent <= maxValue) {
                $(".btn-number[data-type='plus'][data-field='" + name + "']").removeAttr('disabled');
            } else {
                Swal.fire({ icon: 'error', title: 'Cart', text: 'Sorry, stock limit exceeded.' });
                $(this).val($(this).data('oldValue'));
            }
        });

        $('.input-number').keydown(function (e) {
            if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                (e.keyCode == 65 && e.ctrlKey === true) ||
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    };

    window.getVariantPrice = function () {
        if ($('#add-to-cart-form input[name=quantity]').val() > 0) {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
            $.ajax({
                type: 'POST',
                url: routes.variantPrice,
                data: $('#add-to-cart-form').serializeArray(),
                success: function (data) {
                    $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                    $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
                }
            });
        }
    };

    $(document).on('click', '.update_order_item', function () {
        updateOrderItem();
    });

    function updateOrderItem(formId) {
        formId = formId || 'add-to-cart-form';
        var $form = $('#' + formId);
        if (!$form.length) {
            return;
        }
        if ($form.find('#add-to-cart-btn').prop('disabled')) {
            return;
        }

        var itemId = $form.find('input[name="id"]').val();
        if (!itemId) {
            return;
        }
        var quantity = Math.max(1, parseInt($form.find('input[name="quantity"]').val(), 10) || 1);

        var foodVar = {};
        var choiceSelections = [];
        var addonIds = [];
        var addonQtyByName = {};

        $form.serializeArray().forEach(function (field) {
            var name = field.name;
            if (name === 'quantity' || name === 'id' || name === 'order_id' || name === 'item_type' || name === '_token') {
                return;
            }
            var fm = name.match(/^variations\[(\d+)\]\[(\w+)\]/);
            if (fm) {
                var key = fm[1];
                var prop = fm[2];
                foodVar[key] = foodVar[key] || { name: '', labels: [] };
                if (prop === 'values') {
                    foodVar[key].labels.push(field.value);
                } else if (prop === 'name') {
                    foodVar[key].name = field.value;
                }
                return;
            }
            if (name === 'addon_id[]' || name === 'addon_id') {
                addonIds.push(field.value);
                return;
            }
            if (/^addon-quantity\d+$/.test(name)) {
                addonQtyByName[name] = field.value;
                return;
            }
            if (/^addon-price\d+$/.test(name)) {
                return;
            }
            choiceSelections.push(field.value);
        });

        var variation = [];
        var variant = [];
        var foodKeys = Object.keys(foodVar).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
        if (foodKeys.length) {
            foodKeys.forEach(function (key) {
                if (!foodVar[key].labels.length) {
                    return;
                }
                variation.push({
                    name: foodVar[key].name,
                    values: foodVar[key].labels.map(function (label) { return { label: label }; })
                });
            });
        } else if (choiceSelections.length) {
            var type = choiceSelections.map(function (value) { return String(value).replace(/\s+/g, ''); }).join('-');
            if (type) {
                variation.push({ type: type });
            }
        }

        var addOns = addonIds.map(function (id) {
            var qty = parseInt(addonQtyByName['addon-quantity' + id], 10);
            return { id: parseInt(id, 10), quantity: isNaN(qty) ? 1 : qty };
        });

        var chosenText = $form.find('#chosen_price').text();
        var chosenTotal = parseAmount(chosenText);
        var unitPrice = chosenTotal > 0 ? chosenTotal / quantity : 0;
        var priceTemplate = chosenText && chosenTotal > 0 ? chosenText : '';

        var name = $('#quick-view-modal').find('.product-title').filter(function () {
            return $(this).text().trim().length > 0;
        }).first().text().trim();
        var image = $('#quick-view-modal').find('.img-responsive').first().attr('src');

        if (editingRowKey !== null) {
            var $editRow = $('#data-view tbody tr[data-key="' + editingRowKey + '"]');
            if ($editRow.length && $editRow.attr('data-order-details-id')) {
                stagedDeletionKeys.add(String($editRow.attr('data-order-details-id')));
            }
            $editRow.remove();
            editingRowKey = null;
        }

        stageAddLine({
            itemId: itemId,
            name: name,
            image: image,
            quantity: quantity,
            variation: variation,
            variant: variant,
            addOns: addOns,
            unitPrice: unitPrice,
            addonTotal: 0,
            priceTemplate: priceTemplate
        });

        $('#quick-view').modal('hide');
        toastr.success(t.added_to_cart, { CloseButton: true, ProgressBar: true });
    }

    window.update_order_item = updateOrderItem;

    var pendingDeleteKey = null;

    $(document).on('click', '.removeFromCart', function () {
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return;
        }
        pendingDeleteKey = $(this).data('key');
        $('#food_list_delete').modal('show');
        setTimeout(function () {
            $('.modal-backdrop').last().addClass('food-delete-backdrop');
        }, 10);
    });

    $(document).on('click', '#confirm-remove-cart-item', function () {
        if (pendingDeleteKey === null) {
            return;
        }
        removeFromCart(pendingDeleteKey);
        pendingDeleteKey = null;
    });

    function removeFromCart(key) {
        var $row = $('#data-view tbody tr[data-key="' + key + '"]');
        if (!$row.length) {
            return;
        }
        var orderDetailsId = $row.attr('data-order-details-id');
        if (orderDetailsId) {
            stagedDeletionKeys.add(String(orderDetailsId));
        }
        $row.remove();
        reindexStagedRows();
        toastr.success(t.item_removed, { CloseButton: true, ProgressBar: true });
    }

    $('.submit-edit-order').on('click', function () {
        var carts = buildCommitPayload();
        if (!carts.length) {
            toastr.error(t.cart_empty, { CloseButton: true, ProgressBar: true });
            return;
        }
        Swal.fire({
            title: t.are_you_sure,
            text: t.submit_all_confirm,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: t.no,
            confirmButtonText: t.yes,
            reverseButtons: true
        }).then(function (result) {
            if (!result.value) {
                return;
            }
            var token = getCsrfToken();
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
            $.ajax({
                type: 'POST',
                url: routes.orderUpdate,
                dataType: 'json',
                traditional: false,
                data: { _token: token, carts: carts },
                beforeSend: function () { $('#loading').show(); },
                success: function (data) {
                    if (data && data.errors && data.errors.length) {
                        for (var i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, { CloseButton: true, ProgressBar: true });
                        }
                        return;
                    }
                    toastr.success((data && data.message) ? data.message : t.order_updated, { CloseButton: true, ProgressBar: true });
                    setTimeout(function () { location.reload(); }, 600);
                },
                error: function (xhr) {
                    var handled = false;
                    if (xhr && xhr.responseJSON && xhr.responseJSON.errors) {
                        var errs = xhr.responseJSON.errors;
                        for (var j = 0; j < errs.length; j++) {
                            if (errs[j] && errs[j].message) {
                                toastr.error(errs[j].message, { CloseButton: true, ProgressBar: true });
                                handled = true;
                            }
                        }
                    }
                    if (!handled) {
                        toastr.error(t.update_failed, { CloseButton: true, ProgressBar: true });
                    }
                },
                complete: function () { $('#loading').hide(); }
            });
        });
    });

    if (shouldOpenEditOffcanvas) {
        $(document).ready(function () {
            $('#offcanvas__order_edit').addClass('open');
            $('body').addClass('modal-open');
            $('#offcanvasOverlay').addClass('show');
            toggleDeleteButtons();
        });
    }

    $(document).on('click', '.reopen-edit-offcanvas', function () {
        $('#offcanvas__order_edit').addClass('open');
        $('body').addClass('modal-open');
        $('#offcanvasOverlay').addClass('show');
        toggleDeleteButtons();
    });

    var searchTimeout;
    $('#food_search').on('input', function () {
        clearTimeout(searchTimeout);
        var keyword = $(this).val().trim();
        var storeId = $(this).data('store-id');
        if (keyword.length < 2) {
            $('#search-dropdown').hide();
            $('#food-search-result').empty();
            $('#food-search-no-data').addClass('d-none');
            return;
        }
        searchTimeout = setTimeout(function () {
            $.get({
                url: routes.searchItems,
                data: { keyword: keyword, store_id: storeId },
                success: function (data) {
                    var items = data.items;
                    $('#search-dropdown').show();
                    if (!items || items.length === 0) {
                        $('#food-search-result').empty();
                        $('#food-search-no-data').removeClass('d-none');
                        return;
                    }
                    $('#food-search-no-data').addClass('d-none');
                    var html = '';
                    $.each(items, function (i, item) {
                        var safeName = String(item.name || '').replace(/"/g, '&quot;');
                        var safePrice = String(item.formatted_price || '').replace(/"/g, '&quot;');
                        var isAvailable = item.is_available !== false;
                        var outOfStock = item.tracks_stock && (item.stock === null || item.stock <= 0);
                        var itemClass = isAvailable ? 'cursor-pointer js-quick-view' : 'unavailable';
                        html += '<div class="search-item d-flex align-items-sm-center gap-2 p-2 border rounded ' + itemClass + '" data-product-id="' + item.id + '" data-available="' + (isAvailable ? 1 : 0) + '" data-has-variations="' + (item.has_variations ? 1 : 0) + '" data-has-addons="' + (item.has_addons ? 1 : 0) + '" data-name="' + safeName + '" data-image="' + (item.image || '') + '" data-price="' + safePrice + '">';
                        html += '<div class="list-items-media"><div class="thumb d-center position-relative rounded overflow-hidden w-65px h-65px">';
                        html += '<img width="65" height="65" src="' + item.image + '" alt="image" class="rounded onerror-image" data-onerror-image="' + imgItemPlaceholder + '">';
                        if (!isAvailable) {
                            html += '<div class="text-white fs-10 font-medium position-absolute unavail">' + (outOfStock ? (t.out_of_stock || 'Out of stock') : (t.unavailable || 'Unavailable')) + '</div>';
                        }
                        html += '</div></div>';
                        html += '<div class="d-flex w-100 flex-sm-nowrap flex-wrap align-items-center justify-content-between search-items-body">';
                        html += '<div class="cont d-flex flex-column gap-0"><p class="fs-14 text-dark mb-0 max-w-187px line--limit-1">' + item.name + '</p>';
                        var tags = '';
                        if (item.veg !== null && item.veg !== undefined) {
                            tags += '<span class="badge badge-soft-' + (item.veg ? 'success' : 'danger') + ' font-weight-normal px-1 py-0 fs-9 rounded-pill">' + (item.veg ? (t.veg || 'Veg') : (t.non_veg || 'Non Veg')) + '</span>';
                        }
                        if (item.is_halal) {
                            tags += '<span class="badge badge-soft-warning font-weight-normal px-1 py-0 fs-9 rounded-pill">' + (t.halal || 'Halal') + '</span>';
                        }
                        if (tags) {
                            html += '<div class="mt-1 d-flex flex-wrap gap-1">' + tags + '</div>';
                        }
                        if (item.tracks_stock) {
                            html += '<div class="fs-12">' + (outOfStock ? (t.out_of_stock || 'Out of stock') : ((t.stock_qty || 'Stock Qty') + ' : <span class="text-dark">' + item.stock + '</span>')) + '</div>';
                        } else if (!isAvailable) {
                            var notAvailText = (t.not_available_now || 'Not available');
                            if (item.available_time) {
                                notAvailText += ' (' + item.available_time + ')';
                            }
                            html += '<small class="fs-12">' + notAvailText + '</small>';
                        }
                        html += '</div>';
                        html += '<div class="text-sm-right cont d-flex flex-column gap-0">';
                        html += '<div class="text-dark">' + (t.price || 'Price') + '</div>';
                        html += '<div class="d-flex align-items-center gap-1">';
                        if (item.original_price) {
                            html += '<del class="text-gray1 fs-12">' + item.original_price + '</del>';
                        }
                        html += '<h6 class="m-0 font-semibold text-dark">' + item.formatted_price + '</h6>';
                        html += '</div></div></div></div>';
                    });
                    $('#food-search-result').html(html);
                }
            });
        }, 300);
    });

    function setSearchActiveItem($items, index) {
        $items.removeClass('kb-active');
        if (index < 0 || index >= $items.length) {
            return;
        }
        var $el = $items.eq(index);
        $el.addClass('kb-active');
        var el = $el.get(0);
        if (el && el.scrollIntoView) {
            el.scrollIntoView({ block: 'nearest' });
        }
    }

    $('#food_search').on('keydown', function (e) {
        if (!$('#search-dropdown').is(':visible')) {
            return;
        }
        var $items = $('#food-search-result .search-item');
        if ($items.length === 0) {
            if (e.key === 'Escape') {
                $('#search-dropdown').hide();
            }
            return;
        }
        var index = $items.index($items.filter('.kb-active'));
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSearchActiveItem($items, (index + 1) % $items.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSearchActiveItem($items, (index - 1 + $items.length) % $items.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            $items.eq(index < 0 ? 0 : index).trigger('click');
        } else if (e.key === 'Escape') {
            $('#search-dropdown').hide();
            $items.removeClass('kb-active');
        }
    });

    $(document).on('mouseenter', '#food-search-result .search-item', function () {
        var $items = $('#food-search-result .search-item');
        setSearchActiveItem($items, $items.index(this));
    });

    function refreshOffcanvasCartList() {
        reindexStagedRows();
    }

    function addItemDirectly(productId, name, image, formattedPrice) {
        stageAddLine({
            itemId: productId,
            name: name || '',
            image: image,
            quantity: 1,
            variation: [],
            variant: [],
            addOns: [],
            unitPrice: parseAmount(formattedPrice),
            addonTotal: 0,
            priceTemplate: formattedPrice || ''
        });
        toastr.success(t.added_to_cart, { CloseButton: true, ProgressBar: true });
    }

    $(document).on('click', '.js-quick-view', function () {
        var $item = $(this);
        var productId = $item.data('product-id');
        var hasVariations = parseInt($item.data('has-variations')) === 1;
        var hasAddons = parseInt($item.data('has-addons')) === 1;
        var name = $item.attr('data-name') || '';
        var image = $item.attr('data-image') || '';
        var price = $item.attr('data-price') || '';
        $('#food_search').val('');
        $('#search-dropdown').hide();
        $('#food-search-result').empty();
        editingRowKey = null;
        if (hasVariations || hasAddons) {
            quickView(productId);
        } else {
            addItemDirectly(productId, name, image, price);
        }
    });

    $(document).on('click', '.search-item.unavailable', function () {
        var msg = $(this).find('.unavail').text().trim() || (t.not_available_now || 'Not available');
        toastr.info(msg, { CloseButton: true, ProgressBar: true });
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.edit-search-form').length) {
            $('#search-dropdown').hide();
        }
    });

    $(document).on('click', '#data-view .increase-quantity-button, #data-view .decrease-quantity-button', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        if ($row.attr('data-product-missing') === '1') {
            return;
        }
        var $input = $row.find('.update-Quantity');
        var current = parseInt($input.val(), 10) || 1;
        var min = parseInt($input.attr('min'), 10) || 1;
        var next = $btn.hasClass('increase-quantity-button') ? current + 1 : Math.max(min, current - 1);
        if (next === current) {
            return;
        }
        $input.val(next);
        $row.attr('data-quantity', next);
        refreshRowTotal($row);
    });

    $(document).on('change input', '#data-view .update-Quantity', function () {
        var $input = $(this);
        var $row = $input.closest('tr');
        if (!$row.length || $row.attr('data-product-missing') === '1') {
            return;
        }
        var qty = parseInt($input.val(), 10) || 1;
        if (qty < 1) {
            qty = 1;
            $input.val(qty);
        }
        $row.attr('data-quantity', qty);
        refreshRowTotal($row);
    });

    $(document).on('click', '.offcanvas-trigger_controll-modal', function () {
        var target = $(this).data('target');
        $(this).closest('.modal').modal('hide');
        $(target).addClass('show');
        $('body').addClass('offcanvas-open');
    });
})();

(function () {
    var cfgEl = document.getElementById('order-page-config');
    if (!cfgEl) return;
    if (!$('.add-delivery-man').length && !$('.order_status_change_alert').length && !$('.js-select2-custom').length) {
        return;
    }

    var routes = JSON.parse(cfgEl.dataset.routes || '{}');
    var t = JSON.parse(cfgEl.dataset.translations || '{}');

    if ($.HSCore && $.HSCore.components && $.HSCore.components.HSSelect2) {
        $('.js-select2-custom').each(function () {
            $.HSCore.components.HSSelect2.init($(this));
        });
    }

    $('.add-delivery-man').on('click', function () {
        var id = $(this).data('id');
        var base = routes.addDeliveryManBase || '';
        $.ajax({
            type: 'GET',
            url: base + id,
            success: function () {
                location.reload();
                toastr.success(t.delivery_man_added || 'Successfully added', {
                    CloseButton: true,
                    ProgressBar: true
                });
            },
            error: function (response) {
                var msg = (response && response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : '';
                toastr.error(msg, {
                    CloseButton: true,
                    ProgressBar: true
                });
            }
        });
    });

    $('.order_status_change_alert').on('click', function () {
        var route = $(this).data('url');
        var message = $(this).data('message');
        var processing = $(this).data('processing');
        window.order_status_change_alert(route, message, processing);
    });

    window.order_status_change_alert = function (route, message, processing) {
        processing = processing || false;
        if (processing) {
            Swal.fire({
                title: t.are_you_sure_q,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: t.cancel,
                confirmButtonText: t.submit,
                inputPlaceholder: t.enter_processing_time,
                input: 'text',
                html: message + '<br/><label>' + t.enter_processing_time_label + '</label>',
                inputValue: processing,
                preConfirm: function (processing_time) {
                    location.href = route + '&processing_time=' + processing_time;
                },
                allowOutsideClick: function () { return !Swal.isLoading(); }
            });
        } else {
            Swal.fire({
                title: t.are_you_sure_q,
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: t.no_cap,
                confirmButtonText: t.yes_cap,
                reverseButtons: true
            }).then(function (result) {
                if (result.value) {
                    location.href = route;
                }
            });
        }
    };

    window.last_location_view = function () {
        toastr.warning(t.last_location_warning || 'Only available when order is out for delivery!', {
            CloseButton: true,
            ProgressBar: true
        });
    };
})();

(function () {
    var cfgEl = document.getElementById('order-page-config');
    if (!cfgEl) return;
    if (typeof google === 'undefined' || !google.maps) return;

    var t = JSON.parse(cfgEl.dataset.translations || '{}');
    var routes = JSON.parse(cfgEl.dataset.routes || '{}');
    var mapCfg = JSON.parse(cfgEl.dataset.map || '{}');
    var deliveryMen = JSON.parse(cfgEl.dataset.deliveryMen || '[]');

    var deliveryMan = deliveryMen;
    var map = null;
    var mapId = mapCfg.mapApiKey || '';
    var defaultLocation = mapCfg.defaultLocation || { lat: 0, lng: 0 };
    var orderType = mapCfg.orderType || '';
    var address = mapCfg.address || null;
    var store = mapCfg.store || null;
    var customer = mapCfg.customer || null;
    var deliveryManInfo = mapCfg.deliveryMan || null;
    var dmLastLocation = mapCfg.dmLastLocation || null;
    var markerIcons = mapCfg.markerIcons || {};
    var fallbackImages = mapCfg.fallbackImages || {};
    var zoneUrlBase = routes.zoneCoordinatesBase || '';

    var myLatlng;
    if (orderType === 'parcel' && address) {
        myLatlng = new google.maps.LatLng(address.latitude, address.longitude);
    } else if (store) {
        myLatlng = new google.maps.LatLng(store.latitude, store.longitude);
    } else {
        myLatlng = new google.maps.LatLng(defaultLocation.lat, defaultLocation.lng);
    }

    var dmbounds = new google.maps.LatLngBounds(null);
    var locationbounds = new google.maps.LatLngBounds(null);
    var dmMarkers = [];
    dmbounds.extend(myLatlng);
    locationbounds.extend(myLatlng);

    var myOptions = {
        center: myLatlng,
        zoom: 13,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        mapId: mapId,
        panControl: true,
        mapTypeControl: false,
        panControlOptions: { position: google.maps.ControlPosition.RIGHT_CENTER },
        zoomControl: true,
        zoomControlOptions: {
            style: google.maps.ZoomControlStyle.LARGE,
            position: google.maps.ControlPosition.RIGHT_CENTER
        },
        scaleControl: false,
        streetViewControl: false,
        streetViewControlOptions: { position: google.maps.ControlPosition.RIGHT_CENTER }
    };

    function buildMarkerImage(src) {
        var el = document.createElement('img');
        el.src = src;
        el.alt = 'Marker';
        el.style.width = '100%';
        el.style.height = '100%';
        el.style.borderRadius = '50%';
        return el;
    }

    function initializeGMap() {
        var canvas = document.getElementById('map_canvas');
        if (!canvas) return;
        map = new google.maps.Map(canvas, myOptions);
        var infowindow = new google.maps.InfoWindow();

        if (store) {
            var restaurantmarker = new google.maps.marker.AdvancedMarkerElement({
                map: map,
                position: new google.maps.LatLng(store.latitude, store.longitude),
                title: store.name_short || '',
                content: buildMarkerImage(markerIcons.restaurant)
            });
            google.maps.event.addListener(restaurantmarker, 'click', (function (restaurantmarker) {
                return function () {
                    var logo = store.logo_url || fallbackImages.store || '';
                    infowindow.setContent(
                        "<div style='float:left'><img style='max-height:40px;wide:auto;' src='" + logo + "'></div>" +
                        "<div class='text-break' style='float:right; padding: 10px;'><b>" + (store.name_short || '') + "</b><br /> " + (store.address || '') + "</div>"
                    );
                    infowindow.open(map, restaurantmarker);
                };
            })(restaurantmarker));
        }

        map.fitBounds(dmbounds);
        for (var i = 0; i < deliveryMan.length; i++) {
            if (deliveryMan[i].lat) {
                var point = new google.maps.LatLng(deliveryMan[i].lat, deliveryMan[i].lng);
                dmbounds.extend(point);
                map.fitBounds(dmbounds);
                var marker = new google.maps.marker.AdvancedMarkerElement({
                    map: map,
                    position: point,
                    title: deliveryMan[i].location,
                    content: buildMarkerImage(markerIcons.deliveryBoy)
                });
                dmMarkers[deliveryMan[i].id] = marker;
                google.maps.event.addListener(marker, 'click', (function (marker, i) {
                    return function () {
                        infowindow.setContent(
                            "<div style='float:left'><img style='max-height:40px;wide:auto;' src='" + deliveryMan[i].image_link + "'></div>" +
                            "<div style='float:right; padding: 10px;'><b>" + deliveryMan[i].name + "</b><br/> " + deliveryMan[i].location + "</div>"
                        );
                        infowindow.open(map, marker);
                    };
                })(marker, i));
            }
        }
    }

    function initMap() {
        var canvas = document.getElementById('map');
        if (!canvas) return;

        var latInput = document.getElementById('latitude');
        var lngInput = document.getElementById('longitude');
        var addressInput = document.querySelector('#shipping-address-modal input[name="address"]');

        var initialLat = latInput ? parseFloat(latInput.value) : NaN;
        var initialLng = lngInput ? parseFloat(lngInput.value) : NaN;
        var center;
        if (!isNaN(initialLat) && !isNaN(initialLng)) {
            center = { lat: initialLat, lng: initialLng };
        } else if (store) {
            center = { lat: parseFloat(store.latitude), lng: parseFloat(store.longitude) };
        } else {
            center = { lat: defaultLocation.lat || 23.757989, lng: defaultLocation.lng || 90.360587 };
        }

        var localMap = new google.maps.Map(canvas, {
            zoom: 16,
            center: center,
            mapId: mapId
        });

        var pinMarker = new google.maps.marker.AdvancedMarkerElement({
            map: localMap,
            position: center,
            gmpDraggable: true,
            title: 'Delivery location'
        });

        var geocoder = new google.maps.Geocoder();
        var zonePolygon = null;

        function applyLocation(latLng, skipGeocode) {
            if (latInput) latInput.value = latLng.lat();
            if (lngInput) lngInput.value = latLng.lng();
            pinMarker.position = latLng;
            if (skipGeocode || !addressInput) return;
            geocoder.geocode({ location: { lat: latLng.lat(), lng: latLng.lng() } }, function (results, status) {
                if (status === 'OK' && results && results[0]) {
                    addressInput.value = results[0].formatted_address;
                }
            });
        }

        localMap.addListener('click', function (e) {
            if (zonePolygon && google.maps.geometry && google.maps.geometry.poly &&
                !google.maps.geometry.poly.containsLocation(e.latLng, zonePolygon)) {
                toastr.error(t.out_of_coverage || 'Out of coverage', {
                    CloseButton: true,
                    ProgressBar: true
                });
                return;
            }
            applyLocation(e.latLng);
        });

        pinMarker.addListener('dragend', function (e) {
            var pos = e.latLng || (pinMarker.position && new google.maps.LatLng(pinMarker.position.lat, pinMarker.position.lng));
            if (!pos) return;
            if (zonePolygon && google.maps.geometry && google.maps.geometry.poly &&
                !google.maps.geometry.poly.containsLocation(pos, zonePolygon)) {
                toastr.error(t.out_of_coverage || 'Out of coverage', {
                    CloseButton: true,
                    ProgressBar: true
                });
                pinMarker.position = center;
                return;
            }
            applyLocation(pos);
        });

        var input = document.getElementById('pac-input');
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                }
            });
            var searchBox = new google.maps.places.SearchBox(input);
            localMap.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
            searchBox.addListener('places_changed', function () {
                var places = searchBox.getPlaces();
                if (!places || places.length === 0) return;
                var place = places[0];
                if (!place.geometry || !place.geometry.location) return;
                if (zonePolygon && google.maps.geometry && google.maps.geometry.poly &&
                    !google.maps.geometry.poly.containsLocation(place.geometry.location, zonePolygon)) {
                    toastr.error(t.out_of_coverage || 'Out of coverage', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    return;
                }
                if (place.geometry.viewport) {
                    localMap.fitBounds(place.geometry.viewport);
                } else {
                    localMap.setCenter(place.geometry.location);
                    localMap.setZoom(17);
                }
                applyLocation(place.geometry.location, true);
                if (addressInput) {
                    addressInput.value = place.formatted_address || place.name || addressInput.value;
                }
            });
        }

        if (store && store.zone_id && zoneUrlBase) {
            $.get({
                url: zoneUrlBase + store.zone_id,
                dataType: 'json',
                success: function (data) {
                    zonePolygon = new google.maps.Polygon({
                        paths: data.coordinates,
                        strokeColor: '#FF0000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: 'white',
                        fillOpacity: 0
                    });
                    zonePolygon.setMap(localMap);
                    google.maps.event.addListener(zonePolygon, 'click', function (e) {
                        applyLocation(e.latLng);
                    });
                }
            });
        }
    }

    function initializegLocationMap() {
        var canvas = document.getElementById('location_map_canvas');
        if (!canvas) return;
        map = new google.maps.Map(canvas, myOptions);
        var infowindow = new google.maps.InfoWindow();

        if (customer && address) {
            var customerMarker = new google.maps.marker.AdvancedMarkerElement({
                map: map,
                position: new google.maps.LatLng(address.latitude, address.longitude),
                title: (customer.f_name || '') + ' ' + (customer.l_name || ''),
                content: buildMarkerImage(markerIcons.customer)
            });
            google.maps.event.addListener(customerMarker, 'click', (function (customerMarker) {
                return function () {
                    var img = customer.image_url || fallbackImages.customer || '';
                    infowindow.setContent(
                        "<div style='float:left'><img style='max-height:40px;wide:auto;' src='" + img + "'></div>" +
                        "<div style='float:right; padding: 10px;'><b>" + (customer.f_name || '') + " " + (customer.l_name || '') + "</b><br />" + (address.address || '') + "</div>"
                    );
                    infowindow.open(map, customerMarker);
                };
            })(customerMarker));
            locationbounds.extend(customerMarker.position);
        }

        if (deliveryManInfo && dmLastLocation) {
            var dmmarker = new google.maps.marker.AdvancedMarkerElement({
                map: map,
                position: new google.maps.LatLng(dmLastLocation.latitude, dmLastLocation.longitude),
                title: (deliveryManInfo.f_name || '') + ' ' + (deliveryManInfo.l_name || ''),
                content: buildMarkerImage(markerIcons.deliveryBoy)
            });
            google.maps.event.addListener(dmmarker, 'click', (function (dmmarker) {
                return function () {
                    var img = deliveryManInfo.image_url || fallbackImages.deliveryMan || '';
                    infowindow.setContent(
                        "<div style='float:left'><img style='max-height:40px;wide:auto;' src='" + img + "'></div>" +
                        "<div style='float:right; padding: 10px;'><b>" + (deliveryManInfo.f_name || '') + " " + (deliveryManInfo.l_name || '') + "</b><br /> " + (dmLastLocation.location || '') + "</div>"
                    );
                    infowindow.open(map, dmmarker);
                };
            })(dmmarker));
            locationbounds.extend(dmmarker.position);
        }

        if (store) {
            var storeMarker = new google.maps.marker.AdvancedMarkerElement({
                map: map,
                position: new google.maps.LatLng(store.latitude, store.longitude),
                title: store.name_short || '',
                content: buildMarkerImage(markerIcons.restaurant)
            });
            google.maps.event.addListener(storeMarker, 'click', (function (storeMarker) {
                return function () {
                    var logo = store.logo_url || fallbackImages.storeAlt || '';
                    infowindow.setContent(
                        "<div style='float:left'><img style='max-height:40px;wide:auto;' src='" + logo + "'></div>" +
                        "<div style='float:right; padding: 10px;'><b>" + (store.name_short || '') + "</b><br /> " + (store.address || '') + "</div>"
                    );
                    infowindow.open(map, storeMarker);
                };
            })(storeMarker));
            locationbounds.extend(storeMarker.position);
        }

        google.maps.event.addListenerOnce(map, 'idle', function () {
            map.fitBounds(locationbounds);
        });
    }

    $(document).ready(function () {
        $('#myModal').on('shown.bs.modal', function () {
            initMap();
            $('#dmassign-map').css('width', '100%');
            $('#map_canvas').css('width', '100%');
        });

        $('#myModal').on('shown.bs.modal', function () {
            initializeGMap();
            google.maps.event.trigger(map, 'resize');
            map.setCenter(myLatlng);
        });

        $('#shipping-address-modal').on('shown.bs.modal', function () {
            initMap();
        });

        $('#locationModal').on('shown.bs.modal', function () {
            initializegLocationMap();
        });

        $('.dm_list').on('click', function () {
            var id = $(this).data('id');
            if (dmMarkers[id]) {
                map.panTo(dmMarkers[id].position);
                map.setZoom(13);
            }
        });
    });

    window.initializeGMap = initializeGMap;
    window.initMap = initMap;
})();
