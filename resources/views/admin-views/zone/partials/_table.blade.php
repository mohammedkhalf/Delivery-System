      <div class="table-responsive datatable-custom">
          <table id="columnSearchDatatable"
              class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
              data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
              <thead class="thead-light">
                  <tr>
                      <th class="border-0">{{ translate('messages.SL') }}</th>
                      <th class="border-0">{{ translate('messages.zone_Id') }}</th>
                      <th class="border-0">{{ translate('messages.business_Zone_name') }}</th>
                      <th class="border-0">{{ translate('messages.vendors') }}</th>
                      <th class="border-0">{{ translate('messages.deliverymen') }}</th>
                        <th class="border-0 text-center">{{ translate('Default_Status') }}</th>
                      <th class="border-0">{{ translate('messages.status') }}</th>
                      <th class="border-0 text-center">{{ translate('messages.action') }}</th>
                  </tr>
              </thead>

              <tbody id="set-rows">
                  @include('admin-views.zone.partials._table_rows', ['zones' => $zones, 'config' => $config, 'digital_payment' => $digital_payment, 'offline_payment' => $offline_payment])
              </tbody>
          </table>
      </div>
      @if (count($zones) !== 0)
          <hr>
      @endif
      <div class="page-area">
          {!! $zones->withQueryString()->links() !!}
      </div>
      @if (count($zones) === 0)
          <div class="empty--data">
              <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
              <h5>
                  {{ translate('no_data_found') }}
              </h5>
          </div>
      @endif
