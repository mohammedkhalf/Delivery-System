<?php $cartItems = $editing ? $cart : $order->details; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-10px">
    <span class="fs-16 font-semibold text-dark">@if($order->store && $order->store->module && $order->store->module->module_type == 'food'){{ translate('Food List') }}@else{{ translate('Item List') }}@endif</span>
    <div class="w-20px h-20px text-dark rounded-circle d-flex align-items-center justify-content-center bg-list-count fs-12 font-semibold">
        {{ $cartItems->count() }}
    </div>
</div>

<div class="table-responsive pt-0 card mb-20">
    <table class="table table-border table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
        <thead class="border-0 initial-94 bg-light">
            <tr>
                <th class="border-0 text-dark">{{ translate('sl') }}</th>
                <th class="border-0 text-dark">{{ translate('Item details') }}</th>
                <th class="border-0 text-dark text-center">{{ translate('Qty') }}</th>
                <th class="border-0 text-dark text-right">{{ translate('Total') }}</th>
                <th class="border-0 text-dark">{{ translate('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cartItems as $key => $detail)
                @if($detail->status)
                    <?php
                    $itemImage = asset('public/assets/admin/img/100x100/2.png');
                    $itemName  = translate('Item');
                    $isPreexisting = $editing && isset($detail->id);
                    $productMissing = false;
                    if ($editing) {
                        $hasItem = !empty($detail->item_id) && $detail->item;
                        $hasCampaign = !empty($detail->item_campaign_id) && $detail->campaign;
                        if ($hasItem) {
                            $itemImage = $detail->item?->image_full_url ?? $itemImage;
                            $itemName  = $detail->item?->name ?? $itemName;
                        } elseif ($hasCampaign) {
                            $itemImage = $detail->campaign?->image_full_url ?? $itemImage;
                            $itemName  = $detail->campaign?->title ?? $itemName;
                        } else {
                            $itemDetailsJson = $detail->item_details ?? null;
                            $itemDetailsArr  = is_array($itemDetailsJson) ? $itemDetailsJson : ($itemDetailsJson ? json_decode($itemDetailsJson, true) : null);
                            if (is_array($itemDetailsArr)) {
                                $itemName = $itemDetailsArr['name'] ?? $itemDetailsArr['title'] ?? $itemName;
                            }
                            if ($isPreexisting && (!empty($detail->item_id) || !empty($detail->item_campaign_id))) {
                                $productMissing = true;
                            }
                        }
                    } else {
                        $itemData  = is_array($detail->item_details) ? $detail->item_details : json_decode($detail->item_details, true);
                        $itemName  = $itemData['name'] ?? $itemName;
                        $liveItem  = \App\Models\Item::find(data_get($itemData, 'id'));
                        $itemImage = $liveItem?->image_full_url ?? $itemImage;
                    }
                    $unitPrice = $detail->price - ($detail->discount_on_item ?? 0);
                    $lineTotal = $unitPrice * $detail->quantity + ($detail->total_add_on_price ?? 0);
                    $disabledAttr = $productMissing ? 'disabled' : '';
                    $readonlyAttr = $productMissing ? 'readonly' : '';
                    ?>
                    <tr class="custom__tr" data-key="{{ $key }}" data-product-missing="{{ $productMissing ? 1 : 0 }}"
                        data-order-details-id="{{ isset($detail->id) ? $detail->id : '' }}"
                        data-item-id="{{ $detail->item_id }}"
                        data-item-campaign-id="{{ $detail->item_campaign_id }}"
                        data-quantity="{{ $detail->quantity }}"
                        data-unit-price="{{ $unitPrice }}"
                        data-addon-total="{{ $detail->total_add_on_price ?? 0 }}"
                        data-variation='@json(is_string($detail->variation ?? null) ? (json_decode($detail->variation, true) ?: []) : ($detail->variation ?? []))'
                        data-variant='@json(is_string($detail->variant ?? null) ? (json_decode($detail->variant, true) ?: []) : ($detail->variant ?? []))'
                        data-add-ons='@json(is_string($detail->add_ons ?? null) ? (json_decode($detail->add_ons, true) ?: []) : ($detail->add_ons ?? []))'>
                        <td><div class="text-dark">{{ $key + 1 }}</div></td>
                        <td>
                            <div class="list-items-media min-w-176px d-flex align-items-center gap-2 {{ $productMissing ? '' : 'cursor-pointer quick-view-cart-item' }}" @if (!$productMissing) data-key="{{ $key }}" @endif>
                                <img width="44" height="44" src="{{ $itemImage }}" alt="image" class="rounded onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}">
                                <div class="cont d-flex flex-column gap-1">
                                    <p class="fs-12 text-dark mb-0 max-w-187px line--limit-1">{{ $itemName }}</p>
                                    @if ($productMissing)
                                        <span class="badge badge-soft-danger fs-10 align-self-start">{{ translate('messages.item_unavailable') }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="product-quantity w-105px mx-auto">
                                <div class="input-group bg-white rounded border d-flex flex-nowrap justify-content-center align-items-center">
                                    <span class="input-group-btn w-30px">
                                        <button class="btn px-2 btn-number w-30px decrease-quantity-button" type="button" data-type="minus" data-key="{{ $key }}" {{ $disabledAttr }}>
                                            <i class="tio-remove fs-16"></i>
                                        </button>
                                    </span>
                                    <input type="number" class="w-30px p-0 border-0 text-center fs-18 update-Quantity text-dark" name="qty[{{ $key }}]" value="{{ $detail->quantity }}" min="1" {{ $readonlyAttr }}>
                                    <span class="input-group-btn w-30px">
                                        <button class="btn px-2 btn-number increase-quantity-button w-30px" type="button" data-type="plus" data-key="{{ $key }}" {{ $disabledAttr }}>
                                            <i class="tio-add fs-16"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="fs-14 text-right text-dark">
                            <div id="item_total_price_{{ $key }}">
                                {{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn rounded-circle mx-auto p-1 d-flex align-items-center justify-content-center w-25px h-25px btn-sm btn--danger removeFromCart" data-key="{{ $key }}">
                                <i class="tio-delete text-white"></i>
                            </button>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
