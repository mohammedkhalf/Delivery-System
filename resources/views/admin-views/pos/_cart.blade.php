<div class="d-flex flex-row cart--table-scroll px-2">
    <table class="table table--vertical-middle">
        <thead class="text-muted thead-light">
            <tr class="text-center">
                <th class="border-bottom-0 border-top-0" scope="col">{{translate('messages.food')}}</th>
                <th class="border-bottom-0 border-top-0" scope="col">{{translate('messages.QTY')}}</th>
                <th class="border-bottom-0 border-top-0 text-right" scope="col">{{translate('messages.Unit_price')}}</th>
                <th class="border-bottom-0 border-top-0" scope="col">{{translate('messages.delete')}}</th>
            </tr>
        </thead>
        <tbody class="border-left border-right border-bottom">
        <?php
            $subtotal = 0;
            $addon_price = 0;
            $tax = session()->get('tax_amount');
            $discount = 0;
            $discount_type = 'amount';
            $discount_on_product = 0;
            $variation_price  = 0;
        ?>
        @if(session()->has('cart') && count( session()->get('cart')) > 0)
            <?php
                $cart = session()->get('cart');
                if(isset($cart['discount']))
                {
                    $discount = $cart['discount'];
                    $discount_type = $cart['discount_type'];
                }
            ?>
            @foreach(session()->get('cart') as $key => $cartItem)

            @if(is_array($cartItem))
                <?php
                $variation_price += $cartItem['variation_price'] ?? 0;
                $product_subtotal = ($cartItem['price'])*$cartItem['quantity'];
                $discount_on_product += ($cartItem['discount']*$cartItem['quantity']);
                $subtotal += $product_subtotal;
                $addon_price += $cartItem['addon_price'];
                ?>
            <tr>
                <td class="media align-items-center cursor-pointer quick-View-Cart-Item" data-product-id="{{$cartItem['id']}}" data-item-key="{{$key}}">
                    <img class="avatar avatar-sm mr-1 onerror-image"
                    src="{{ $cartItem['image_full_url'] }}" data-onerror-image="{{asset('public/assets/admin/img/100x100/2.png')}}" alt="{{$cartItem['name']}} image">
                    <div class="media-body">
                        <h6 class="text-hover-primary mb-0 fs-12">{{Str::limit($cartItem['name'], 14)}}</h6>
                        <small>{{Str::limit($cartItem['variant'], 20)}}</small>
                    </div>
                </td>
                <td class="text-center middle-align">
                    <input type="number"  data-key="{{$key}}"  class="amount--input form-control text-center update-Quantity" data-oldvalue="{{$cartItem['quantity']}}" value="{{$cartItem['quantity']}}" min="1"
                    max="{{ (isset($cartItem['stock_quantity']) && $cartItem['stock_quantity'] > 0) ?   ($cartItem['maximum_cart_quantity'] ?  min($cartItem['stock_quantity'], $cartItem['maximum_cart_quantity']) : $cartItem['stock_quantity'])  : $cartItem['maximum_cart_quantity'] ??  '9999999999' }}" >
                </td>
                <td class="text-right fs-14 font-medium">
                    {{\App\CentralLogics\Helpers::format_currency($product_subtotal)}}
                </td>
                <td>
                    <div class="d-flex justify-content-center">
                        <a href="javascript:" data-product-id="{{$key}}" class="pos-cart-remove-btn remove-From-Cart rounded-circle"> <i class="tio-delete-outlined"></i></a>
                    </div>
                </td>
            </tr>
            @endif
            @endforeach
        @endif
        </tbody>
    </table>
</div>

<?php
    $total = $subtotal + $addon_price;

    if ($discount_type == 'percent' && $discount > 0) {
        $discount_amount = (($total - $discount_on_product) * $discount) / 100;
    } else {
        $discount_amount = $discount;
    }

    $total -= ($discount_amount + $discount_on_product);

    $tax_amount = session()->get('tax_amount');
    $tax_included = session()->get('tax_included');
