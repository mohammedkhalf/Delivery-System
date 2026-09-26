

"use strict";

$(document).on('ready', function () {

    $('#min_purchase').data('previous-value', $('#min_purchase').val());
    $('#discount').data('previous-value', $('#discount').val());


    $('#discount_type').on('change', function () {
        discount_check();
    });
    $('#discount').on('click', function () {
        discount_check();
    });
    $('#min_purchase').on('click', function () {
        discount_check();
    });
    function discount_check() {
        if ($('#discount_type').val() == 'amount') {
            $('#max_discount').attr("readonly", "true").attr("min", 0).removeAttr("required");
            $('#max_discount').val(0);
            let minPurchase = parseFloat($('#min_purchase').val());
            if (!isNaN(minPurchase) && minPurchase > 0) {
                $('#discount').attr('max', minPurchase);
            } else {
                $('#discount').removeAttr('max');
            }
            validateDiscount();
        }
        else {
            if ($('#discount_type').val() == 'percent') {
                $('#max_discount').removeAttr("readonly").attr("min", "0.01").attr("required", "required");
                if ((parseFloat($('#max_discount').val()) || 0) <= 0) {
                    $('#max_discount').val('');
                }
            }
            $('#discount').attr('max', 100);
        }
    }

    $('#date_from').attr('min', (new Date()).toISOString().split('T')[0]);
    $('#date_to').attr('min', (new Date()).toISOString().split('T')[0]);


    coupon_type_change($('#coupon_type').val());
});

$("#date_from").on("change", function () {
    $('#date_to').attr('min', $(this).val());
});

$("#date_to").on("change", function () {
    $('#date_from').attr('max', $(this).val());
});


$(document).on('ready', function () {

    $('#coupon_type').on('change', function () {
        let coupon_type = $(this).val();
        coupon_type_change(coupon_type);
    });

});
    function validateDiscount() {
        let discountType = $('#discount_type').val();
        let discountInput = $('#discount');
        let minPurchase = parseFloat($('#min_purchase').val()) || 0;
        let discountValue = parseFloat(discountInput.val()) || 0;

        if (discountType === 'amount' && discountValue > minPurchase) {
            discountInput.val(discountValue);

        }
    }

    $(document).on('click', '.data-info-show', function() {
        let id = $(this).data('id');
        let url = $(this).data('url');
        $('#content-disable').addClass('disabled');
        fetch_data(id, url)
    })

    function fetch_data(id, url) {
        $.ajax({
            url: url,
            type: "get",
            beforeSend: function() {
                $('#data-view').empty();
                $('#loading').show()
            },
            success: function(data) {
                $("#data-view").append(data.view);
            },
            complete: function() {
                $('#loading').hide()
            }
        })
    }
function coupon_type_change(coupon_type) {


        if(coupon_type ==='free_delivery')
        {
            $('#discount_type').prop("disabled", true).val("").trigger("change");
            $('#max_discount').val(0).prop("readonly", true).removeAttr("required").attr("min","0");
            $('#discount').val(0).prop("readonly", true).removeAttr("required").attr("min","0");
            $('#discount_type_div').addClass('d-none');
            $('#max_discount_div').addClass('d-none');
            $('#discount_div').addClass('d-none');
        }
        else{
            $('#discount').removeAttr("readonly").attr("required","true").attr("min","1");
            $('#discount_type').removeAttr("disabled").attr("required","true");
            if (!$('#discount_type').val()) {
                $('#discount_type').val('percent');
            }
            $('#discount_type_div').removeClass('d-none');
            $('#max_discount_div').removeClass('d-none');
            $('#discount_div').removeClass('d-none');
            if ($('#discount_type').val() === 'amount') {
                $('#max_discount').val(0).attr("readonly", "true").attr("min", 0).removeAttr("required");
            } else {
                $('#max_discount').removeAttr("readonly").attr("min", "0.01").attr("required", "required");
                if ((parseFloat($('#max_discount').val()) || 0) <= 0) {
                    $('#max_discount').val('');
                }
            }
        }

}
        $(document).on('click', '.copy-to-clipboard', function() {
            copyToClipboardById($(this).data('id'));
        });

        function copyToClipboardById(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                navigator.clipboard.writeText(element.value)
                    .then(() => {
                        toastr.success('Copied to clipboard!');
                    })
                    .catch(() => {
                        toastr.error('Failed to copy!');
                    });
            } else {
                toastr.warning('Element not found.');
            }
        }
