"use strict";

$(document).ready(function () {
    let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

    $('.select-subwrapper').each(function () {
        const wrapper = $(this);
        const checkboxes = wrapper.find('.check-item .form-check-input');
        const allChecked = checkboxes.length > 0 && checkboxes.length === checkboxes.filter(':checked').length;
        wrapper.find('.check-all').prop('checked', allChecked);
    });

    const allCheckboxes = $('.check--item-wrapper .check-item .form-check-input');
    const allChecked = allCheckboxes.length > 0 && allCheckboxes.length === allCheckboxes.filter(':checked').length;
    $('#select-all').prop('checked', allChecked);
});

$('#reset-btn').on('click', function () {
    $('.check--item-wrapper .check-item .form-check-input').prop('checked', false);
    $('#select-all').prop('checked', false);
    $('.select-subwrapper .check-all').prop('checked', false);
});

$('#select-all').on('change', function () {
    const isChecked = this.checked === true;
    $('.check--item-wrapper .check-item .form-check-input').prop('checked', isChecked);
    $('.select-subwrapper .check-all').prop('checked', isChecked);
});

$('.check--item-wrapper .check-item .form-check-input').on('change', function () {
    const allCheckboxes = $('.check--item-wrapper .check-item .form-check-input');
    const allChecked = allCheckboxes.length === allCheckboxes.filter(':checked').length;
    $('#select-all').prop('checked', allChecked);
});

//Sub Select
$('.select-subwrapper .check-all').on('change', function () {
    const wrapper = $(this).closest('.select-subwrapper');
    const isChecked = $(this).is(':checked');

    wrapper.find('.check-item .form-check-input').prop('checked', isChecked);

    const allCheckboxes = $('.check--item-wrapper .check-item .form-check-input');
    const allChecked = allCheckboxes.length === allCheckboxes.filter(':checked').length;
    $('#select-all').prop('checked', allChecked);
});

// Handle individual checkbox update
$('.select-subwrapper .check-item .form-check-input').on('change', function () {
    const wrapper = $(this).closest('.select-subwrapper');
    const allCheckboxes = wrapper.find('.check-item .form-check-input');
    const allChecked = allCheckboxes.length === allCheckboxes.filter(':checked').length;

    // Automatically check/uncheck the "select all" based on child checkboxes
    wrapper.find('.check-all').prop('checked', allChecked);
});