//    $tax_included = ($tax_included && $tax_amount > 0) ? 1 : 0;

    $base_delivery_fee = (float) session()->get('address.delivery_fee', 0);
    $pos_order_type    = (string) (session('order_type') ?? 'delivery');

    $pos_eligible_amount = max(0, $subtotal + $addon_price - $discount_on_product - ($discount_amount ?? 0));
    $pos_free_delivery   = \App\CentralLogics\DeliveryFeeLogic::effectiveFee(
        $base_delivery_fee,
        $store ?? null,
        $pos_eligible_amount,
        \App\CentralLogics\DeliveryFeeLogic::resolveCouponCodeFromSession(),
    );
    $delivery_fee              = (float) $pos_free_delivery['fee'];
    $pos_free_delivery_by      = $pos_free_delivery['free_by'];
    $pos_is_free_delivery      = (bool) $pos_free_delivery['is_free'];
    $delivery_fee_for_selector = $delivery_fee;

    $pos_module_zone_pivot = ($store && $store->zone_id)
        ? \App\Models\ModuleZone::query()
            ->where('module_id', $store->module_id)
            ->where('zone_id', $store->zone_id)
            ->first()
        : null;
    $pos_saver_enabled = (bool) ($pos_module_zone_pivot->additional_delivery_option_status ?? false);
    $pos_min_charge    = (float) ($pos_module_zone_pivot->minimum_delivery_charge ?? 0);

    $is_charge_eligible = $pos_saver_enabled
        && $pos_order_type === 'delivery'
        && $delivery_fee > 0
        && ($pos_min_charge <= 0 || $delivery_fee >= $pos_min_charge);

    $deliveryType       = session()->get('delivery_type', '');
    $deliveryTypeCharge = $is_charge_eligible ? (float) session()->get('delivery_type_charge', 0) : 0;
    $isExpressDelivery  = $is_charge_eligible && $deliveryType === 'express'        && $deliveryTypeCharge > 0;
    $isSlightlyDelay    = $is_charge_eligible && $deliveryType === 'slightly_delay' && $deliveryTypeCharge > 0;

    $pos_pro_discount             = (float) session()->get('pos_pro_discount', 0);
    $pos_pro_benefit_type         = session()->get('pos_pro_benefit_type');
    $pos_pro_delivery_offer_type  = session()->get('pos_pro_delivery_offer_type');
    $pos_pro_delivery_percentage  = (float) session()->get('pos_pro_delivery_percentage', 0);

    $pos_pro_delivery_savings = 0.0;
    if ($pos_pro_benefit_type === 'delivery_fee' && !$pos_is_free_delivery && $delivery_fee > 0) {
        if ($pos_pro_delivery_offer_type === 'full_free') {
            $pos_pro_delivery_savings = 0;
        } elseif ($pos_pro_delivery_offer_type === 'partial_free' && $pos_pro_delivery_percentage > 0 && $pos_pro_delivery_percentage < 100) {
            $pre_pro = $delivery_fee / (1 - ($pos_pro_delivery_percentage / 100));
            $pos_pro_delivery_savings = max(0, $pre_pro - $delivery_fee);
        }
    }

    $adjustedDeliveryFee = $delivery_fee;
    if ($isExpressDelivery) {
        $adjustedDeliveryFee = $delivery_fee + $deliveryTypeCharge;
    } elseif ($isSlightlyDelay) {
        $adjustedDeliveryFee = max(0, $delivery_fee - $deliveryTypeCharge);
    }

    $total -= $pos_pro_discount;
    $total += $adjustedDeliveryFee;

    $deliveryTypeLabels = [
        'standard'       => translate('messages.standard'),
        'express'        => translate('messages.express'),
        'slightly_delay' => translate('messages.slightly_delay'),
    ];
    $deliveryTypeSuffix = ($isExpressDelivery || $isSlightlyDelay)
        ? ' (' . ($deliveryTypeLabels[$deliveryType] ?? '') . ')'
        : ($pos_is_free_delivery && $pos_order_type === 'delivery' ? ' (' . translate('messages.Free Delivery') . ')' : '');
?>

@php
    $cart_has_address = session()->has('address') && is_array(session('address')) && isset(session('address')['delivery_fee']);
