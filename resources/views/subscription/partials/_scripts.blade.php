<script>
    $('.plan-slider').owlCarousel({
        loop: false,
        margin: 30,
        responsiveClass:true,
        nav:false,
        dots:false,
        items: 3,
        center: true,
        startPosition: '{{ $index }}',

        responsive:{
            0: {
                items:1.1,
                margin: 10,
            },
            375: {
                items:1.3,
                margin: 30,
            },
            576: {
                items:1.7,
            },
            768: {
                items:2.2,
                margin: 40,
            },
            992: {
                items: 3,
                margin: 40,
            },
            1200: {
                items: 4,
                margin: 40,
            }
        }
    })

    "use strict";
    $('.status_change_alert').on('click', function (event) {
        let url = $(this).data('url');
        let message = $(this).data('message');
        status_change_alert(url, message, event)
    })

    function status_change_alert(url, message, e) {
        e.preventDefault();
        Swal.fire({
            title: '{{ translate('Are_you_sure?') }}',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.post({
                    url: url,
                    data: {
                        id: '{{ $store->id }}',
                        subscription_id:'{{ $store?->store_sub_update_application?->id }}',
                    },
                    beforeSend: function () {
                        $('#loading').show()
                    },
                    success: function (data) {
                        toastr.success('{{ translate('Successfully_canceled_the_subscription') }}!');
                    },
                    complete: function () {
                        $('#loading').hide();
                        location.reload();
                    }
                });
            }
        })
    }

    $('.shift_to_commission').on('click', function (event) {
        let url = $(this).data('url');
        let message = $(this).data('message');
        shift_to_commission(url, message, event)
    })

    function shift_to_commission(url, message, e) {
        e.preventDefault();
        Swal.fire({
            title: '{{ translate('Are_you_sure?') }}',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.post({
                    url: url,
                    data: {
                        id: '{{ $store->id }}',
                    },
                    beforeSend: function () {
                        $('#loading').show()
                    },
                    success: function (data) {
                        toastr.success('{{ translate('Successfully_Switched_To_Commission') }}!');
                    },
                    complete: function () {
                        $('#loading').hide();
                        location.reload();
                    }
                });
            }
        })
    }

    $(document).on('click', '.package_detail', function () {
        var url = $(this).attr('data-url');
        package_pay(url);
    });

    $(document).on('click', '#continue_btn', function () {
        $('#subscription-renew-modal').modal('show')
    });

    $(document).on('click', '#back_to_planes', function () {
        $('#plan-modal').modal('show')
    });

    function package_pay(url){
        $.ajax({
            url: url,
            method: 'get',
            beforeSend: function() {
                $('#loading').show();
                $('#plan-modal').modal('hide')
            },
            success: function(data){
                $('#data_package').html(data.view);
                if(data.disable_item_count !== null && data.disable_item_count > 0){
                    $('#product_warning').modal('show')
                    $('#disable_item_count').text(data.disable_item_count)
                } else{
                    $('#subscription-renew-modal').modal('show')
                }
            },
            complete: function() {
                $('#loading').hide();
            },
        });
    }

    $("#comission_status").on('change', function(){
        if($("#comission_status").is(':checked')){
            $('#comission').removeAttr('readonly');
        } else {
            $('#comission').attr('readonly', true);
            $('#comission').val('0');
        }
    });

    @if (!empty($enableQuickActions) && request()?->renew_now == true)
    var url = '{{ route($routePrefix.'.packageView',[$store?->store_sub?->package_id,$store->id ]) }}';
    package_pay(url);
        var url = new URL(window.location.href);
        var searchParams = new URLSearchParams(url.search);
        searchParams.delete('renew_now');
        var newUrl = url.origin + url.pathname + '?' + searchParams.toString();
        if (!searchParams.toString()) {
            newUrl = url.origin + url.pathname;
        }
        window.history.replaceState(null, '', newUrl);
    @endif

    @if (!empty($enableQuickActions) && request()?->open_plans == true)
    $('#plan-modal').modal('show');
        var url = new URL(window.location.href);
        var searchParams = new URLSearchParams(url.search);
        searchParams.delete('open_plans');
        var newUrl = url.origin + url.pathname + '?' + searchParams.toString();
        if (!searchParams.toString()) {
            newUrl = url.origin + url.pathname;
        }
        window.history.replaceState(null, '', newUrl);
    @endif
</script>
