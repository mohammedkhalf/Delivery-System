@php use App\CentralLogics\Helpers; @endphp
<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Service_List') }}</h1>
    </div>
    <div class="col-lg-12">

        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter_Criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Store') }}: {{ $data['store'] ?? translate('All') }}
                        <br>
                        {{ translate('Zone') }}: {{ $data['zone'] ?? translate('All') }}
                        <br>
                        {{ translate('Module') }}: {{ $data['module_name'] ?? translate('N/A') }}
                        <br>
                        {{ translate('category') }}: {{ $data['category'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Search_Bar_Content') }}: {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>

                <tr>
                    <th>{{ translate('sl') }}</th>
                    <th>{{ translate('Image') }}</th>
                    <th>{{ translate('service_name') }}</th>
                    <th>{{ translate('Category_Name') }}</th>
                    <th>{{ translate('zone') }}</th>
                    <th>{{ translate('base_price') }}</th>
                    <th>{{ translate('minimum_bidding_price') }}</th>
                    <th>{{ translate('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $service)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td> &nbsp;</td>
                        <td>{{ $service->name }}</td>
                        <td>{{ $service->category?->name ?? translate('messages.N/A') }}</td>
                        <td>{{ $service->store?->zone?->name ?? translate('messages.N/A') }}</td>
                        <td>{{ Helpers::format_currency($service->base_price) }}</td>
                        <td>{{ $service->min_bid_price ? Helpers::format_currency($service->min_bid_price) : translate('messages.N/A') }}</td>
                        <td>{{ $service->status ? translate('Active') : translate('Inactive') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