@endphp
<input type="hidden" id="cart_delivery_fee" data-value="{{ $delivery_fee_for_selector }}" value="{{ $delivery_fee_for_selector }}">
<input type="hidden" id="cart_order_type" data-value="{{ $pos_order_type }}" value="{{ $pos_order_type }}">
<input type="hidden" id="cart_has_address" data-value="{{ $cart_has_address ? 1 : 0 }}" value="{{ $cart_has_address ? 1 : 0 }}">
<div class="box p-3">
    <dl class="row text-dark">
        @if (Config::get('module.current_module_type') == 'food')

        <dd  class="col-6">{{translate('messages.addon')}}:</dd>
        <dd class="col-6 text-right">{{\App\CentralLogics\Helpers::format_currency($addon_price)}}</dd>
        @endif

        <dd  class="col-6">{{translate('messages.subtotal')}}
            @if ($tax_included ==  1)
                ({{ translate('messages.TAX_Included') }})
                @php($tax_amount=0)
            @endif
            :</dd>
        <dd class="col-6 text-right">{{\App\CentralLogics\Helpers::format_currency($subtotal+$addon_price)}}</dd>
        <dd  class="col-6">{{translate('messages.discount')}} :</dd>
        <dd class="col-6 text-right">- {{\App\CentralLogics\Helpers::format_currency(round($discount_on_product,2))}}</dd>
        @if ($pos_pro_discount > 0)
            <dd class="col-6">{{ translate('messages.Pro_Discount') }} :</dd>
            <dd class="col-6 text-right">- {{ \App\CentralLogics\Helpers::format_currency(round($pos_pro_discount, 2)) }}</dd>
        @endif
        <dd class="col-6">{{ translate('messages.delivery_fee') }}{{ $deliveryTypeSuffix }}
            @if ($pos_pro_delivery_savings > 0)
                @if ($pos_pro_delivery_offer_type === 'full_free')
                    <i class="tio-info-outined text-info" data-toggle="tooltip"
                       title="{{ translate('messages.Pro_Customer_free_delivery_applied') }}"></i>
                @else
                    <i class="tio-info-outined text-info" data-toggle="tooltip"
                       title="{{ translate('messages.Pro_Customer_partial_delivery_discount_applied') }} ({{ (float) $pos_pro_delivery_percentage }}%)"></i>
                @endif
            @endif
            :</dd>
        <dd class="col-6 text-right" id="delivery_price">
            {{ \App\CentralLogics\Helpers::format_currency($adjustedDeliveryFee) }}</dd>
        {{-- @if(isset($store) && $store?->sub_self_delivery)
            <dd class="col-12">
                <small class="text-warning">
                    <i class="tio-info-outined"></i>
                    {{ translate('This store delivers on its own — the vendor\'s delivery charge applies.') }}
                </small>
            </dd>
        @endif --}}
        @if ($tax_included !=  1)
            <dd  class="col-6">{{ translate('messages.tax') }}  : </dd>
            <dd class="col-6 text-right">
            {{\App\CentralLogics\Helpers::format_currency(round($tax_amount,2))}}</dd>
        @endif
        <dd  class="col-6 pr-0">
            <hr class="my-0">
        </dd>
        <dd  class="col-6 pl-0">
            <hr class="my-0">
        </dd>
        <dt  class="col-6">{{ translate('messages.total') }}  : </dt>
        <dt class="col-6 text-right">
            {{\App\CentralLogics\Helpers::format_currency(round($total+$tax_amount, 2))}}
        </dt>
    </dl>

    <form action="{{route('admin.pos.order')}}?store_id={{request('store_id')}}" id='order_place' method="post" >
        @csrf
        <input type="hidden" name="user_id" id="customer_id">
        <div class="pos--payment-options mt-3 mb-3">
            <p class="mb-3">{{ translate('paid_By') }}</p>
            <ul>
                @php($cod=\App\CentralLogics\Helpers::get_business_settings('cash_on_delivery'))
                @if ($cod['status'])
                <li>
                    <label>
                        <input type="radio" name="type" value="cash" hidden checked>
                        <span>{{ translate('Cash On Delivery') }}</span>
                    </label>
                </li>
                @endif
                @php($wallet=\App\CentralLogics\Helpers::get_business_settings('wallet_status'))
                @if ($wallet)
                <li>
                    <label>
                        <input type="radio" name="type" value="wallet" hidden {{ $cod['status']? '':'checked' }}>
                        <span>{{ translate('Wallet') }}</span>
                    </label>
                </li>
                @endif
            </ul>
        </div>

        <div class="row button--bottom-fixed g-1 bg-white">
            <div class="col-sm-6">
                <button type="button" class="btn h-100  btn-outline-danger btn-block empty-Cart" {{ (session()->has('cart') && count( session()->get('cart')) > 0)?'':'disabled' }}>{{ translate('messages.Clear Cart') }} </button>
            </div>
            <div class="col-sm-6">
                <button type="submit" class="btn  btn--primary place-order-submit btn-block">{{ translate('messages.place_order') }} </button>
            </div>
        </div>
    </form>
</div>

