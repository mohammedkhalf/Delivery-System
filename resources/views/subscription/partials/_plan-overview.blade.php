<div class="card mb-3">
    <div class="card-header flex-wrap gap-2 border-0 align-items-center">
        <div>
            <h3 class="card-title align-items-center gap-2">
                <span class="text-title">{{ translate('Package Overview') }}
                    @if ($showStatusBadge ?? false)
                        @if($store?->status == 0 &&  $store?->vendor?->status == 0)
                        <span class=" badge badge-pill badge-info">  &nbsp; {{ translate('Approval_Pending') }}  &nbsp; </span>
                        @elseif($store?->store_sub_update_application?->status == 0)
                        <span class=" badge badge-pill badge-danger">  &nbsp; {{ translate('Expired') }}  &nbsp; </span>
                        @elseif ($store?->store_sub_update_application?->is_canceled == 1)
                        <span class=" badge badge-pill badge-warning">  &nbsp; {{ translate('canceled') }}  &nbsp; </span>
                        @elseif($store?->store_sub_update_application?->status == 1)
                        <span class=" badge badge-pill badge-success">  &nbsp; {{ translate('Active') }}  &nbsp; </span>
                        @endif
                    @endif
                </span>
            </h3>
            <span class="fs-12 d-block color-334257B2">{{ translate('Here you see the active business plan.') }}</span>
        </div>
        <div class="btn--container justify-content-end mt-20">
            @if ( $store?->store_sub_update_application?->is_canceled == 0 && $store?->store_sub_update_application?->status == 1  )
            <button type="button"  data-url="{{route($routePrefix.'.cancelSubscription',$store?->id)}}" data-message="{{translate('If_you_cancel_the_subscription,_after_')}} {{  \Carbon\Carbon::now()->diffInDays($store?->store_sub_update_application?->expiry_date_parsed->format('Y-m-d'), false) }} {{ translate('days_the_vendor_will_no_longer_be_able_to_run_the_business_before_subscribe_a_new_plan.') }}"
                class="btn btn--danger text-white status_change_alert">{{ translate('Cancel Subscription') }}</button>
            @endif
            <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn text-wrap btn--primary">{{ translate('Change / Renew Subscription Plan') }}</button>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="bg-F7F8F9 p--20 rounded mb-20">
            <div class="d-flex flex-wrap flex-md-nowrap justify-content-between __plan-details-top">
                <div class="left">
                    <h3 class="name">{{ $store?->store_sub_update_application?->package?->package_name }}</h3>
                    <div class="font-medium text--title">{{ $store?->store_sub_update_application?->package?->text }}</div>
                </div>
                <h3 class="right">{{ \App\CentralLogics\Helpers::format_currency($store?->store_sub_update_application?->last_transcations?->price) }} /<small class="font-medium text--title">{{ $store?->store_sub_update_application?->last_transcations?->validity }} {{ translate('messages.Days') }}</small></h3>
            </div>
        </div>
        <div class="bg-F7F8F9 p--20 rounded">
            <div class="check--grid-wrapper mt-3 max-w-850px">

                <div>
                    <div class="d-flex align-items-center gap-2">
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @if ( $store?->store_sub_update_application?->max_order == 'unlimited' )
                        <span class="form-check-label text-dark">{{ $store?->module->module_type == 'rental' && addon_published_status('Rental') ? translate('messages.unlimited_trips') : ($isServiceModule ? translate('messages.Unlimited_Bookings') : translate('messages.unlimited_orders')) }}</span>
                        @else
                        <span class="form-check-label text-dark"> {{ $store?->store_sub_update_application?->package?->max_order }} {{
                           $store?->module->module_type == 'rental' && addon_published_status('Rental') ? translate('messages.trips') : ($isServiceModule ? translate('messages.Bookings') : translate('messages.Orders')) }} <small>({{ $store?->store_sub_update_application?->max_order }} {{ translate('left') }}) </small> </span>
                        @endif
                    </div>
                </div>

                @if ($showPos ?? true)
                <div>
                    <div class="d-flex align-items-center gap-2">
                        @if ( $store?->store_sub_update_application?->pos == 1 )
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @else
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                        @endif
                        <span class="form-check-label text-dark">{{ translate('messages.POS') }}</span>
                    </div>
                </div>
                @endif

                <div>
                    <div class="d-flex align-items-center gap-2">
                        @if ( $store?->store_sub_update_application?->mobile_app == 1 )
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @else
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                        @endif
                        <span class="form-check-label text-dark">{{ translate('messages.Mobile_App') }}</span>
                    </div>
                </div>

                @if ($showSelfDelivery ?? true)
                <div>
                    <div class="d-flex align-items-center gap-2">
                        @if ( $store?->store_sub_update_application?->self_delivery == 1 )
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @else
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                        @endif
                        <span class="form-check-label text-dark">{{ translate('messages.self_delivery') }}</span>
                    </div>
                </div>
                @endif

                <div>
                    <div class="d-flex align-items-center gap-2">
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @if ( $store?->store_sub_update_application?->max_product == 'unlimited' )
                        <span class="form-check-label text-dark">{{ $store?->module->module_type == 'rental' && addon_published_status('Rental') ? translate('messages.unlimited_Upload') : ($isServiceModule ? translate('messages.Unlimited_Service_Upload') : translate('messages.unlimited_item_Upload')) }}</span>
                        @else
                        <span class="form-check-label text-dark"> {{ $store?->store_sub_update_application?->max_product }} {{ $store?->module->module_type == 'rental' && addon_published_status('Rental') ? translate('messages.Upload') : ($isServiceModule ? translate('messages.Service_Upload') : translate('messages.product_Upload')) }} <small>({{ $store?->store_sub_update_application?->max_product  - $store->items_count > 0 ? $store?->store_sub_update_application?->max_product  - $store->items_count : 0 }} {{ translate('left') }}) </small></span>
                        @endif
                    </div>
                </div>

                <div>
                    <div class="d-flex align-items-center gap-2">
                        @if ( $store?->store_sub_update_application?->review == 1 )
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @else
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                        @endif
                        <span class="form-check-label text-dark">{{ translate('messages.review') }}</span>
                    </div>
                </div>

                <div>
                    <div class="d-flex align-items-center gap-2">
                        @if ( $store?->store_sub_update_application?->chat == 1 )
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                        @else
                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                        @endif
                        <span class="form-check-label text-dark">{{ translate('messages.chat') }}</span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
