@extends('layouts.vendor.app')

@section('title', translate('messages.Order Details'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-edit-offcanvas.css') }}">
@endpush

@section('content')
    <?php
    $campaign_order = isset($order?->details[0]?->item_campaign_id) ? true : false;
    $max_processing_time = explode('-', $order['store']['delivery_time'])[0];
    ?>
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('/public/assets/admin/img/shopping-basket.png') }}" class="w--20"
                                alt="">
                        </span>
                        <span>
                            {{ translate('order_details') }} <span
                                class="badge badge-soft-dark rounded-circle ml-1">{{ $order->details->count() }}</span>
                        </span>
                    </h1>
                </div>

                <div class="col-sm-auto">
                    <a class="btn btn-icon btn-sm btn-soft-secondary rounded-circle mr-1"
                        href="{{ route('vendor.order.details', [$order['id'] - 1]) }}" data-toggle="tooltip"
                        data-placement="top" title="Previous order">
                        <i class="tio-chevron-left"></i>
                    </a>
                    <a class="btn btn-icon btn-sm btn-soft-secondary rounded-circle"
                        href="{{ route('vendor.order.details', [$order['id'] + 1]) }}" data-toggle="tooltip"
                        data-placement="top" title="Next order">
                        <i class="tio-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- End Page Header -->

        <div class="row" id="printableArea">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <!-- Card -->
                <div class="card mb-3 mb-lg-5">
                    <!-- Header -->
                    <div class="card-header border-0 align-items-start flex-wrap">
                        <div class="order-invoice-left d-flex d-sm-flex justify-content-between">
                            <div>
                                <h1 class="page-header-title d-flex flex-wrap align-items-center __gap-5px">
                                    {{ translate('messages.order') }} #{{ $order['id'] }}
                                    @if ($order->edited)
                                        <span class="badge badge-soft-danger ml-sm-3">
                                            {{ translate('messages.edited') }}
                                        </span>
                                    @endif
                                    @if ($order->orderEditLogs && $order->orderEditLogs->count() > 0)
                                        <button type="button" class="btn p-0 fs-12 text-info font-weight-medium outline-0 shadow-none offcanvas-trigger" data-target="#offcanvas__history_log">
                                            ({{ translate('messages.Edit_History_Log') }})
                                        </button>
                                    @endif
                                </h1>
                                <span class="mt-2 d-block">
                                    <i class="tio-date-range"></i>
                                    {{ date('d M Y ' . config('timeformat'), strtotime($order['created_at'])) }}
                                </span>
                                @if ($order->schedule_at && $order->scheduled)
                                    <h6 class="text-capitalize">
                                        {{ translate('messages.scheduled_at') }}
                                        : <label
                                            class="fz--10 badge badge-soft-warning">{{ date('d M Y ' . config('timeformat'), strtotime($order['schedule_at'])) }}</label>
                                    </h6>
                                @endif
                                @if($order['cancellation_reason'])
                                <h6>
                                    <span class="text-danger">{{ translate('messages.order_cancellation_reason') }} :</span>
                                    {{ $order['cancellation_reason'] }}
                                </h6>
                                @endif


                                <!-- New Note -->
                                @if ($order['bring_change_amount'] > 0)
                                <div class="info-notes-bg px-3 color-222324CC py-2 rounded fs-12  gap-2 mt-2">
                                    {{ translate('Please_bring') }} <strong class="text-title"> {{  \App\CentralLogics\Helpers::format_currency($order['bring_change_amount']) }}</strong> {{ translate('in_change_when_making_the_delivery') }}.
                                </div>
                                @endif
                                <!-- New Note End -->
                                @if ($order['unavailable_item_note'])
                                    <h6 class="w-100 badge-soft-warning p-1 rounded mt-2">
                                        <span class="text-dark">
                                            {{ translate('messages.order_unavailable_item_note') }} :
                                        </span>
                                        {{ $order['unavailable_item_note'] }}
                                    </h6>
                                @endif
                                @if ($order['delivery_instruction'])
                                    <h6 class="w-100 badge-soft-warning p-1 rounded mt-2">
                                        <span class="text-dark">
                                            {{ translate('messages.order_delivery_instruction') }} :
                                        </span>
                                        {{ $order['delivery_instruction'] }}
                                    </h6>
                                @endif
                                @if ($order['order_note'])
                                    <h6>
                                        {{ translate('messages.order_note') }} :
                                        {{ $order['order_note'] }}
                                    </h6>
                                @endif
                            </div>
                            <div class="d-sm-none">
                                <a class="btn btn--primary print--btn font-regular"
                                    href={{ route('vendor.order.generate-invoice', [$order['id']]) }}>
                                    <i class="tio-print mr-sm-1"></i> <span>{{ translate('messages.print_invoice') }}</span>
                                </a>
                            </div>
                        </div>


                        <div class="order-invoice-right mt-3 mt-sm-0">
                            <div class="btn--container ml-auto align-items-center justify-content-end">
                                @if ($canEditOrder && in_array($order->order_status, ['pending']) && isset($order->store) && !$campaign_order && $order->prescription_order == 0 && count($order?->payments) == 0 && $order?->ref_bonus_amount == 0 && $order?->flash_admin_discount_amount == 0 && ($order->payment_method == 'cash_on_delivery'))
                                    @if ($editing)
                                        <button type="button" class="btn-outline-base btn btn--primary print--btn font-regular d-none d-sm-block reopen-edit-offcanvas">
                                            <i class="tio-edit"></i> {{ translate('messages.edit') }}
                                        </button>
                                    @else
                                        <button type="button" class="btn-outline-base btn btn--primary print--btn font-regular d-none d-sm-block" data-toggle="modal"
                                            data-target="#edit_order_confirmation-btn">
                                            <i class="tio-edit"></i> {{ translate('messages.edit') }}
                                        </button>
                                    @endif
                                @endif
                                <a class="btn btn--primary print--btn font-regular d-none d-sm-block"
                                    href={{ route('vendor.order.generate-invoice', [$order['id']]) }}>
                                    <i class="tio-print mr-sm-1"></i> <span>{{ translate('messages.print_invoice') }}</span>
                                </a>
                            </div>
                            <div class="text-right mt-3 order-invoice-right-contents text-capitalize">
                                <h6>
                                    {{ translate('messages.payment_status') }} :
                                    @if ($order['payment_status'] == 'paid')
                                        <span class="badge badge-soft-success ml-sm-3">
                                            {{ translate('messages.paid') }}
                                        </span>
                                        @elseif ($order['payment_status'] == 'partially_paid')

                                        @if ($order->payments()->where('payment_status','unpaid')->exists())
                                        <span class="text-danger">{{ translate('messages.partially_paid') }}</span>
                                        @else
                                        <span class="text-success">{{ translate('messages.paid') }}</span>
                                        @endif
                                    @else
                                        <span class="badge badge-soft-danger ml-sm-3">
                                            {{ translate('messages.unpaid') }}
                                        </span>
                                    @endif
                                </h6>
                                @if ($order->store && $order->store->module->module_type == 'food')
                                <h6>
                                    <span>{{ translate('cutlery') }}</span> <span>:</span>
                                    @if ($order['cutlery'] == '1')
                                        <span class="badge badge-soft-success ml-sm-3">
                                            {{ translate('messages.yes') }}
                                        </span>
                                    @else
                                        <span class="badge badge-soft-danger ml-sm-3">
                                            {{ translate('messages.no') }}
                                        </span>
                                    @endif

                                </h6>
                                @endif
                                <h6 class="text-capitalize">
                                    {{ translate('messages.payment_method') }} :
                                    {{ translate(str_replace('_', ' ', $order['payment_method'])) }}
                                </h6>
                                @if ($order['transaction_reference'])
                                    <h6 class="">
                                        {{ translate('messages.reference_code') }} :
                                        <button class="btn btn-outline-primary btn-sm" data-toggle="modal"
                                            data-target=".bd-example-modal-sm">
                                            {{ translate('messages.add') }}
                                        </button>
                                    </h6>
                                @endif
                                <h6 class="text-capitalize">{{ translate('messages.order_type') }}
                                    : <label
                                        class="fz--10 badge m-0 badge-soft-primary">{{ translate(str_replace('_', ' ', $order['order_type'])) }}</label>
                                </h6>
                                <h6>
                                    {{ translate('messages.order_status') }} :
                                    @if ($order['order_status'] == 'pending')
                                        <span class="badge badge-soft-info ml-2 ml-sm-3 text-capitalize">
                                            {{ translate('messages.pending') }}
                                        </span>
                                    @elseif($order['order_status'] == 'confirmed')
                                        <span class="badge badge-soft-info ml-2 ml-sm-3 text-capitalize">
                                            {{ translate('messages.confirmed') }}
                                        </span>
                                    @elseif($order['order_status'] == 'processing')
                                        <span class="badge badge-soft-warning ml-2 ml-sm-3 text-capitalize">
                                            {{ translate('messages.processing') }}
                                        </span>
                                    @elseif($order['order_status'] == 'picked_up')
                                        <span class="badge badge-soft-warning ml-2 ml-sm-3 text-capitalize">
                                            {{ translate('messages.out_for_delivery') }}
                                        </span>
                                    @elseif($order['order_status'] == 'delivered')
                                        <span class="badge badge-soft-success ml-2 ml-sm-3 text-capitalize">
                                            {{ translate('messages.delivered') }}
                                        </span>
                                    @elseif($order['order_status'] == 'failed')
                                        <span class="badge badge-soft-danger ml-2 ml-sm-3 text-capitalize">
                                            {{ translate('messages.payment_failed') }}
                                        </span>
                                    @else
                                        <span class="badge badge-soft-danger ml-2 ml-sm-3 text-capitalize">
                                            {{ str_replace('_', ' ', $order['order_status']) }}
                                        </span>
                                    @endif
                                </h6>

                            </div>
                        </div>
                    </div>

                    @if ($order->order_attachment)
                        @php
                            $order_images = json_decode($order->order_attachment,true) ?? [];
                        @endphp
                        <div class="px-20">
                            <h4 class="fs-14 mb-10px">{{ translate('messages.Prescription') }}</h4>
                            <div class="tabs-slide-wrap tabs-slide-wrap-prescription position-relative">
                                <div class="tabs-inner d-flex align-items-center gap-xxl-20 gap-3">

                                    @foreach ($order_images as $key => $item)
                                                <?php $item = is_array($item)?$item:['img'=>$item,'storage'=>'public']; ?>

                                                <div class="tabs-slide_items">
                                                        <div class="prescription-thumb h-100px aspect-ratio-1 overflow-hidden rounded" data-toggle="modal"
                                                                                    data-target="#prescriptionimagemodal{{ $key }}">
                                                                    <img src="{{\App\CentralLogics\Helpers::get_full_url('order', $item['img'], $item['storage']??'public') }}" alt="img" class="w-100">
                                                        </div>
                                                    </div>
                                                <div class="modal fade" id="prescriptionimagemodal{{ $key }}" tabindex="-1"
                                                    role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title" id="myModalLabel">
                                                                    {{ translate('messages.prescription') }}</h4>
                                                                <button type="button" class="close"
                                                                        data-dismiss="modal"><span
                                                                        aria-hidden="true">&times;</span><span
                                                                        class="sr-only">{{ translate('messages.cancel') }}</span></button>
                                                            </div>
                                                            <div class="modal-body scroll-bar">
                                                                <img  src="{{\App\CentralLogics\Helpers::get_full_url('order', $item['img'], $item['storage']??'public') }}"
                                                                    class="initial--22 w-100">
                                                            </div>
                                                            <?php $storage = $item['storage']??'public'; ?>
                                                            <?php $file = $storage == 's3'?base64_encode('order/' . $item['img']):base64_encode('public/order/' . $item['img']); ?>
                                                            <div class="modal-footer">
                                                                <a class="btn btn-primary"
                                                                href="{{ route('admin.file-manager.download', [$file,$storage]) }}"><i
                                                                        class="tio-download"></i>
                                                                    {{ translate('messages.download') }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach


                                </div>
                                <div class="arrow-area">
                                    <div class="button-prev align-items-center">
                                        <button type="button"
                                            class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                            <i class="tio-chevron-left fs-24"></i>
                                        </button>
                                    </div>
                                    <div class="button-next align-items-center">
                                        <button type="button"
                                            class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                            <i class="tio-chevron-right fs-24"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                     @endif
                    <!-- End Header -->

                    <!-- Body -->
                    <div class="card-body px-0">
                        <?php
                        $total_addon_price = 0;
                        $product_price = 0;
                        $store_discount_amount = 0;
                        $delivery_fee_info = \App\CentralLogics\DeliveryFeeLogic::adjustedFeeForOrder($order);

                        if ($order->prescription_order == 1) {
                            $product_price = $order['order_amount'] - $delivery_fee_info['adjusted'] - $order['total_tax_amount'] - $order['dm_tips'] - $order['additional_charge'] + $order['store_discount_amount'];
                            if($order->tax_status == 'included'){
                                $product_price += $order['total_tax_amount'];
                            }
                        }
                        ?>
                        <div class="table-responsive">
                            <table
                                class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="border-0">{{ translate('messages.#') }}</th>
                                        <th class="border-0">{{ translate('messages.item_details') }}</th>
                                        @if ($order->store->module->module_type == 'food')
                                            <th class="border-0">{{ translate('messages.addons') }}</th>
                                        @endif
                                        <th class="text-right  border-0">{{ translate('messages.price') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->details as $key => $detail)
                                        @if (isset($detail->item_id))
                                            <?php $detail->item = json_decode($detail->item_details, true); ?>
                                            <?php $product = \App\Models\Item::where(['id' => $detail->item['id']])->first(); ?>
                                            <!-- Media -->
                                            <tr>
                                                <td>
                                                    <div>
                                                        {{ $key + 1 }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="media media--sm">
                                                        <a class="avatar avatar-xl mr-3"
                                                            href="{{ route('vendor.item.view', $detail->item['id']) }}">
                                                            <img class="img-fluid rounded onerror-image"
                                                            src="{{ $product->image_full_url  ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                                alt="Image Description">
                                                        </a>
                                                        <div class="media-body">
                                                            <div>
                                                                <strong
                                                                    class="line--limit-1">{{ Str::limit($detail->item['name'], 25, '...') }}</strong>
                                                                <?php $unitPrice = $detail['price']; ?>
                                                                <h6>
                                                                    {{ $detail['quantity'] }} x
                                                                    {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                                </h6>
                                                                @if ($order->store && $order->store->module->module_type == 'food')
                                                                    @if (isset($detail['variation']) ? json_decode($detail['variation'], true) : [])
                                                                        @foreach (json_decode($detail['variation'], true) as $variation)
                                                                            @if (isset($variation['name']) && isset($variation['values']))
                                                                                <span class="d-block text-capitalize">
                                                                                    <strong>
                                                                                        {{ $variation['name'] }} -
                                                                                    </strong>
                                                                                </span>
                                                                                @foreach ($variation['values'] as $value)
                                                                                    <span class="d-block text-capitalize">
                                                                                        &nbsp; &nbsp;
                                                                                        {{ $value['label'] }} :
                                                                                        <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                                                    </span>
                                                                                @endforeach
                                                                            @else
                                                                                @if (isset(json_decode($detail['variation'], true)[0]))
                                                                                    <strong><u>
                                                                                            {{ translate('messages.Variation') }}
                                                                                            : </u></strong>
                                                                                    @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                                                        <div
                                                                                            class="font-size-sm text-body">
                                                                                            <span>{{ $key1 }}
                                                                                                : </span>
                                                                                            <span
                                                                                                class="font-weight-bold">{{ $variation }}</span>
                                                                                        </div>
                                                                                    @endforeach
                                                                                @endif
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                @else
                                                                    @if (count(json_decode($detail['variation'], true)) > 0)
                                                                        <strong><u>{{ translate('messages.variation') }}
                                                                                :
                                                                            </u></strong>
                                                                    <?php
                                                                        $detailsVariation = isset(json_decode($detail['variation'], true)[0]) ? json_decode($detail['variation'], true)[0] : json_decode($detail['variation'], true);
                                                                    ?>
                                                                        @foreach ($detailsVariation as $key1 => $variation)
                                                                            @if ($key1 != 'stock')
                                                                                <div class="font-size-sm text-body">
                                                                                        <span>{{ $key1 }} :
                                                                                        </span>
                                                                                    <span class="font-weight-bold">
                                                                                        {{ Str::limit(implode(', ', (array) $variation), 15, '...') }}
                                                                                    </span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                @if ($order->store->module->module_type == 'food')
                                                    <td>
                                                        <div>
                                                            @foreach (json_decode($detail['add_ons'], true) as $key2 => $addon)
                                                                @if ($key2 == 0)
                                                                    <strong><u>{{ translate('messages.addons') }} :
                                                                        </u></strong>
                                                                @endif
                                                                <div class="font-size-sm text-body">
                                                                    <span>{{ Str::limit($addon['name'], 25, '...') }} :
                                                                    </span>
                                                                    <span class="font-weight-bold">
                                                                        {{ $addon['quantity'] }} x
                                                                        {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                                    </span>
                                                                </div>
                                                                <?php $total_addon_price += $addon['price'] * $addon['quantity']; ?>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                @endif
                                                <td>
                                                    <div class="text-right">
                                                        <?php
                                                            $amount = $detail['price'] * $detail['quantity'];
                                                            $lineTotal = $unitPrice * $detail['quantity'];
                                                        ?>
                                                        <h5>{{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}</h5>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $product_price += $amount; ?>
                                            <?php $store_discount_amount += $detail['discount_on_item'] * $detail['quantity']; ?>
                                            <!-- End Media -->
                                        @elseif(isset($detail->item_campaign_id))
                                            <?php $detail->campaign = json_decode($detail->item_details, true); ?>
                                            <?php $campaign = \App\Models\ItemCampaign::where(['id' => $detail->campaign['id']])->first(); ?>
                                            <!-- Media -->
                                            <tr>
                                                <td>
                                                    <div>
                                                        {{ $key + 1 }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="media media--sm">
                                                        <div class="avatar avatar-xl mr-3">
                                                            <img class="img-fluid onerror-image"
                                                            src="{{$campaign?->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"

                                                                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                                alt="Image Description">
                                                        </div>
                                                        <div class="media-body">
                                                            <div>
                                                                <strong
                                                                    class="line--limit-1">{{ Str::limit($detail->campaign['name'], 25, '...') }}</strong>

                                                                <?php $unitPrice = $detail['price']; ?>
                                                                <h6>
                                                                    {{ $detail['quantity'] }} x
                                                                    {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                                </h6>

                                                                @if (count(json_decode($detail['variation'], true)) > 0)
                                                                    <strong><u>{{ translate('messages.variation') }} :
                                                                        </u></strong>
                                                                    @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                                        @if ($key1 != 'stock')
                                                                            <div class="font-size-sm text-body">
                                                                                <span>{{ $key1 }} : </span>
                                                                                <span
                                                                                    class="font-weight-bold">{{ Str::limit($variation, 25, '...') }}</span>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                @endif

                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                @if ($order->store->module->module_type == 'food')
                                                    <td>
                                                        @foreach (json_decode($detail['add_ons'], true) as $key2 => $addon)
                                                            @if ($key2 == 0)
                                                                <strong><u>{{ translate('messages.addons') }} :
                                                                    </u></strong>
                                                            @endif
                                                            <div class="font-size-sm text-body">
                                                                <span>{{ Str::limit($addon['name'], 20, '...') }} : </span>
                                                                <span class="font-weight-bold">
                                                                    {{ $addon['quantity'] }} x
                                                                    {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                                </span>
                                                            </div>
                                                            <?php $total_addon_price += $addon['price'] * $addon['quantity']; ?>
                                                        @endforeach
                                                    </td>
                                                @endif
                                                <td>
                                                    <div class="text-right">
                                                        <?php
                                                            $amount = $detail['price'] * $detail['quantity'];
                                                            $lineTotal = $unitPrice * $detail['quantity'];
                                                        ?>
                                                        <h5>{{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}</h5>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $product_price += $amount; ?>
                                            <?php $store_discount_amount += $detail['discount_on_item'] * $detail['quantity']; ?>
                                            <!-- End Media -->
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mx-3">
                            <hr>
                        </div>
                        <?php
                        $total_tax_amount = $order['total_tax_amount'];
                        if($order->tax_status == 'included'){
                                $total_tax_amount=0;
                            }
                        $tax_included = \App\Models\BusinessSetting::where(['key'=>'tax_included'])->first() ?  \App\Models\BusinessSetting::where(['key'=>'tax_included'])->first()->value : 0;

                        $store_discount_amount = $order['store_discount_amount'];

                        ?>
                        <div class="row justify-content-md-end mb-3 mx-0 mt-4">
                            <div class="col-md-9 col-lg-8">
                                <dl class="row text-right">
                                    <dt class="col-6">{{ translate('messages.items_price') }}:</dt>
                                    <dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($product_price) }}
                                    </dd>
                                    @if ($order->store->module->module_type == 'food')
                                        <dt class="col-6">{{ translate('messages.addon_cost') }}:</dt>

                                        <dd class="col-6">
                                            {{ \App\CentralLogics\Helpers::format_currency($total_addon_price) }}
                                            <hr>
                                        </dd>
                                    @endif

                                    <dt class="col-6">{{ translate('messages.subtotal') }}
                                        @if ($order->tax_status == 'included' ||  $tax_included ==  1)
                                        ({{ translate('messages.TAX_Included') }})
                                        @endif
                                        :</dt>

                                    <dd class="col-6">
                                        @if ($order->prescription_order == 1 && in_array($order['order_status'],['pending','confirmed','processing','accepted']))
                                            <button class="btn btn-sm" type="button" data-toggle="modal"
                                                data-target="#edit-order-amount"><i class="tio-edit"></i></button>
                                        @endif
                                        {{ \App\CentralLogics\Helpers::format_currency($product_price + $total_addon_price) }}
                                    </dd>
                                    <dt class="col-6">{{ translate('messages.discount') }}:</dt>
                                    <dd class="col-6">
                                        @if ($order->prescription_order == 1 && in_array($order['order_status'],['pending','confirmed','processing','accepted']))
                                            <button class="btn btn-sm" type="button" data-toggle="modal"
                                                data-target="#edit-discount-amount"><i class="tio-edit"></i></button>
                                        @endif
                                        - {{ \App\CentralLogics\Helpers::format_currency($store_discount_amount + $order['flash_admin_discount_amount'] + $order['flash_store_discount_amount']) }}
                                    </dd>



                                    <dt class="col-6">{{ translate('messages.coupon_discount') }}
                                        @if ($order->orderProDiscount && $order->orderProDiscount->benefit_type === 'coupon')
                                            <i class="tio-info-outined" data-toggle="tooltip"
                                               title="{{ translate('Pro Customer coupon applied.') }}"></i>
                                        @endif
                                        :</dt>
                                    <dd class="col-6">
                                        - {{ \App\CentralLogics\Helpers::format_currency($order['coupon_discount_amount']) }}</dd>

                                    @if ($order->extra_discount_amount > 0)
                                          <dt class="col-6">{{ translate('messages.extra_discount') }}:</dt>
                                          <dd class="col-6">
                                              - {{ \App\CentralLogics\Helpers::format_currency($order->extra_discount_amount) }}</dd>
                                        @endif

                                    @if ($order['ref_bonus_amount'] > 0)
                                    <dt class="col-6">{{ translate('messages.Referral_Discount') }}:</dt>
                                    <dd class="col-6">
                                        - {{ \App\CentralLogics\Helpers::format_currency($order['ref_bonus_amount']) }}</dd>

                                    @endif
                                    @if (($order->orderProDiscount?->amount_saved ?? 0) > 0)
                                    <dt class="col-6">{{ translate('messages.Pro_Discount') }}:</dt>
                                    <dd class="col-6">
                                        - {{ \App\CentralLogics\Helpers::format_currency($order->orderProDiscount->amount_saved) }}</dd>
                                    @endif

                                    @if ($order->tax_status == 'excluded' || $order->tax_status == null  )
                                    <dt class="col-sm-6">{{ translate('messages.vat/tax') }}:</dt>
                                    <dd class="col-sm-6">
                                        +
                                        {{ \App\CentralLogics\Helpers::format_currency($total_tax_amount) }}
                                    </dd>
                                    @endif
                                    <dt class="col-6">{{ translate('messages.delivery_man_tips') }}</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($order->dm_tips) }}</dd>
                                    <dt class="col-6">{{ translate('messages.delivery_fee') }}
                                            @if (
                                                 $order->orderProDiscount
                                                 && $order->orderProDiscount->benefit_type === 'delivery_fee'
                                                 && ($order->orderProDiscount->delivery_fee_reduction_amount ?? 0) > 0
                                             )
                                                 @if ($order->orderProDiscount->delivery_offer_type === 'full_free')
                                                     <i class="tio-info-outined" data-toggle="tooltip"
                                                        title="{{ translate('messages.Pro_Customer_free_delivery_applied') }}"></i>
                                                 @else
                                                     <i class="tio-info-outined" data-toggle="tooltip"
                                                        title="{{ translate('messages.Pro_Customer_partial_delivery_discount_applied') }} ({{ (float) ($order->orderProDiscount->delivery_charge_discount_percentage ?? 0) }}%)"></i>
                                                 @endif
                                             @elseif ($order->free_delivery_by == 'admin')
                                                 <i class="tio-info-outined" data-toggle="tooltip" title="{{ translate('Delivery fee is applicable and will be covered by the admin.') }}"></i>
                                             @elseif ($order->free_delivery_by == 'vendor')
                                                 <i class="tio-info-outined" data-toggle="tooltip" title="{{ translate('Delivery fee is applicable and will be covered by the Vendor.') }}"></i>
                                             @endif
                                        :</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency(\App\CentralLogics\DeliveryFeeLogic::proDeliveryBreakdown($order)['original_fee']) }}
                                        <hr>
                                    </dd>
                                    @include('partials.pro-delivery-discount-row', ['order' => $order, 'layout' => 'dl', 'ddClass' => ''])
                                    @include('partials.delivery-type-row', ['order' => $order, 'layout' => 'dl', 'ddClass' => ''])
                                    <dt class="col-6">{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('messages.additional_charge') }}:</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($order['additional_charge']) }}
                                    </dd>
                                    @if ($order['extra_packaging_amount'] > 0)
                                    <dt class="col-6">{{ translate('messages.Extra_Packaging_Amount') }}:</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($order['extra_packaging_amount']) }}</dd>
                                    @endif
                                    @if ($order['partially_paid_amount'] > 0)

                                    <dt class="col-6">{{ translate('messages.partially_paid_amount') }}:</dt>
                                    <dd class="col-6">
                                            {{ \App\CentralLogics\Helpers::format_currency($order['partially_paid_amount']) }}
                                    </dd>
                                    <dt class="col-6">{{ translate('messages.due_amount') }}:</dt>
                                    @if ($order['payment_method'] == 'partial_payment')

                                    <dd class="col-6">
                                            {{ \App\CentralLogics\Helpers::format_currency($order['partially_paid_amount']) }}
                                    </dd>
                                    @else
                                    <dd class="col-6">
                                            {{ \App\CentralLogics\Helpers::format_currency(0) }}
                                    </dd>
                                    @endif
                                    @endif

                                    <dt class="col-6">{{ translate('messages.total') }}:</dt>
                                    <dd class="col-6">
                                        {{ \App\CentralLogics\Helpers::format_currency($product_price + $delivery_fee_info['adjusted'] + $total_tax_amount + $total_addon_price + $order['additional_charge'] - $order['coupon_discount_amount'] - $store_discount_amount - $order['flash_admin_discount_amount']  - $order['ref_bonus_amount'] - ($order->orderProDiscount?->amount_saved ?? 0) + $order['extra_packaging_amount'] - $order['flash_store_discount_amount'] + $order->dm_tips - $order->extra_discount_amount) }}
                                    </dd>
                                    @if ($order?->payments)
                                        @foreach ($order?->payments as $payment)
                                            @if ($payment->payment_status == 'paid')
                                                @if ( $payment->payment_method == 'cash_on_delivery')

                                                <dt class="col-sm-6">{{ translate('messages.Paid_with_Cash') }} ({{  translate('COD')}}) :</dt>
                                                @else

                                                <dt class="col-sm-6">{{ translate('messages.Paid_by') }} {{  translate($payment->payment_method)}} :</dt>
                                                @endif
                                            @else

                                            <dt class="col-sm-6">{{ translate('Due_Amount') }} ({{  $payment->payment_method == 'cash_on_delivery' ?  translate('messages.COD') : translate($payment->payment_method) }}) :</dt>
                                            @endif
                                        <dd class="col-sm-6">
                                            {{ \App\CentralLogics\Helpers::format_currency($payment->amount) }}
                                        </dd>
                                        @endforeach
                                    @endif
                                </dl>
                                @if ($order->edited)
                                    <div class="text-right">
                                        <div class="d-inline-flex p-2 px-3 rounded gap-2 bg-opacity-warning-10 mt-3">
                                            <i class="tio-info text-warning"></i>
                                            <p class="fz-12px mb-0">
                                                {{translate('Total bill has been updated after the edits.')}}
                                            </p>
                                        </div>
                                    </div>
                                @endif
                                <!-- End Row -->
                            </div>
                        </div>
                        <!-- End Row -->
                    </div>
                    <!-- End Body -->
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4">
                <!-- Card -->
                @if ($order->order_status != 'refund_requested' &&
                    $order->order_status != 'refunded' &&
                    $order->order_status != 'delivered')
                    <div class="card mb-2">
                        <!-- Header -->
                        <div class="card-header border-0 pb-0 px-0 mx-4">
                            <h5 class="card-header-title text-capitalize">
                                <span>{{ translate('messages.order_setup') }}</span>
                            </h5>
                        </div>
                        <!-- End Header -->

                        <!-- Body -->

                        <div class="card-body pt-3">
                            <!-- Order Status Flow Starts -->
                            <?php $order_delivery_verification = (bool) \App\Models\BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value; ?>
                            <div class="bg--secondary rounded p-10px">
                                <div class="mb-0">
                                    <div class="row g-1">
                                        <div class="{{ config('canceled_by_store') ? 'col-6' : 'col-12' }}">
                                            <a class="btn btn--primary w-100 fz--13 px-2 {{ $order['order_status'] == 'pending' ? '' : 'd-none' }} route-alert"
                                               data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'confirmed']) }}"
                                               data-message="{{ translate('messages.confirm_this_order_?') }}"
                                                href="javascript:">{{ translate('messages.confirm_this_order') }}</a>
                                        </div>
                                        @if (config('canceled_by_store'))
                                            <div class="col-6">
                                                <a class="btn btn--danger w-100 fz--13 px-2 cancelled-status {{ $order['order_status'] == 'pending' ? '' : 'd-none' }}"
                                                   >{{ translate('Cancel Order') }}</a>
                                            </div>
                                        @endif
                                    </div>
                                        @if ($order->store && $order->store->module->module_type == 'food')
                                            <a class="btn btn--primary w-100 order-status-change-alert {{ $order['order_status'] == 'confirmed' || $order['order_status'] == 'accepted' ? '' : 'd-none' }}"

                                               data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'processing']) }}"
                                               data-message="{{ translate('Change status to cooking ?') }}"
                                               data-verification="false"
                                               data-processing-time="{{ $max_processing_time }}"
                                               href="javascript:">{{ translate('messages.proceed_for_processing') }}</a>
                                        @else
                                        <a class="btn btn--primary w-100 route-alert  {{ $order['order_status'] == 'confirmed' || $order['order_status'] == 'accepted' ? '' : 'd-none' }}"
                                           data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'processing']) }}"
                                           data-message="{{ translate('messages.proceed_for_processing') }}"
                                        href="javascript:">{{ translate('messages.proceed_for_processing') }}</a>
                                        @endif
                                    <a class="btn btn--primary w-100 route-alert {{ $order['order_status'] == 'processing' ? '' : 'd-none' }}"
                                       data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'handover']) }}"
                                       data-message="{{ translate('messages.make_ready_for_handover') }}"
                                        href="javascript:">{{ translate('messages.make_ready_for_handover') }}</a>
                                     @if($order['order_status'] == 'handover'|| ($order['order_status'] == 'picked_up' && $order->store->sub_self_delivery == 1))
                                        <a class="btn  w-100
                                        {{ ($order['order_type'] == 'take_away' || $order->store->sub_self_delivery == 1 || $order->is_pos)  ?  'btn--primary order-status-change-alert'  :  'btn--secondary  self-delivery-warning' }} "
                                           data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'delivered']) }}"
                                           data-message="{{ translate('messages.Change status to delivered (payment status will be paid if not)?') }}"
                                           data-verification="{{ $order_delivery_verification ? 'true' : 'false' }}"
                                            href="javascript:">{{ translate('messages.make_delivered') }}</a>
                                     @endif

                                </div>
                            </div>
                        </div>

                        <!-- End Body -->
                    </div>
                @endif
                <!-- End Card -->
                @if ($order->order_status == 'canceled')
                <ul class="delivery--information-single mt-3">
                    <li>
                        <span class=" badge badge-soft-danger "> {{ translate('messages.Cancel_Reason') }} :</span>
                        <span class="info">  {{ $order->cancellation_reason }} </span>
                    </li>

                    <li>
                        <span class="name">{{ translate('Cancel_Note') }} </span>
                        <span class="info">  {{ $order->cancellation_note ?? translate('messages.N/A')}} </span>
                    </li>
                    <li>
                        <span class="name">{{ translate('Canceled_By') }} </span>
                        <span class="info">  {{ translate($order->canceled_by) }} </span>
                    </li>
                    @if ($order->payment_status == 'paid' || $order->payment_status == 'partially_paid' )
                            @if ( $order?->payments)
                                <?php $pay_infos =$order->payments()->where('payment_status','paid')->get(); ?>
                                @foreach ($pay_infos as $pay_info)
                                    <li>
                                        <span class="name">{{ translate('Amount_paid_by') }} {{ translate($pay_info->payment_method) }} </span>
                                        <span class="info">  {{ \App\CentralLogics\Helpers::format_currency($pay_info->amount)  }} </span>
                                    </li>
                                @endforeach
                            @else
                            <li>
                                <span class="name">{{ translate('Amount_paid_by') }} {{ translate($order->payment_method) }} </span>
                                <span class="info ">  {{ \App\CentralLogics\Helpers::format_currency($order->order_amount)  }} </span>
                            </li>
                            @endif
                    @endif

                    @if ($order->payment_status == 'paid' || $order->payment_status == 'partially_paid')
                        @if ( $order?->payments)
                            <?php $amount =$order->payments()->where('payment_status','paid')->sum('amount'); ?>
                                <li>
                                    <span class="name">{{ translate('Amount_Returned_To_Wallet') }} </span>
                                    <span class="info">  {{ \App\CentralLogics\Helpers::format_currency($amount)  }} </span>
                                </li>
                        @else
                        <li>
                            <span class="name">{{ translate('Amount_Returned_To_Wallet') }} </span>
                            <span class="info">  {{ \App\CentralLogics\Helpers::format_currency($order->order_amount)  }} </span>
                        </li>
                        @endif
                    @endif
                </ul>
                <hr class="w-100">
            @endif
                @if ($order['order_type'] != 'take_away' && $order->store->sub_self_delivery == 1)
                    <!-- Card -->
                    <div class="card mb-2">
                        <!-- Header -->
                        <div class="card-header pb-0 border-0">
                            <h4 class="card-header-title">
                                <span class="card-header-icon"><i class="tio-user"></i></span>
                                <span>{{ translate('messages.Delivery Man') }}</span>
                            </h4>
                        </div>
                        <!-- End Header -->

                        <!-- Body -->
                        <div class="card-body pt-3">
                            @if ($order->delivery_man)
                            <div class="bg--secondary rounded p-10px">
                                <div class="media gap-10px customer--information-single" href="javascript:">
                                    <div class="avatar avatar-circle">
                                        <img class="avatar-img onerror-image"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                             src="{{ $order->delivery_man->image_full_url }}"
                                            alt="Image Description">
                                    </div>
                                    <div class="media-body p-0">
                                        <div class="">
                                            <span
                                                class="text-body fw-semibold text-title fs-12 d-block text-hover-primary mb-1">{{ $order->delivery_man['f_name'] . ' ' . $order->delivery_man['l_name'] }}</span>

                                            <span class="text--title font-weight-normal text-clr d-flex align-items-center mb-1">
                                                <i class="tio-shopping-basket-outlined mr-2"></i>
                                                {{ $order->delivery_man->orders_count }}
                                                {{ translate('messages.orders_delivered') }}
                                            </span>

                                            <span class="text--title font-weight-normal text-clr d-flex align-items-center mb-1">
                                                <i class="tio-call-talking-quiet mr-2"></i>
                                                {{ $order->delivery_man['phone'] }}
                                            </span>

                                            <span class="text--title font-weight-normal text-clr d-flex align-items-center">
                                                <i class="tio-email-outlined mr-2"></i>
                                                {{ $order->delivery_man['email'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                @if ($order['order_type'] != 'take_away')
                                    <div class="mt-10px"></div>
                                    <?php $address = $order->dm_last_location; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5>{{ translate('messages.last_location') }}</h5>
                                    </div>
                                    @if (isset($address))
                                        <span class="d-block">
                                            <a target="_blank" class="d-block text--info"
                                                href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $address['latitude'] }}+{{ $address['longitude'] }}">
                                                <i class="tio-map text-title"></i> {{ $address['location'] }}<br>
                                            </a>
                                        </span>
                                    @else
                                        <span class="d-block text-lowercase qcont">
                                            {{ translate('messages.location_not_found') }}
                                        </span>
                                    @endif
                                @endif
                            @else
                                <span class="badge badge-soft-danger py-2 d-block qcont">
                                    {{ translate('messages.deliveryman_not_found') }}
                                </span>
                            @endif
                        </div>
                        <!-- End Body -->
                    </div>
                @endif
                <!-- End Card -->
                <?php $data = isset($order->order_proof) ? json_decode($order->order_proof, true) : []; ?>
                @if ( in_array($order->order_status, [ 'handover', 'delivered', 'picked_up']) || ($data != null && count($data) > 0) )
                    <!-- order proof -->
                    <div class="card mb-2 mt-2">
                        <div class="card-header border-0 text-center pb-0">
                            <h4 class="m-0">{{ translate('messages.delivery_proof') }} </h4>
                            @if ($order['store']['sub_self_delivery'])

                            <button class="btn btn-outline-primary btn-sm" data-toggle="modal"
                                                data-target=".order-proof-modal">
                                                {{ translate('messages.add') }}
                                            </button>
                            @endif
                        </div>
                        <?php $data = isset($order->order_proof) ? json_decode($order->order_proof, true) : 0; ?>
                        <div class="card-body pt-2">
                            @if ($data)
                                <div class="bg--secondary rounded p-10px">
                                    <label class="input-label"
                                    for="order_proof">{{ translate('messages.image') }} : </label>
                                    <div class="row g-3">
                                        @foreach ($data as $key => $img)
                                        <?php $img = is_array($img)?$img:['img'=>$img,'storage'=>'public']; ?>
                                            <div class="col-3">
                                                <img class="img__aspect-1 rounded border w-100 onerror-image" data-toggle="modal"
                                                    data-target="#imagemodal{{ $key }}"
                                                    data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                    src="{{\App\CentralLogics\Helpers::get_full_url('order',$img['img'],$img['storage']) }}"
                                                    alt="image">
                                            </div>
                                            <div class="modal fade" id="imagemodal{{ $key }}" tabindex="-1"
                                                role="dialog" aria-labelledby="order_proof_{{ $key }}"
                                                aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title"
                                                                id="order_proof_{{ $key }}">
                                                                {{ translate('order_proof_image') }}</h4>
                                                            <button type="button" class="close"
                                                                data-dismiss="modal"><span
                                                                    aria-hidden="true">&times;</span><span
                                                                    class="sr-only">{{ translate('messages.cancel') }}</span></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <img src="{{\App\CentralLogics\Helpers::get_full_url('order',$img['img'],$img['storage']) }}"
                                                                class="initial--22 w-100" alt="img">
                                                        </div>
                                                        <?php $storage = $img['storage']??'public'; ?>
                                                        <?php $file = $storage == 's3'?base64_encode('order/' . $img['img']):base64_encode('public/order/' . $img['img']); ?>
                                                        <div class="modal-footer">
                                                            <a class="btn btn-primary"
                                                                href="{{ route('admin.file-manager.download', [$file,$storage]) }}"><i
                                                                    class="tio-download"></i>
                                                                {{ translate('messages.download') }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                        </div>
                    </div>
                @endif
                <!-- Card -->
                <div class="card">
                    <!-- Header -->
                    <div class="card-header border-0 pb-0">
                        <h4 class="card-header-title">
                            <span class="card-header-icon"><i class="tio-user"></i></span>
                            <span>{{ translate('messages.customer') }}</span>
                        </h4>
                    </div>
                    <!-- End Header -->

                    <!-- Body -->
                    @if ($order->customer)
                        <div class="card-body pt-3">

                            <div class="bg--secondary rounded p-10px">
                                <div class="media gap-10px customer--information-single" href="javascript:">
                                    @include('partials._user-avatar', [
                                        'imageUrl'  => $order->customer->image_full_url,
                                        'proStatus' => $order->customer->pro_status ?? false,
                                        'size'      => 42,
                                    ])
                                    <div class="media-body p-0">
                                        <span
                                            class="text-title font-semibold d-block text-hover-primary mb-1">{{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}</span>

                                        <span class="text--title font-weight-normal text-clr fs-12 d-flex align-items-center">
                                            <i class="tio-shopping-basket-outlined mr-2"></i>
                                            {{ $order->customer->orders_count }}
                                            {{ translate('messages.orders') }}
                                        </span>

                                        <span class="text--title font-weight-normal text-clr fs-12 d-flex align-items-center">
                                            <i class="tio-call-talking-quiet mr-2"></i>
                                            {{ $order->customer['phone'] }}
                                        </span>

                                        <span class="text--title font-weight-normal text-clr fs-12 d-flex align-items-center">
                                            <i class="tio-email-outlined mr-2"></i>
                                            {{ $order->customer['email'] }}
                                        </span>

                                    </div>
                                </div>
                            </div>
                            <div class="mt-10px"></div>




                            @if ($order->delivery_address)
                                <div class="bg--secondary rounded p-10px">
                                    <?php $address = json_decode($order->delivery_address, true); ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5>{{ translate('messages.delivery_info') }}</h5>
                                    </div>
                                    @if (isset($address))
                                        <span class="delivery--information-single d-block">
                                            <div class="d-flex mb-1">
                                                <span class="name">{{ translate('messages.name') }}:</span>
                                                <span class="info">{{ $address['contact_person_name'] }}</span>
                                            </div>

                                            <div class="d-flex mb-1">
                                                <span class="name">{{ translate('messages.contact') }}:</span>
                                                <a class="info deco-none"
                                                    href="tel:{{ $address['contact_person_number'] }}">
                                                    {{ $address['contact_person_number'] }}</a>
                                            </div>
                                            <div class="d-flex align-items-center gap-xxl-20 gap-2 flex-wrap">

                                                  @if(data_get($address,'house') != '')
                                                <div class="d-flex">
                                                    <span class="name">{{ translate('House') }}:</span>
                                                    <span
                                                        class="info">{{ isset($address['house']) ? $address['house'] : '' }}</span>
                                                </div>
                                                  @endif
                                                   @if(data_get($address,'floor') != '')
                                                <div class="cus-border-in"></div>
                                                <div class="d-flex">
                                                    <span class="name">{{ translate('Floor') }}:</span>
                                                    <span
                                                    class="info">{{ isset($address['floor']) ? $address['floor'] : '' }}</span>
                                                </div>
                                                   @endif
                                                    @if(data_get($address,'road') != '')
                                                <div class="cus-border-in"></div>
                                                <div class="d-flex">
                                                    <span class="name">{{ translate('Road') }}:</span>
                                                    <span
                                                        class="info">{{ isset($address['road']) ? $address['road'] : '' }}</span>
                                                </div>
                                                    @endif
                                            </div>
                                            @if ($order['order_type'] != 'take_away' && isset($address['address']))
                                                @if (isset($address['latitude']) && isset($address['longitude']))
                                                    <a target="_blank" class="text--info d-flex gap-1 align-items-center mt-2"
                                                        href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $address['latitude'] }}+{{ $address['longitude'] }}">
                                                        <i class="tio-map text-title"></i>{{ $address['address'] }}<br>
                                                    </a>
                                                @else
                                                    <i class="tio-map"></i>{{ $address['address'] }}<br>
                                                @endif
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                    @elseif($order->is_guest)
                        <div class="card-body">
                            <span class="badge badge-soft-success py-2 mb-2 d-block qcont">
                                {{ translate('Guest_user') }}
                            </span>
                            @if ($order->delivery_address)
                            <?php $address = json_decode($order->delivery_address, true); ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <h5>{{ translate('messages.delivery_info') }}</h5>
                            </div>
                            @if (isset($address))
                                <span class="delivery--information-single d-block">
                                    <div class="d-flex">
                                        <span class="name">{{ translate('messages.name') }}:</span>
                                        <span class="info">{{ $address['contact_person_name'] }}</span>
                                    </div>
                                    <div class="d-flex">
                                        <span class="name">{{ translate('messages.contact') }}:</span>
                                        <a class="info deco-none"
                                            href="tel:{{ $address['contact_person_number'] }}">
                                            {{ $address['contact_person_number'] }}</a>
                                    </div>
                                    @if(data_get($address,'floor') != '')
                                    <div class="d-flex">
                                        <span class="name">{{ translate('Floor') }}:</span>
                                        <span
                                            class="info">{{ isset($address['floor']) ? $address['floor'] : '' }}</span>
                                    </div>

                                    @endif

                                   @if(data_get($address,'house') != '')
                                   <div class="d-flex mb-2">
                                       <span class="name">{{ translate('House') }}:</span>
                                       <span
                                           class="info">{{ isset($address['house']) ? $address['house'] : '' }}</span>
                                   </div>

                                    @endif

                                    @if(data_get($address,'road') != '')
                                        <div class="d-flex">
                                            <span class="name">{{ translate('Road') }}:</span>
                                            <span
                                                class="info">{{ isset($address['road']) ? $address['road'] : '' }}</span>
                                        </div>
                                    @endif

                                    @if ($order['order_type'] != 'take_away' && isset($address['address']))
                                    <hr>
                                        @if (isset($address['latitude']) && isset($address['longitude']))
                                            <a target="_blank"
                                                href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $address['latitude'] }}+{{ $address['longitude'] }}">
                                                <i class="tio-map"></i>{{ $address['address'] }}<br>
                                            </a>
                                        @else
                                            <i class="tio-map"></i>{{ $address['address'] }}<br>
                                        @endif
                                    @endif
                                </span>
                            @endif
                        @endif

                        </div>
                    @else
                        <div class="card-body">
                            <span class="badge badge-soft-danger py-2 d-block qcont">
                                {{ translate('Customer Not found!') }}
                            </span>
                        </div>
                    @endif
                    <!-- End Body -->
                </div>
                <!-- End Card -->
            </div>
        </div>
        <!-- End Row -->
    </div>



        <!-- Modal -->
        <div class="modal fade order-proof-modal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h4" id="mySmallModalLabel">{{ translate('messages.add_delivery_proof') }}</h5>
                    <button type="button" class="btn btn-xs btn-icon btn-ghost-secondary" data-dismiss="modal"
                        aria-label="Close">
                        <i class="tio-clear tio-lg"></i>
                    </button>
                </div>

                <form action="{{ route('vendor.order.add-order-proof', [$order['id']]) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <!-- Input Group -->
                        <div class="flex-grow-1 mx-auto">

                            <div class="d-flex flex-wrap __gap-12px __new-coba" id="coba">
                                <?php $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : 0; ?>
                                @if ($proof)

                                @foreach ($proof as $key => $photo)
                                <?php $photo = is_array($photo)?$photo:['img'=>$photo,'storage'=>'public']; ?>

                                            <div class="spartan_item_wrapper min-w-176px max-w-176px">
                                                <img class="img--square"
                                                    src="{{\App\CentralLogics\Helpers::get_full_url('order',$photo['img'],$photo['storage']) }}"
                                                    alt="order image">

                                                <div class="pen spartan_remove_row"><i class="tio-edit"></i></div>
                                                <a href="{{ route('vendor.order.remove-proof-image', ['id' => $order['id'], 'name' => $photo]) }}"
                                                    class="spartan_remove_row"><i class="tio-add-to-trash"></i></a>
                                            </div>
                                        @endforeach
                                @endif
                            </div>
                        </div>
                        <!-- End Input Group -->
                        <div class="text-right mt-2">
                            <button class="btn btn--primary">{{ translate('messages.submit') }}</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
    <!-- End Modal -->

    <div class="modal fade" id="edit-order-amount" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.update_order_amount') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('vendor.order.update-order-amount') }}" method="POST" class="row">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <div class="form-group col-12">
                            <label for="order_amount">{{ translate('messages.order_amount') }}</label>
                            <input id="order_amount" type="number" class="form-control" name="order_amount" min="0"
                                value="{{ round($order['order_amount'] - $order['total_tax_amount']  - $order['additional_charge'] -  $order['delivery_charge'] + $order['store_discount_amount'] - $order['dm_tips'] ,6) }}" step=".01">
                        </div>

                        <div class="form-group col-sm-12">
                            <button class="btn btn-sm btn-primary"
                                type="submit">{{ translate('messages.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="edit-discount-amount" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.update_discount_amount') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('vendor.order.update-discount-amount') }}" method="POST" class="row">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <div class="form-group col-12">
                            <label for="discount_amount">{{ translate('messages.discount_amount') }}</label>
                            <input type="number" id="discount_amount" class="form-control" name="discount_amount" min="0"
                                value="{{ $order['store_discount_amount'] }}" step=".01">
                        </div>

                        <div class="form-group col-sm-12">
                            <button class="btn btn-sm btn-primary"
                                type="submit">{{ translate('messages.submit') }}</button>
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

    <!-- Order edit offcanvas -->
    <div id="offcanvas__order_edit" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 750px !important;">
        <div>
            <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                <div class="px-3 py-3 d-flex justify-content-between w-100">
                    <div>
                        <h2 class="mb-1">{{ translate('Edit Item') }}</h2>
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <h3 class="page-header-title mb-0 d-flex align-items-center gap-2">
                                <span class="font--max-sm fs-14">{{ translate('Order') }} #{{ $order['id'] }}</span>
                                <?php
                                $statusBadge = match($order->order_status) {
                                    'pending'    => 'badge-soft-info',
                                    'confirmed','accepted' => 'badge-soft-success',
                                    'processing' => 'badge-soft-warning',
                                    'handover','picked_up' => 'badge-soft-primary',
                                    'delivered'  => 'badge-soft-success',
                                    'canceled'   => 'badge-soft-danger',
                                    default      => 'badge-soft-secondary',
                                };
                                ?>
                                <span class="badge {{ $statusBadge }} font-regular m-0">{{ translate(str_replace('_', ' ', $order->order_status)) }}</span>
                            </h3>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-14 font-regular d-block text-dark">{{ translate('Order_Placed') }} :</span>
                                <span class="fs-14 font-semibold d-block text-dark">{{ date('d M Y ' . config('timeformat'), strtotime($order['created_at'])) }}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button"
                        class="btn-close h-32px min-w-32 border rounded-circle d-center bg--secondary location-reload offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;
                    </button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-20">
                <div class="mb-20 position-relative edit-search-form">
                    <div class="form-control position-relative bg-white d-flex align-items-center gap-2">
                        <i class="tio-search"></i>
                        <input id="food_search" type="search" class="h-100 fs-12 bg-transparent w-100 border-0 rounded-0" placeholder="{{ translate('Search by food name') }}" autocomplete="off" data-store-id="{{ $order->store_id }}">
                        <div class="search-wrap-manage w-100 z-index-99" id="search-dropdown" style="display:none;">
                            <div class="search-items-wrap p-sm-3 p-2 rounded bg-white d-flex flex-column gap-2">
                                <div id="food-search-result"></div>
                                <div id="food-search-no-data" class="d-none">
                                    <h6 class="text-center bg-light py-5 px-3 rounded">{{ translate('no_data_found') }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <div id="data-view" class="pb-5 mb-5">
                    @include('admin-views.order.partials._edit_cart_list', ['cart' => $cart, 'editing' => $editing, 'order' => $order])
                </div>

            </div>
            <div class="offcanvas-footer position-absolute bottom-0 start-0 w-100 bg-white p-3 d-flex align-items-center justify-content-end gap-3">
                <button type="button" class="btn min-w-120 btn--reset location-reload reset">{{ translate('Cancel') }}</button>
                <button type="button" class="btn min-w-120 btn--primary submit-edit-order">{{ translate('Update Cart') }}</button>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay_fixed" class="offcanvasOverlay_fixed"></div>


    <!-- History Log -->
    <div id="offcanvas__history_log" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 570px">
        <div>
            <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                <div class="px-3 py-3 d-flex justify-content-between w-100">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h2 class="mb-0 fs-18 font-medium">{{ translate('messages.Edit_History_Log') }}</h2>
                        <h3 class="page-header-title mb-0 d-flex align-items-center gap-2">
                            <span class="font--max-sm fs-14 font-normal fs-14">(# {{ $order['id'] }})</span>
                        </h3>
                    </div>
                    <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;
                    </button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-20">
                <div class="card p-10px">
                    <div class="table-responsive pt-0">
                        <div class="p-1">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
                                <thead class="border-0 initial-94 bg-light">
                                    <tr>
                                        <th class="border-0 text-dark text-nowrap">{{ translate('messages.sl') }}</th>
                                        <th class="border-0 text-dark text-nowrap">{{ translate('messages.date_&_time') }}</th>
                                        <th class="border-0 text-dark text-nowrap">{{ translate('messages.remark') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $logRemarkLabels = [
                                        'edited_item_quantity' => translate('messages.edited_item_quantity'),
                                        'add_new_item'         => translate('messages.added_new_item'),
                                        'delete_item'          => translate('messages.removed_item'),
                                    ];
                                    ?>
                                    @forelse ($order->orderEditLogs as $logKey => $editLog)
                                        <tr>
                                            <td>
                                                <div>{{ $logKey + 1 }}</div>
                                            </td>
                                            <td class="fs-14">
                                                <span class="d-block text-dark">
                                                    {{ $editLog->created_at?->format('d M Y') }}
                                                </span>
                                                <span class="text-muted">
                                                    {{ $editLog->created_at?->format(config('timeformat') == 'h:i A' ? 'h:i A' : config('timeformat', 'h:i a')) }}
                                                </span>
                                            </td>
                                            <td class="fs-14">
                                                <div class="mb-0 text-dark fs-14 lh-1 line--limit-2 min-w-120">
                                                    {{ $logRemarkLabels[$editLog->log] ?? translate(str_replace('_', ' ', $editLog->log ?? 'edited')) }}
                                                </div>
                                                <div class="text-info fs-12">
                                                    {{ translate('messages.edit_by') }} {{ translate('messages.' . ($editLog->edited_by ?? 'admin')) }}
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3">
                                                {{ translate('messages.no_edit_history') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <!-- Confiramtion Modal -->
    <div class="modal shedule-modal fade" id="edit_order_confirmation-btn" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-5 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-1">
                        <img src="{{asset('public/assets/admin/img/delete-confirmation.png')}}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('messages.Are You sure you want to edit this order?') }}</h3>
                        <p class="mb-0">{{ translate('messages.If you edit this order, some product details will be updated, which may affect the total price. ') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.no') }}</button>
                    <a href="{{ route('vendor.order.edit', $order->id) }}" class="btn min-w-120px btn--primary">{{ translate('messages.yes') }}</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Confiramtion Modal -->
    <div class="modal fade z-1051" id="food_list_delete" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-5 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-1">
                        <img src="{{asset('public/assets/admin/img/delete-confirmation.png')}}" alt="icon" class="mb-3">
                        @if ($order->store && $order->store->module && $order->store->module->module_type == 'food')
                            <h3 class="mb-2">{{ translate('messages.Are You Sure You Want To Delete This Food?') }}</h3>
                            <p class="mb-0">{{ translate('messages.If you delete this food item, it will be removed from the list. You will need to add it again by searching in the food list.') }}</p>
                        @else
                            <h3 class="mb-2">{{ translate('messages.Are You Sure You Want To Delete This Item?') }}</h3>
                            <p class="mb-0">{{ translate('messages.If you delete this item, it will be removed from the list. You will need to add it again by searching in the item list.') }}</p>
                        @endif
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.no') }}</button>
                    <button type="button" class="btn min-w-120px btn--primary" id="confirm-remove-cart-item" data-dismiss="modal">{{ translate('messages.yes') }}</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $pageRoutes = [
        'orderStatus'        => route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'canceled']),
        'quickViewCartItem'  => route('vendor.order.quick-view-cart-item'),
        'quickView'          => route('vendor.order.quick-view'),
        'variantPrice'       => route('vendor.item.variant-price'),
        'addToCart'          => route('vendor.order.add-to-cart'),
        'removeFromCart'     => route('vendor.order.remove-from-cart'),
        'orderUpdate'        => route('vendor.order.update', $order->id),
        'searchItems'        => route('vendor.order.search-items'),
        'cartList'           => route('vendor.order.cart-list'),
        'updateCartQuantity' => route('vendor.order.update-cart-quantity'),
    ];
    $pageTranslations = [
        'self_delivery_disable'       => translate('messages.Self_Delivery_is_Disable'),
        'are_you_sure'                => translate('messages.are_you_sure'),
        'are_you_sure_q'              => translate('messages.Are you sure ?'),
        'change_status_canceled'      => translate('messages.Change status to canceled ?'),
        'no'                          => translate('messages.no'),
        'yes'                         => translate('messages.yes'),
        'no_cap'                      => translate('messages.No'),
        'yes_cap'                     => translate('messages.Yes'),
        'submit'                      => translate('messages.submit'),
        'cancel'                      => translate('messages.Cancel'),
        'enter_verification_code'     => translate('Enter order verification code'),
        'enter_processing_time'       => translate('Enter processing time'),
        'enter_processing_time_label' => translate('Enter Processing time in minutes'),
        'select_reason'               => translate('Select Reason'),
        'invalid_image_type'          => translate('messages.please_only_input_png_or_jpg_type_file'),
        'file_too_big'                => translate('messages.file_size_too_big'),
        'already_in_cart'             => translate('messages.product_already_added_in_cart'),
        'added_to_cart'               => translate('messages.product_has_been_added_in_cart'),
        'order_updated'               => translate('messages.order_updated_successfully'),
        'remove_item_confirm'         => translate('messages.you_want_to_remove_this_order_item'),
        'item_removed'                => translate('messages.item_has_been_removed_from_cart'),
        'submit_all_confirm'          => translate('messages.you_want_to_submit_all_changes_for_this_order'),
        'cart_empty'                  => translate('messages.cart_is_empty'),
        'update_failed'               => translate('messages.order_update_failed'),
        'not_available_now'           => translate('messages.not_available'),
        'stock_qty'                   => translate('messages.Stock Qty'),
        'out_of_stock'                => translate('messages.out_of_stock'),
        'unavailable'                 => translate('messages.unavailable'),
        'veg'                         => translate('messages.veg'),
        'non_veg'                     => translate('messages.non_veg'),
        'halal'                       => translate('messages.Halal'),
        'price'                       => translate('messages.Price'),
        'add_to_cart'                 => translate('messages.add_to_cart'),
        'update_cart'                 => translate('messages.update_cart'),
    ];
    ?>
    <div id="order-page-config"
         hidden
         data-order-id="{{ $order->id }}"
         data-order-proof-count="{{ ($order->order_proof && is_array($order->order_proof)) ? count(json_decode($order->order_proof)) : 0 }}"
         data-open-edit-offcanvas="{{ (isset($editing) && $editing && session()->pull('open_edit_offcanvas')) ? 1 : 0 }}"
         data-img-upload="{{ asset('public/assets/admin/img/upload-img.png') }}"
         data-img-placeholder="{{ asset('public/assets/admin/img/100x100/2.png') }}"
         data-routes='@json($pageRoutes)'
         data-translations='@json($pageTranslations)'></div>

    <template id="cancel-reasons-template">@foreach ($reasons as $r)<option value="{{ $r->reason }}">{{ $r->reason }}</option>@endforeach</template>

@endsection
@push('script_2')
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/view-pages/order-edit-offcanvas.js') }}"></script>
@endpush
