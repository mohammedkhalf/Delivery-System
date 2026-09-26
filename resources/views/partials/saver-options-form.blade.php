@php
    $pivotRow         = $pivot         ?? null;
    $expressOption    = $express_option ?? null;
    $delayedOption    = $delayed_option ?? null;
    $saverEnabled     = (bool) ($pivotRow->additional_delivery_option_status ?? false);
    $minDeliveryTime  = $pivotRow ? \App\Models\ModuleZoneDeliveryOption::minutesToPair((int) ($pivotRow->minimum_delivery_time ?? 0)) : ['value' => '', 'unit' => 'min', 'minutes' => 0];
    $minChargeValue   = $pivotRow ? ($pivotRow->minimum_delivery_charge ?? '') : '';
    $expressExtra     = $expressOption['extra_charge']          ?? null;
    $expressReducePair= $expressOption ? $expressOption['reduce_delivery_time'] : ['value' => '', 'unit' => 'min', 'minutes' => 0];
    $delayedReduce    = $delayedOption['reduce_charge']         ?? null;
    $delayedAddPair   = $delayedOption ? $delayedOption['add_delivery_time'] : ['value' => '', 'unit' => 'min', 'minutes' => 0];
    $namePrefix       = "module_data[{$module->id}]";
@endphp

<div class="card mt-4 card-container saver-options-card" data-module-id="{{ $module->id }}">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
            <div>
                <h4 class="mb-1">{{ translate('messages.Additional Delivery Charge Setup') }}</h4>
                <p class="fs-12 m-0">{{ translate('messages.If enable this feature customers will receive option to choose delivery type') }}</p>
            </div>
            <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-3">
                <div class="view_toggle_btn {{ $saverEnabled ? 'active' : '' }} fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                    {{ translate('messages.view') }}
                    <i class="tio-chevron-down fs-22"></i>
                </div>
                <div class="mb-0">
                    <label class="toggle-switch toggle-switch-sm mb-0">
                        <input type="hidden" name="{{ $namePrefix }}[additional_delivery_option_status]" value="0">
                        <input type="checkbox" data-type="toggle" class="status toggle-switch-input saver-options-toggle"
                               name="{{ $namePrefix }}[additional_delivery_option_status]"
                               value="1" {{ $saverEnabled ? 'checked' : '' }}>
                        <span class="toggle-switch-label text mb-0">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card-details-body saver-options-body {{ $saverEnabled ? '' : 'd-none' }}">
            <div class="bg-light2 rounded p-xxl-20 p-3 mt-20">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4">
                        <h5 class="mb-1">{{ translate('messages.Threshold Delivery Time') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('messages.Set a minimum delivery time. After reduction, the final delivery time cannot be lower than this limit.') }}</p>
                    </div>
                    <div class="col-lg-8">
                        <div class="bg-white rounded p-3">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="input-label text-capitalize d-flex align-items-center gap-1">
                                        <span class="line--limit-1">{{ translate('messages.Set minimum delivery time limit') }}</span>
                                    </label>
                                    <div class="d-flex border rounded overflow-hidden">
                                        <input type="number" min="0" placeholder="{{ translate('messages.Ex: 20') }}"
                                               class="form-control rounded-0 border-0 saver-required"
                                               name="{{ $namePrefix }}[minimum_delivery_time]"
                                               value="{{ $minDeliveryTime['value'] }}">
                                        <select class="custom-select rounded-0 border-0 bg-modal-btn form-control w-90px"
                                                name="{{ $namePrefix }}[minimum_delivery_time_unit]">
                                            <option value="min"  {{ $minDeliveryTime['unit'] === 'min'  ? 'selected' : '' }}>{{ translate('messages.min') }}</option>
                                            <option value="hour" {{ $minDeliveryTime['unit'] === 'hour' ? 'selected' : '' }}>{{ translate('messages.hour') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="input-label text-capitalize d-flex align-items-center gap-1">
                                        <span class="line--limit-1">{{ translate('messages.Minimum Delivery Charge For Delivery Type') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</span>
                                        <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                              data-title="{{ translate('messages.Set the minimum delivery charge allowed in this zone. The Reduce Charge cannot exceed this limit.') }}">
                                            <i class="tio-info text-muted"></i>
                                        </span>
                                    </label>
                                    <input type="number" min="0" step=".01" placeholder="{{ translate('messages.Ex: 5') }}"
                                           class="form-control saver-required"
                                           name="{{ $namePrefix }}[minimum_delivery_charge]"
                                           value="{{ $minChargeValue }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-light2 rounded p-xxl-20 p-3 mt-20">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4">
                        <h5 class="mb-1">{{ translate('messages.Express Delivery') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('messages.Deliver faster by reducing delivery time with an additional charge.') }}</p>
                    </div>
                    <div class="col-lg-8">
                        <div class="bg-white rounded p-3">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="input-label text-capitalize d-flex align-items-center gap-1">
                                        <span class="line--limit-1">{{ translate('messages.Add Extra Charge') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</span>
                                        <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                              data-title="{{ translate('messages.Add extra charge to the delivery fee. This amount will be added to the total delivery charge.') }}">
                                            <i class="tio-info text-muted"></i>
                                        </span>
                                    </label>
                                    <input type="number" min="0" step=".01" placeholder="{{ translate('messages.Ex: 5') }}"
                                           class="form-control saver-required"
                                           name="{{ $namePrefix }}[delivery_types][express][extra_charge]"
                                           value="{{ $expressExtra }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="input-label text-capitalize d-flex align-items-center gap-1">
                                        <span class="line--limit-1">{{ translate('messages.Reduce Delivery Time') }}</span>
                                        <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                              data-title="{{ translate('messages.Set a reduced delivery time, ensuring the final delivery time is not less than minimum delivery time limit after deduction.') }}">
                                            <i class="tio-info text-muted"></i>
                                        </span>
                                    </label>
                                    <div class="d-flex border rounded overflow-hidden">
                                        <input type="number" min="0" placeholder="{{ translate('messages.Ex: 20') }}"
                                               class="form-control rounded-0 border-0 saver-required"
                                               name="{{ $namePrefix }}[delivery_types][express][reduce_delivery_time]"
                                               value="{{ $expressReducePair['value'] }}">
                                        <select class="custom-select rounded-0 border-0 bg-modal-btn form-control w-90px"
                                                name="{{ $namePrefix }}[delivery_types][express][reduce_delivery_time_unit]">
                                            <option value="min"  {{ $expressReducePair['unit'] === 'min'  ? 'selected' : '' }}>{{ translate('messages.min') }}</option>
                                            <option value="hour" {{ $expressReducePair['unit'] === 'hour' ? 'selected' : '' }}>{{ translate('messages.hour') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-light2 rounded p-xxl-20 p-3 mt-20">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4">
                        <h5 class="mb-1">{{ translate('messages.Slightly Delay Delivery') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('messages.Deliver a bit later and offer a reduced delivery charge.') }}</p>
                    </div>
                    <div class="col-lg-8">
                        <div class="bg-white rounded p-3">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="input-label text-capitalize d-flex align-items-center gap-1">
                                        <span class="line--limit-1">{{ translate('messages.Reduce Charge') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</span>
                                        <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                              data-title="{{ translate('messages.Set a reduced charge, ensuring the final delivery charge is not less than minimum delivery charge after deduction.') }}">
                                            <i class="tio-info text-muted"></i>
                                        </span>
                                    </label>
                                    <input type="number" min="0" step=".01" placeholder="{{ translate('messages.Ex: 5') }}"
                                           class="form-control saver-required"
                                           name="{{ $namePrefix }}[delivery_types][slightly_delay][reduce_charge]"
                                           value="{{ $delayedReduce }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="input-label text-capitalize d-flex align-items-center gap-1">
                                        <span class="line--limit-1">{{ translate('messages.Add Extra Delivery Time') }}</span>
                                        <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                              data-title="{{ translate('messages.Adjust delivery time by adding extra time. The final delivery time must not go below the minimum delivery limit.') }}">
                                            <i class="tio-info text-muted"></i>
                                        </span>
                                    </label>
                                    <div class="d-flex border rounded overflow-hidden">
                                        <input type="number" min="0" placeholder="{{ translate('messages.Ex: 20') }}"
                                               class="form-control rounded-0 border-0 saver-required"
                                               name="{{ $namePrefix }}[delivery_types][slightly_delay][add_delivery_time]"
                                               value="{{ $delayedAddPair['value'] }}">
                                        <select class="custom-select rounded-0 border-0 bg-modal-btn form-control w-90px"
                                                name="{{ $namePrefix }}[delivery_types][slightly_delay][add_delivery_time_unit]">
                                            <option value="min"  {{ $delayedAddPair['unit'] === 'min'  ? 'selected' : '' }}>{{ translate('messages.min') }}</option>
                                            <option value="hour" {{ $delayedAddPair['unit'] === 'hour' ? 'selected' : '' }}>{{ translate('messages.hour') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