{{-- Delivery Address Modal --}}
<div class="modal fade" id="deliveryAddrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-scroll">
            <div class="modal-header bg-light border-bottom py-3">
                <h4 class="modal-title flex-grow-1 text-center">{{translate('delivery_options')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            @if ($store)
            <div class="modal-body p-xxl-4 p-3">
                <?php
                    if(session()->has('address')) {
                        $old = session()->get('address');
                    }else {
                        $old = null;
                    }
                ?>
                <form id='delivery_address_store'>
                    @csrf

                    <div class="row g-2" id="delivery_address">
                        <div class="col-md-6">
                            <label class="input-label"
                                for="contact_person_name">{{ translate('messages.contact_person_name') }}<span
                                            class="input-label-secondary text-danger">*</span></label>
                            <input  id="contact_person_name" type="text" class="form-control" name="contact_person_name"
                                value="{{ $old ? $old['contact_person_name'] : (isset($customer) ? $customer->f_name . ' ' . $customer->l_name : '' )}}" placeholder="{{ translate('messages.Ex :') }} Jhone">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label"
                                for="contact_person_number">{{ translate('Contact Number') }}<span
                                            class="input-label-secondary text-danger">*</span></label>
                            <input id="contact_person_number" type="tel" class="form-control" name="contact_person_number"
                                value="{{ $old ? $old['contact_person_number'] : (isset($customer) ? $customer->phone : '')  }}"  placeholder="{{ translate('messages.Ex :') }} +3264124565">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label" for="road">{{ translate('messages.Road') }}</label>
                            <input id="road" type="text" class="form-control" name="road" value="{{ $old ? $old['road'] : '' }}"  placeholder="{{ translate('messages.Ex :') }} 4th">
                        </div>
                        <div class="col-md-3">
                            <label  class="input-label" for="house">{{ translate('messages.House') }}</label>
                            <input id="house" type="text" class="form-control" name="house" value="{{ $old ? $old['house'] : '' }}" placeholder="{{ translate('messages.Ex :') }} 45/C">
                        </div>
                        <div class="col-md-3">
                            <label class="input-label" for="floor">{{ translate('messages.Floor') }}</label>
                            <input id="floor" type="text" class="form-control" name="floor" value="{{ $old ? $old['floor'] : '' }}"  placeholder="{{ translate('messages.Ex :') }} 1A">
                        </div>
                    </div>

                    <div class="border p-3 mt-3 rounded border-success">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="input-label" for="longitude">{{ translate('messages.longitude') }}<span
                                                class="input-label-secondary text-danger">*</span></label>
                                <input  type="text" class="form-control" id="longitude" name="longitude"
                                    value="{{ $old ? $old['longitude'] : '' }}" readonly >
                            </div>
                            <div class="col-md-6">
                                <label class="input-label" for="latitude">{{ translate('messages.latitude') }}<span
                                                class="input-label-secondary text-danger">*</span></label>
                                <input  type="text" class="form-control" id="latitude" name="latitude"
                                    value="{{ $old ? $old['latitude'] : '' }}" readonly>
                            </div>
                            <div class="col-md-12">
                                <label class="input-label" for="address">{{ translate('messages.address') }}</label>
                                <textarea id="address" name="address" class="form-control" cols="30" rows="3" placeholder="{{ translate('messages.Ex :') }} address">{{ $old ? $old['address'] : '' }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-1 justify-content-between mb-3">
                                    <div>
                                        <span class="text-danger">*</span>
                                        {{ translate(' pin the address in the map to calculate delivery fee') }}
                                    </div>
                                    <div class="btn btn--primary text-white">
                                        <input type="hidden" name="distance" id="distance">
                                        <span>{{ translate('Delivery fee') }} :</span>
                                        <input type="hidden" name="delivery_fee" id="delivery_fee" value="{{ $old ? $old['delivery_fee'] : '' }}">
                                        <strong>{{ $old ? $old['delivery_fee'] : 0 }} {{ \App\CentralLogics\Helpers::currency_symbol() }}</strong>
                                    </div>
                                </div>
                                <input id="pac-input" class="controls map-search__option rounded initial-8"
                                    title="{{ translate('messages.search_your_location_here') }}" type="text"
                                    placeholder="{{ translate('messages.search_here') }}" />
                                <div class="mb-2 h-200px" id="map"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="btn--container justify-content-end mt-20">
                            <button class="btn btn-sm btn--primary w-100 delivery-Address-Store" type="button">
                                {{  translate('Update_Delivery address') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @else
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <div class="text-center">
                            <h2>
                                {{translate('messages.please_select_a_store_first')}}
                            </h2>
                            <button data-dismiss="modal" class="btn btn-primary">{{translate('messages.Ok')}}</button>
                        </div>
                    </div>
                </div>
            </div>

            @endif
        </div>
    </div>
</div>

@push('script_2')
    <script>
        $(document).ready(function(){
            $('.coupon-slider').owlCarousel({
                margin: 15,
                loop: false,
                autoWidth: true,
                items: 3,
            })

        })
    </script>
@endpush
