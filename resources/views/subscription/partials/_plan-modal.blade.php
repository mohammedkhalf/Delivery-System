<div class="modal fade show" id="plan-modal">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header px-3 pt-3">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body px-4 pt-0">
                <div>
                    <div class="text-center">
                        <h2 class="modal-title">{{ translate('Change Subscription Plan') }}</h2>
                    </div>
                    <div class="text-center text-14 mb-4 pb-3">
                       {{ translate('Renew or shift your plan to get better experience!') }}
                    </div>
                    <div class="plan-slider owl-theme owl-carousel owl-refresh">
                        @if (\App\CentralLogics\Helpers::commission_check())
                        <div class="__plan-item hover {{ $store->store_business_model == 'commission'  ? 'active' : ''}} ">
                            <div class="inner-div">
                                <div class="text-center">
                                    <h3 class="title">{{ translate('Commission Base') }}</h3>
                                    <h2 class="price">{{  $store->comission ?? $admin_commission }}%</h2>
                                </div>
                                <div class="py-5 mt-4">
                                    <div class="info-text text-center">
                                        {{ translate($title.' will pay') }} {{  $store->comission ?? $admin_commission }}% {{ translate('commission to') }} {{ $business_name }} {{ translate('from each '.$orderOrTrip.'. You will get access of all the features and options  in '.$title.' panel , app and interaction with user.') }}
                                    </div>
                                </div>
                                <div class="text-center">
                                    @if ($store->store_business_model == 'commission')
                                    <button type="button" class="btn btn--secondary">{{ translate('Current_Plan') }}</button>
                                    @else
                                        @php($cash_backs = \App\CentralLogics\Helpers::calculateSubscriptionRefundAmount(store: $store, return_data: true))
                                        <button type="button" data-url="{{route($routePrefix.'.switchToCommission',$store->id)}}" data-message="{{translate('You_Want_To_Migrate_To_Commission.')}} {{ data_get($cash_backs,'back_amount') > 0  ?  translate('You will get').' '. \App\CentralLogics\Helpers::format_currency(data_get($cash_backs,'back_amount')) .' '.translate('to_your_wallet_for_remaining') .' '.data_get($cash_backs,'days').' '.translate('messages.days_subscription_plan') : '' }}" class="btn btn--primary shift_to_commission">{{ translate('Shift in this plan') }}</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        @forelse ($packages as $package)
                        <div class="__plan-item hover {{ $store?->store_sub_update_application?->package_id == $package->id  && $store->store_business_model != 'commission'  ? 'active' : ''}}">
                            <div class="inner-div">
                                <div class="text-center">
                                    <h3 class="title">{{ $package->package_name }}</h3>
                                    <h2 class="price">{{ \App\CentralLogics\Helpers::format_currency($package->price)}}</h2>
                                    <div class="day-count">{{ $package->validity }} {{ translate('messages.days') }}</div>
                                </div>
                                <ul class="info">

                                    @if ($package->pos && ($showPos ?? true))
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.POS') }} </span>
                                    </li>
                                    @endif
                                    @if ($package->mobile_app)
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.mobile_app') }} </span>
                                    </li>
                                    @endif
                                    @if ($package->chat)
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.chatting_options') }} </span>
                                    </li>
                                    @endif
                                    @if ($package->review)
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.review_section') }} </span>
                                    </li>
                                    @endif
                                    @if ($package->self_delivery && ($showSelfDelivery ?? true))
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.self_delivery') }} </span>
                                    </li>
                                    @endif
                                    @if ($package->max_order == 'unlimited')
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ $store?->module->module_type == 'rental' && addon_published_status('Rental') ? translate('messages.unlimited_trips') : (($isServiceModule ?? false) ? translate('messages.Unlimited_Bookings') : translate('messages.Unlimited_Orders')) }} </span>
                                    </li>
                                    @else
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ $package->max_order }} {{ $store?->module->module_type == 'rental' && addon_published_status('Rental') ? translate('messages.trips') : (($isServiceModule ?? false) ? translate('messages.Bookings') : translate('messages.Orders')) }} </span>
                                    </li>
                                    @endif
                                    @if ($package->max_product == 'unlimited')
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ ($isServiceModule ?? false) ? translate('messages.Unlimited_Service_Uploads') : translate('messages.Unlimited_uploads') }} </span>
                                    </li>
                                    @else
                                    <li>
                                        <i class="tio-checkmark-circle"></i> <span>  {{ $package->max_product }} {{ ($isServiceModule ?? false) ? translate('messages.Service_Uploads') : translate('messages.uploads') }} </span>
                                    </li>
                                    @endif

                                </ul>
                                <div class="text-center">
                                    @if ( $store?->store_business_model != 'commission'  && $store?->store_sub_update_application?->package_id == $package->id)
                                    <button data-id="{{ $package->id }}"  data-url="{{route($routePrefix.'.packageView',[$package->id,$store->id ])}}"
                                        data-target="#package_detail" id="package_detail" type="button" class="btn btn--warning text-white renew-btn package_detail">{{ translate('messages.Renew') }}</button>
                                    @else
                                    <button data-id="{{ $package->id }}" data-url="{{route($routePrefix.'.packageView',[$package->id,$store->id ])}}"
                                        data-target="#package_detail" id="package_detail" type="button" class="btn btn--primary shift-btn package_detail">{{ translate('messages.Shift_in_this_plan') }}</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @empty

                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
