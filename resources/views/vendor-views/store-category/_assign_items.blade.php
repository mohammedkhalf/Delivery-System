<style>
    .assign-items-scroll { list-style: none; padding-left: 0; margin-bottom: 0; }
    .assign-items-scroll .assign-item-row { border-radius: 10px; transition: background-color 0.15s ease; }
    .assign-items-scroll .assign-item-row + .assign-item-row { margin-top: 4px; }
    .assign-items-scroll .assign-item-row > label { cursor: pointer; margin: 0; width: 100%; }
    .assign-items-scroll .assign-item-row:hover { background-color: #F5F7FA; }
    .assign-items-scroll .assign-item-row.is-selected,
    .assign-items-scroll .assign-item-row:has(.assign-item-checkbox:checked) {
        background-color: #E6F0FE;
    }
    /* Locked rows (already saved to this category) — user can't uncheck. */
    .assign-items-scroll .assign-item-row.is-locked > label { cursor: not-allowed; }
    .assign-items-scroll .assign-item-row.is-locked .assign-item-checkbox {
        opacity: 1; /* keep the primary tick visible — Bootstrap dims disabled */
        background-color: var(--bs-primary, #0d6efd);
        border-color: var(--bs-primary, #0d6efd);
    }
    #assignItemsListContainer { min-height: 0; overflow-y: auto; }
</style>
<form id="assignItemsForm"
      action="{{ route('vendor.store-category.items.assign', $category->id) }}"
      method="post"
      class="d-flex flex-column h-100"
      data-search-url="{{ route('vendor.store-category.items.search', $category->id) }}">
    @csrf

    @php($isService = $isService ?? false)
    <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
        <h3 class="mb-0">{{ $isService ? translate('Select Services For Category') : translate('Select Items For Category') }}</h3>
        <button type="button"
            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
            aria-label="Close">&times;</button>
    </div>

    <div class="custom-offcanvas-body p-20 d-flex flex-column flex-grow-1" style="min-height: 0;">
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3 py-2 px-3" role="alert"
            style="background-color: #FFF8E5; border: 1px solid #FFE6A8;">
            <i class="tio-info mt-1" style="color: #F2A93B;"></i>
            <div class="fs-12 text-body">
                @if($isService)
                    {{ translate('Once you create store categories, you must add your services to those categories. Without assigning services, they will not appear on your store details page. If you want to proceed with the main category, you can skip adding any store categories.') }}
                @else
                    {{ translate('Once you create store categories, you must add your items to those categories. Without assigning items, they will not appear on your store details page. If you want to proceed with the main category, you can skip adding any store categories.') }}
                @endif
            </div>
        </div>

        @if($unassignedCount > 0)
            <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3" role="alert"
                style="background-color: #FDECEC; border: 1px solid #F8C5C5; border-radius: 8px;">
                <i class="tio-warning-outlined mt-1" style="color: #E53935;"></i>
                <div class="fs-12 text-body">
                    {{ translate('There are') }}
                    <strong>{{ $unassignedCount }} {{ $isService ? translate('services') : translate('items') }}</strong>
                    {{ translate('that are unassigned to any category. Please assign them to a category so they can be visible on the store details page.') }}
                </div>
            </div>
        @endif

        <div class="form-group mb-3">
            <input type="text" name="assign_search" class="form-control h--40px"
                placeholder="{{ translate('Search here') }}"
                autocomplete="off">
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <h6 class="mb-0">{{ $isService ? translate('Service List') : translate('Item List') }}</h6>
            <span class="badge badge-soft-primary fs-12">
                <span id="assignItemsSelectedCount">0</span> {{ translate('Selected') }}
            </span>
        </div>

        <div id="assignItemsListContainer" class="flex-grow-1">
            @include('vendor-views.store-category._assign_items_list', [
                'items' => $items,
                'category' => $category,
                'isService' => $isService,
            ])
        </div>
    </div>

    <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center offcanvas-footer p-3">
        <button type="button" class="btn w-100 btn--reset h--40px reset-assign-btn">{{ translate('Reset') }}</button>
        <button type="submit" class="btn w-100 btn--primary h--40px">{{ translate('Save') }}</button>
    </div>
</form>
