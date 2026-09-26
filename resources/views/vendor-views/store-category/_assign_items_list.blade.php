@php($isService = $isService ?? false)
@if(count($items) === 0)
    <div class="text-center py-4 px-3">
        <img src="{{ asset('public/assets/admin/img/empty.png') }}" alt="empty" class="mb-2" width="80">
        <p class="mb-0 text-muted fs-12">{{ $isService ? translate('No uncategorized services to assign.') : translate('No uncategorized items to assign.') }}</p>
    </div>
@else
    <ul class="assign-items-scroll">
        @foreach($items as $item)
            <li class="assign-item-row {{ ((int) $item->store_category_id === (int) $category->id) ? 'is-selected is-locked' : '' }}">
                <label class="d-flex align-items-center gap-3 px-3 py-2"
                    for="assign_item_{{ $item->id }}">
                    <div class="fs-13 text-title fw-medium" style="min-width: 18px;">
                        {{ $loop->iteration }}
                    </div>
                    <img class="rounded onerror-image"
                        style="width: 48px; height: 48px; object-fit: cover; flex-shrink: 0;"
                        src="{{ $isService ? $item->thumbnail_full_url : $item->image_full_url }}"
                        data-onerror-image="{{ asset('public/assets/admin/img/100x100/img2.jpg') }}"
                        alt="{{ $item->name }}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-muted fs-11 mb-0">{{ translate('ID') }} #{{ $item->id }}</div>
                        <h6 class="mb-0 fw-bold text-truncate fs-14">{{ Str::limit($item->name, 32, '...') }}</h6>
                        <div class="d-flex align-items-center gap-3 fs-12 text-muted mt-1">
                            @if($isService)
                                @if(!is_null($item->base_price))
                                    <span>{{ translate('Price') }} {{ \App\CentralLogics\Helpers::format_currency($item->base_price) }}</span>
                                @endif
                            @else
                                @if(!is_null($item->price))
                                    <span>{{ translate('Price') }} {{ \App\CentralLogics\Helpers::format_currency($item->price) }}</span>
                                @endif
                                <span>{{ translate('Variation') }}
                                    {{ is_array($item->food_variations ?? null)
                                        ? count($item->food_variations)
                                        : (is_string($item->food_variations) ? count(json_decode($item->food_variations, true) ?: []) : 0) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="form-check m-0">
                        <input class="form-check-input assign-item-checkbox"
                            type="checkbox"
                            name="item_ids[]"
                            value="{{ $item->id }}"
                            id="assign_item_{{ $item->id }}"
                            {{ ((int) $item->store_category_id === (int) $category->id) ? 'checked disabled' : '' }}
                            title="{{ ((int) $item->store_category_id === (int) $category->id) ? translate('Already added to this category') : '' }}">
                    </div>
                </label>
            </li>
        @endforeach
    </ul>
@endif
