@php use App\CentralLogics\Helpers; @endphp
<table>
    <thead>
        <tr>
            <th>{{ translate('messages.SL') }}</th>
            <th>{{ translate('messages.service_name') }}</th>
            <th>{{ translate('messages.category') }}</th>
            <th>{{ translate('messages.provider') }}</th>
            <th>{{ translate('messages.zone') }}</th>
            <th>{{ translate('messages.base_price') }}</th>
            <th>{{ translate('messages.status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['data'] as $key => $service)
            <tr>
                <td>{{ $key + 1 }}</td>
                <td>{{ $service->name }}</td>
                <td>{{ $service->category?->name ?? translate('messages.N/A') }}</td>
                <td>{{ $service->store?->name ?? translate('messages.N/A') }}</td>
                <td>{{ $service->store?->zone?->name ?? translate('messages.N/A') }}</td>
                <td>{{ Helpers::format_currency($service->base_price) }}</td>
                <td>{{ $service->is_rejected ? translate('messages.rejected') : translate('messages.pending') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
