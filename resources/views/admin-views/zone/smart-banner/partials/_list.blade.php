<div class="table-responsive datatable-custom">
    <table id="columnSearchDatatable"
           class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
        <thead class="thead-light">
            <tr>
                <th class="border-0 fs-14">{{ translate('messages.SL') }}</th>
                <th class="border-0 fs-14">{{ translate('messages.banner_info') }}</th>
                <th class="border-0 fs-14">
                    <div class="min-w-160px">{{ translate('messages.duration') }}</div>
                </th>
                <th class="border-0 fs-14">{{ translate('messages.module') }}</th>
                <th class="border-0 fs-14">{{ translate('messages.position') }}</th>
                <th class="border-0 fs-14">{{ translate('messages.status') }}</th>
                <th class="border-0 fs-14 text-center">{{ translate('messages.action') }}</th>
            </tr>
        </thead>
        <tbody id="set-rows">
            @foreach($banners as $key => $banner)
                <tr>
                    <td class="pl-4">{{ $key + $banners->firstItem() }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2 max-w-320px">
                            <img src="{{ $banner->image_full_url ?? asset('public/assets/admin/svg/illustrations/sorry.svg') }}"
                                 alt="banner" class="rounded" style="width: 56px; height: 56px; object-fit: cover;">
                            <div class="min-w-0">
                                <span class="d-block text-title fs-14 text-truncate" style="max-width: 240px;">{{ $banner->title }}</span>
                                <span class="d-block fs-12 text-muted text-truncate" style="max-width: 240px;">{{ $banner->subtitle }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="d-block fs-12">
                            {{ translate('messages.Date') }}:
                            @if($banner->active_days === 'everyday')
                                {{ translate('messages.everyday') }}
                            @else
                                {{ \App\CentralLogics\Helpers::date_format($banner->start_date) }} - {{ \App\CentralLogics\Helpers::date_format($banner->end_date) }}
                            @endif
                        </span>
                        <span class="d-block fs-12">
                            {{ translate('messages.Time') }}:
                            @if($banner->start_time)
                                {{ \App\CentralLogics\Helpers::time_format($banner->start_time) }} -
                                {{ $banner->end_time ? \App\CentralLogics\Helpers::time_format($banner->end_time) : translate('messages.until_you_turn_off') }}
                            @else
                                {{ translate('messages.all_day') }}
                            @endif
                        </span>
                    </td>
                    <td>{{ $banner->module ? translate($banner->module->module_name) : translate('messages.all_modules') }}</td>
                    <td>{{ translate(ucfirst($banner->position)) }} {{ translate('messages.position') }}</td>
                    <td>
                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $banner['id'] }}">
                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                   data-id="status-{{ $banner['id'] }}"
                                   data-type="status"
                                   data-image-on='{{ asset('public/assets/admin/img/status-ons.png') }}'
                                   data-image-off="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                   data-title-on="{{ translate('messages.want_to_turn_on_smart_banner') }}"
                                   data-title-off="{{ translate('messages.want_to_turn_off_smart_banner') }}"
                                   data-text-on="<p>{{ translate('messages.this_banner_will_become_visible_to_customers.') }}</p>"
                                   data-text-off="<p>{{ translate('messages.this_banner_will_be_hidden_from_customers.') }}</p>"
                                   id="status-{{ $banner['id'] }}" {{ $banner->status ? 'checked' : '' }}>
                            <span class="toggle-switch-label">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                        <form action="{{ route('admin.business-settings.zone.smart-banner.status', [$banner['id'], $banner->status ? 0 : 1]) }}"
                              method="get" id="status-{{ $banner['id'] }}_form">
                        </form>
                    </td>
                    <td>
                        <div class="btn--container justify-content-center">
                            <a class="btn action-btn btn--primary btn-outline-primary offcanvas-trigger smart-banner-edit-trigger"
                               href="javascript:"
                               data-id="{{ $banner['id'] }}"
                               data-url="{{ route('admin.business-settings.zone.smart-banner.edit', [$banner['id']]) }}"
                               data-target="#smartBannerForm_offcanvas"
                               title="{{ translate('messages.edit') }}">
                                <i class="tio-edit"></i>
                            </a>
                            <a class="btn action-btn btn--primary btn-outline-primary offcanvas-trigger smart-banner-view-trigger"
                               href="javascript:"
                               data-id="{{ $banner['id'] }}"
                               data-url="{{ route('admin.business-settings.zone.smart-banner.view', [$banner['id']]) }}"
                               data-target="#smartBannerView_offcanvas"
                               title="{{ translate('messages.view') }}">
                                <i class="tio-visible-outlined"></i>
                            </a>
                            <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                               href="javascript:"
                               data-id="smart-banner-{{ $banner['id'] }}"
                               data-message="{{ translate('messages.are_you_sure_you_want_to_delete_this_smart_banner_permanently') }}"
                               title="{{ translate('messages.delete') }}">
                                <i class="tio-delete-outlined"></i>
                            </a>
                            <form action="{{ route('admin.business-settings.zone.smart-banner.delete', [$banner['id']]) }}"
                                  method="post" id="smart-banner-{{ $banner['id'] }}">
                                @csrf @method('delete')
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if (count($banners) !== 0)
    <hr>
@endif
<div class="page-area">
    {!! $banners->withQueryString()->links() !!}
</div>
@if (count($banners) === 0)
    <div class="empty--data">
        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
        <h5>{{ translate('messages.no_data_found') }}</h5>
    </div>
@endif
