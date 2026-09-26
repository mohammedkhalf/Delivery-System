@extends('layouts.vendor.app')

@section('title', translate('messages.POS Orders'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/delivery-type.css') }}">
@endpush

@section('content')
    @php($store_data = \App\CentralLogics\Helpers::get_store_data())
    <section class="section-content padding-y-sm bg-default mt-1">
        <div class="content container-fluid">
            <div class="d-flex flex-wrap">
                <div class="order--pos-left">
                    <div class="card h-100">
                        <div class="card-header bg-light border-0">
                            <h5 class="card-title">
                                <span class="card-header-icon">
                                    <i class="tio-incognito"></i>
                                </span>
                                <span>
                                    {{ translate('products') }}
                                </span>
                            </h5>
                        </div>
                        <div class="card-header d-flex flex-wrap justify-content-between ">
                            <div class="w-100">
                                <div class="row g-2 justify-content-around">
                                    <div class="col-sm-6">
                                        <form id="search-form" class="search-form m-0">
                                            <!-- Search -->
                                            <div class="input-group input--group">
                                                <input id="datatableSearch" type="search"
                                                    value="{{ $keyword ?? '' }}" name="pos_search"
                                                    class="form-control h--45px"
                                                    placeholder="{{ translate('messages.ex_:_search_here') }}"
                                                    aria-label="{{ translate('messages.search_here') }}">
                                                <button class="btn btn--secondary h--45px" type="submit"><i
                                                        class="tio-search"></i></button>
                                            </div>
                                            <!-- End Search -->
                                        </form>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <select name="category" id="category" class="form-control js-select2-custom set-filter"
                                                title="{{ translate('messages.select_category') }}"
                                                    data-url="{{url()->full()}}"
                                                    data-filter="category_id">
                                                <option value="">{{ translate('messages.all_categories') }}</option>
                                                @foreach ($categories as $item)
                                                    <option value="{{ $item->id }}"
                                                        {{ $category == $item->id ? 'selected' : '' }}>
                                                        {{ Str::limit($item->name, 20, '...') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column" id="items">

                            @if ($products->count() > 0)

                                <div class="row g-3 mb-auto">
                                    @foreach ($products as $product)
                                        <div class="order--item-box item-box">
                                            @include('vendor-views.pos._single_product', [
                                                'product' => $product,
                                                'store' => $store_data,
                                            ])
                                        </div>
                                    @endforeach
                                </div>

                                @if ($products->count() >= 13)
                                    <hr>
                                @endif

                                <div class="page-area mt-2">
                                    {!! $products->withQueryString()->links() !!}
                                </div>

                            @else

                                <div class="search--no-found text-center py-5 w-100">
                                    <img src="{{ asset('public/assets/admin/img/search-icon.png') }}" alt="img">
                                    <p class="mt-3">
                                        {{ translate('messages.no_products_on_store_pos_search') }}
                                    </p>
                                </div>

                            @endif

                        </div>
                    </div>
                </div>
                <div class="order--pos-right">
                    <div class="card">
                        <div class="card-header bg-light border-0 m-1">
                            <h5 class="card-title">
                                <span>
                                    {{ translate('messages.Billing Section') }}
                                </span>
                            </h5>
                        </div>
                        <div class="w-100">
                            <div class="d-flex flex-wrap flex-row p-2 add--customer-btn">
                                <select id='customer' name="customer_id"
                                    data-placeholder="{{ translate('messages.walk_in_customer') }}"
                                    class="js-data-example-ajax form-control">
                                    @if (isset($customer) && $customer)
                                        <option selected value="{{ $customer->id }}">
                                            {{ $customer->f_name . ' ' . $customer->l_name }} ({{ $customer->phone }})
                                        </option>
                                    @endif
                                </select>
                                <button class="btn btn--primary px-3" data-toggle="modal"
                                    data-target="#add-customer">{{ translate('messages.add_new_customer') }}</button>
                            </div>
                            @if ($store_data->sub_self_delivery == 1)
                                <div class="pos--delivery-options">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title">
                                            <span class="card-title-icon">
                                                <i class="tio-user"></i>
                                            </span>
                                            <span>{{ translate('messages.Delivery Information') }}</span>
                                        </h5>
                                        <span class="delivery--edit-icon text-primary" id="delivery_address"
                                            data-toggle="modal" data-target="#paymentModal"><i class="tio-edit"></i></span>
                                    </div>
                                    <div class="pos--delivery-options-info d-flex flex-wrap" id="del-add">
                                        @include('vendor-views.pos._address')
                                    </div>
                                </div>
                            @endif
                        </div>


                        @include('partials.delivery-type-selector', [
                            'getUrl'            => route('vendor.pos.delivery_type.get'),
                            'setUrl'            => route('vendor.pos.delivery_type.set'),
                            'zoneId'            => \App\CentralLogics\Helpers::get_store_data()?->zone_id ?? '',
                            'moduleId'          => \App\CentralLogics\Helpers::get_store_data()?->module_id ?? '',
                            'storeId'           => \App\CentralLogics\Helpers::get_store_data()?->id ?? '',
                            'storeDeliveryTime' => \App\CentralLogics\Helpers::get_store_data()?->delivery_time ?? '',
                        ])

                        <div class='w-100' id="cart">
                            @include('vendor-views.pos._cart')
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- container //  -->
    </section>

    <div class="modal fade" id="add-customer" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.add_new_customer') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{route('vendor.pos.customer-store')}}" method="post" id="product_form">
                        @csrf
                        <div class="row">
                            <div class="col-12 col-lg-6">
                                <div class="form-group">
                                    <label for="f_name" class="input-label">{{ translate('first_name') }} <span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input type="text" id="f_name" name="f_name" class="form-control"
                                        placeholder="{{ translate('first_name') }}" required>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="form-group">
                                    <label for="l_name" class="input-label">{{ translate('last_name') }} <span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input type="text" id="l_name" name="l_name" class="form-control"
                                        placeholder="{{ translate('last_name') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-lg-6">
                                <div class="form-group">
                                    <label for="email" class="input-label">{{ translate('email') }}<span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control"
                                        placeholder="{{ translate('Ex_:_ex@example.com') }}" required>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="form-group">
                                    <label for="phone" class="input-label">{{ translate('phone') }} <span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input id="phone" type="tel" name="phone" class="form-control"
                                        placeholder="{{ translate('phone') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end">
                            <button type="reset" class="btn btn--reset">{{ translate('reset') }}</button>
                            <button type="submit" id="submit_new_customer"
                                class="btn btn--primary">{{ translate('submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Content -->
    <div class="modal fade" id="quick-view" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" id="quick-view-modal">

            </div>
        </div>
    </div>

    @php($order = \App\Models\Order::find(session('last_order')))
    @if ($order)
        @php(session(['last_order' => false]))
        <div class="modal fade" id="print-invoice" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ translate('messages.print_invoice') }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body row ff-emoji">
                        {{-- <div class="col-md-12">
                            <div class="text-center">
                                <input type="button" class="btn btn--primary non-printable text-white print-Div"
                                    value="Proceed, If thermal printer is ready." />
                                <a href="{{ url()->previous() }}" class="btn btn-danger non-printable">{{translate('messages.back')}}</a>
                            </div>
                            <hr class="non-printable">
                        </div> --}}
                        <div class="row m-auto" id="print-modal-content">
                            @include('vendor-views.pos.invoice')
                        </div>

                    </div>
                </div>
            </div>
        </div>
    @endif


@endsection

@push('script_2')
    <script async
        src="https://maps.googleapis.com/maps/api/js?key={{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value }}&libraries=places,marker&callback=initMap&loading=async&v=weekly">
    </script>

    <script src="{{asset('public/assets/admin/js/view-pages/pos.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/views/delivery-type-selector.js')}}?v={{ @filemtime(public_path('assets/admin/js/views/delivery-type-selector.js')) ?: 1 }}"></script>
    <script>
        "use strict";
        $(document).on('click', '.place-order-submit', function (event) {
            event.preventDefault();
            let customer_id = document.getElementById('customer');
            if(customer_id.value)
            {
                document.getElementById('customer_id').value = customer_id.value;
            }
                let form = document.getElementById('order_place');
                form.submit();
        });




        function togglePinLoading(isLoading) {
            let $btn = $('.delivery-Address-Store');
            if (!$btn.length) {
                return;
            }
            if (isLoading) {
                if (!$btn.data('original-html')) {
                    $btn.data('original-html', $btn.html());
                }
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>{{ translate('calculating') }}...');
            } else {
                $btn.prop('disabled', false).html($btn.data('original-html'));
            }
        }

        function initMap() {
        const mapId = "{{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value }}"

            let map = new google.maps.Map(document.getElementById("map"), {
                zoom: 13,
                center: {
                    lat: {{ $store_data ? $store_data['latitude'] : '23.757989' }},
                    lng: {{ $store_data ? $store_data['longitude'] : '90.360587' }}
                },
                mapId: mapId
            });

            let zonePolygon = null;

            //get current location block
            let infoWindow = new google.maps.InfoWindow();
            const geoErrorMessages = {
                geolocationFailed: "{{ translate('The Geolocation service failed') }}",
                noGeolocationSupport: "{{ translate('Your browser doesn`t support geolocation') }}",
            };
            // Try HTML5 geolocation.

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                      let  myLatlng = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        infoWindow.setPosition(myLatlng);
                        infoWindow.setContent("Location found.");
                        infoWindow.open(map);
                        map.setCenter(myLatlng);
                    },
                    () => {
                        handleLocationError(true, infoWindow, map.getCenter(), map, geoErrorMessages);
                    }
                );
            } else {
                // Browser doesn't support Geolocation
                handleLocationError(false, infoWindow, map.getCenter(), map, geoErrorMessages);
            }
            //-----end block------
            const bounds = new google.maps.LatLngBounds();
            posInitPlaceSearch({
                map: map,
                inputId: "pac-input",
                outOfCoverageMessage: '{{ translate('messages.out_of_coverage') }}',
                getZonePolygon: () => zonePolygon,
                @if ($store_data)
                onLocationSelected: function (location, address) {
                    togglePinLoading(true);
                    posCalculateDeliveryDistance({
                        origins: [
                            { lat: {{ $store_data['latitude'] }}, lng: {{ $store_data['longitude'] }} },
                            "{{ $store_data->address }}"
                        ],
                        destinations: [
                            address,
                            { lat: location.lat(), lng: location.lng() }
                        ],
                        geocodedAddress: address,
                        extraChargeUrl: '{{ route('vendor.pos.extra_charge') }}',
                        currencySymbol: '{{ \App\CentralLogics\Helpers::currency_symbol() }}',
                        warningMessage: '{{ translate('Please pin a more precise location to calculate delivery fee') }}',
                        toggleLoading: togglePinLoading,
                    });
                },
                @endif
            });
            @if ($store_data)
                $.get({
                    url: '{{ url('/') }}/admin/zone/get-coordinates/{{ $store_data->zone_id }}',
                    dataType: 'json',
                    success: function(data) {
                        zonePolygon = new google.maps.Polygon({
                            paths: data.coordinates,
                            strokeColor: "#FF0000",
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: 'white',
                            fillOpacity: 0,
                        });
                        zonePolygon.setMap(map);
                        zonePolygon.getPaths().forEach(function(path) {
                            path.forEach(function(latlng) {
                                bounds.extend(latlng);
                                map.fitBounds(bounds);
                            });
                        });
                        map.setCenter(data.center);
                        google.maps.event.addListener(zonePolygon, 'click', function(mapsMouseEvent) {
                            infoWindow.close();
                            // Create a new InfoWindow.
                            infoWindow = new google.maps.InfoWindow({
                                position: mapsMouseEvent.latLng,
                                content: JSON.stringify(mapsMouseEvent.latLng.toJSON(), null,
                                    2),
                            });
                            let coordinates;
                             coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
                             coordinates = JSON.parse(coordinates);

                            document.getElementById('latitude').value = coordinates['lat'];
                            document.getElementById('longitude').value = coordinates['lng'];
                            infoWindow.open(map);

                            let geocoder  = new google.maps.Geocoder();
                            let latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);

                            togglePinLoading(true);

                            geocoder.geocode({
                                'latLng': latlng
                            }, function(results, status) {
                                if (status !== google.maps.GeocoderStatus.OK || !results[1]) {
                                    togglePinLoading(false);
                                    toastr.warning('{{ translate('Please pin a more precise location to calculate delivery fee') }}', {
                                        CloseButton: true,
                                        ProgressBar: true
                                    });
                                    return;
                                }

                                let address = results[1].formatted_address;
                                posCalculateDeliveryDistance({
                                    origins: [
                                        { lat: {{ $store_data['latitude'] }}, lng: {{ $store_data['longitude'] }} },
                                        "{{ $store_data->address }}"
                                    ],
                                    destinations: [
                                        address,
                                        { lat: coordinates['lat'], lng: coordinates['lng'] }
                                    ],
                                    geocodedAddress: address,
                                    extraChargeUrl: '{{ route('vendor.pos.extra_charge') }}',
                                    currencySymbol: '{{ \App\CentralLogics\Helpers::currency_symbol() }}',
                                    warningMessage: '{{ translate('Please pin a more precise location to calculate delivery fee') }}',
                                    toggleLoading: togglePinLoading,
                                });
                            });
                        });
                    },
                });
            @endif

        }

        $(document).on('ready', function() {
            @if ($order)
                $('#print-invoice').modal('show');
            @endif
        });


        $('#search-form').on('submit', function(e) {
            e.preventDefault();
            let keyword = $('#datatableSearch').val();
            let nurl = new URL('{!! url()->full() !!}');
            nurl.searchParams.set('pos_keyword', keyword);
            location.href = nurl;
        });

        $(document).on('input', '#datatableSearch', function () {
            let nurl = new URL(window.location.href);
            if (this.value === '' && nurl.searchParams.has('pos_keyword')) {
                nurl.searchParams.delete('pos_keyword');
                location.href = nurl;
            }
        });


        $(document).on('click', '.quick-View', function () {
            $.get({
                url: '{{ route('vendor.pos.quick-view') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('id')
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });

        $(document).on('click', '.quick-View-Cart-Item', function () {
            $.get({
                url: '{{ route('vendor.pos.quick-view-cart-item') }}',
                dataType: 'json',
                data: {
                    product_id:  $(this).data('product-id'),
                    item_key:  $(this).data('item-key'),
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });

        function checkAddToCartValidity() {
            let names = {};
            $('#add-to-cart-form input:radio').each(function() {
                names[$(this).attr('name')] = true;
            });
            let count = 0;
            $.each(names, function() {
                count++;
            });
            if ($('input:radio:checked').length === count) {
                return true;
            }
            return true;
        }

        function getVariantPrice() {
            if ($('#add-to-cart-form input[name=quantity]').val() > 0 && checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: '{{ route('vendor.pos.variant_price') }}',
                    data: $('#add-to-cart-form').serializeArray(),
                    success: function(data) {
                        $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                        $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
                    }
                });
            }
        }

        let form_submitted = false;
        $(document).on('click', '.add-To-Cart', function () {
            if (form_submitted) return false;
            form_submitted = true;
            $('.add-To-Cart').prop('disabled', true);
            if (checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                let form_id = 'add-to-cart-form'
                $.post({
                    url: '{{ route('vendor.pos.add-to-cart') }}',
                    data: $('#' + form_id).serializeArray(),
                    beforeSend: function() {
                        $('#loading').show();
                    },
                    success: function(data) {

                        if (data.data === 1) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Cart',
                                text: "{{ translate('messages.product_already_added_in_cart') }}"
                            });
                            return false;
                        } else if (data.data === 2) {
                            updateCart();
                            Swal.fire({
                                icon: 'info',
                                title: 'Cart',
                                text: "{{ translate('messages.product_has_been_updated_in_cart') }}"
                            });

                            return false;
                        } else if (data.data === 0) {
                            form_submitted = false;
                            $('.add-To-Cart').prop('disabled', false);
                            Swal.fire({
                                icon: 'error',
                                title: 'Cart',
                                text: '{{ translate('messages.Sorry, product out of stock') }}'
                            });
                            return false;
                        } else if (data.data === 'letiation_error') {
                            form_submitted = false;
                            $('.add-To-Cart').prop('disabled', false);
                            Swal.fire({
                                icon: 'error',
                                title: 'Cart',
                                text: data.message
                            });
                            return false;
                        }
                        $('.call-when-done').click();

                        toastr.success('{{ translate('messages.product_has_been_added_in_cart') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });

                        updateCart();
                    },
                    complete: function() {
                        $('#loading').hide();
                        form_submitted = false;
                        $('.add-To-Cart').prop('disabled', false);
                    }
                });
            } else {
                form_submitted = false;
                $('.add-To-Cart').prop('disabled', false);
                Swal.fire({
                    type: 'info',
                    title: '{{translate('Cart')}}',
                    text: '{{ translate('Please choose all the options') }}'
                });
            }

        });

        $(document).on('click', '.remove-From-Cart', function () {
            let key=  $(this).data('product-id');
            $.post('{{ route('vendor.pos.remove-from-cart') }}', {
                _token: '{{ csrf_token() }}',
                key: key
            }, function(data) {
                if (data.errors) {
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                } else {
                    updateCart();
                    toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }

            });
        });

        $(document).on('click', '.empty-Cart', function () {
            $.post('{{ route('vendor.pos.emptyCart') }}', {
                _token: '{{ csrf_token() }}'
            }, function() {
                $('#del-add').empty();
                $('#customer_id').val('');
                $('#customer').val(null).trigger('change');
                updateCart();
                toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            });
        });

        function setPosContactFields(name, phone) {
            var nameEl  = document.getElementById('contact_person_name');
            var phoneEl = document.getElementById('contact_person_number');
            if (nameEl)  { nameEl.value  = name  || ''; }
            if (phoneEl) { phoneEl.value = phone || ''; }
        }

        function updateCartThen(callback) {
            $.post('<?php echo e(route('vendor.pos.cart_items')); ?>', {
                _token: '<?php echo e(csrf_token()); ?>'
            }, function (data) {
                $('#cart').empty().html(data);
                if (typeof initTelInputs === 'function') { initTelInputs(); }
                syncDeliveryTypeFromCart();
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }

        $(document).on('change', '#customer', function () {
            var customerId = $(this).val();

            $('#del-add').empty();

            if (!customerId) {
                updateCartThen(function () {
                    setPosContactFields('', '');
                });
                return;
            }

            $.get({
                url: '{{ route('vendor.pos.getUserData') }}',
                dataType: 'json',
                data: { customer_id: customerId },
                success: function (data) {
                    if (data && data.view) {
                        $('#del-add').empty().html(data.view);
                    }
                    updateCartThen(function () {
                        setPosContactFields(
                            data && data.customer_name,
                            data && data.customer_phone
                        );
                    });
                },
            });
        });

        function syncDeliveryTypeFromCart() {
            if (window.deliveryTypeSelector && typeof window.deliveryTypeSelector.syncFromCart === 'function') {
                window.deliveryTypeSelector.syncFromCart();
            }
        }

        function updateCart() {
            $.post('<?php echo e(route('vendor.pos.cart_items')); ?>', {
                _token: '<?php echo e(csrf_token()); ?>'
            }, function(data) {
                $('#cart').empty().html(data);
                if (typeof initTelInputs === 'function') { initTelInputs(); }
                syncDeliveryTypeFromCart();
            });
        }

        document.addEventListener('delivery-type:changed', function () { updateCart(); });
        $(function () { syncDeliveryTypeFromCart(); });

        $(document).on('click', '.delivery-Address-Store', function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            let form_id = 'delivery_address_store';
            $.post({
                url: '{{ route('vendor.pos.add-delivery-info') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    if (data.errors) {
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                        $('#loading').hide();
                        return;
                    }
                    $('#del-add').empty().html(data.view);
                    updateCart();
                    $('.call-when-done').click();
                    $('#paymentModal').modal('hide');
                    setTimeout(function () {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css({
                            'overflow': '',
                            'padding-right': '',
                        });
                    }, 300);
                },
                error: function () {
                    $('#loading').hide();
                },
                complete: function() {
                    $('#loading').hide();
                }
            });
        });

        $(document).on('click', '.payable-amount', function (event) {
           let form_id = 'payable_store_amount';

                if($('#paid').val() < 0){
                    toastr.error('{{ translate('Amount_must_be_grater_then_0') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        event.preventDefault();
                        return;
                }
                if($('#paid').val() < $('#total_order_amount').val() ){
                    toastr.error('{{ translate('This_amount_must_grater_then_order_amount') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        event.preventDefault();
                        return;
                }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.pos.paid') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function() {

                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function() {
                    $('#loading').hide();
                    $('#insertPayableAmount').modal('hide');
                }
            });

        });

        $(function() {
            $(document).on('click', 'input[type=number]', function() {
                this.select();
            });
        });

        $(document).on('change', '.update-Quantity', function (e) {
            let element = $(e.target);
            let minValue = parseInt(element.attr('min'));
            let maxValue = parseInt(element.attr('max'));
            let valueCurrent = parseInt(element.val());
            let key = element.data('key');


            if (valueCurrent >= minValue && valueCurrent <= maxValue) {
                $.post('{{ route('vendor.pos.updateQuantity') }}', {
                    _token: '{{ csrf_token() }}',
                    key: key,
                    quantity: valueCurrent
                }, function() {
                    updateCart();
                });
            } else if(valueCurrent > maxValue){
                Swal.fire({
                    icon: 'error',
                    title: 'Cart',
                    text: 'Sorry, cart limit exceeded.'
                });
                element.val(element.data('oldValue'));
            }
            else {
                Swal.fire({
                    icon: 'error',
                    title: 'Cart',
                    text: '{{ translate('Sorry, the minimum value was reached') }}'
                });
                element.val(element.data('oldValue'));
            }

            // Allow: backspace, delete, tab, escape, enter and .
            if (e.type === 'keydown') {
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                    // Allow: Ctrl+A
                    (e.keyCode === 65 && e.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                    // let it happen, don't do anything
                    return;
                }
                // Ensure that it is a number and stop the keypress
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            }

        });

        $('.js-data-example-ajax').select2({
            ajax: {
                url: '{{ route('vendor.pos.customers') }}',
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

    </script>

@endpush
