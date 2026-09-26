<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('messages.Pro_Customer_Subscription_List') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('messages.Subscription_Analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Total_Subscriber') }}: {{ $data['stats']['total'] ?? 0 }}
                        <br>
                        {{ translate('messages.Active_Subscriber') }}: {{ $data['stats']['active'] ?? 0 }}
                        <br>
                        {{ translate('messages.Inactive_Subscriber') }}: {{ $data['stats']['inactive'] ?? 0 }}
                        <br>
                        {{ translate('messages.Total_Earned') }}: {{ \App\CentralLogics\Helpers::format_currency((float) ($data['stats']['total_earned'] ?? 0)) }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.Search_Criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Search_Bar_Content') }}: {{ $data['search'] ?? translate('messages.N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.Filter_Criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Tab') }}: {{ ucfirst($data['tab'] ?? 'all') }}
                        <br>
                        {{ translate('messages.Plan') }}: {{ $data['plan_name'] ?? translate('messages.All') }}
                        <br>
                        {{ translate('messages.Subscription_Status') }}: {{ $data['subscription_status'] ? ucfirst($data['subscription_status']) : translate('messages.All') }}
                        <br>
                        {{ translate('messages.Renewal_Status') }}: {{ $data['renewal_status'] ? ucfirst($data['renewal_status']) : translate('messages.All') }}
                        <br>
                        {{ translate('messages.Date_Range') }}: {{ $data['dates'] ?? translate('messages.N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.SL') }}</th>
                    <th>{{ translate('messages.Customer_Name') }}</th>
                    <th>{{ translate('messages.Email') }}</th>
                    <th>{{ translate('messages.Phone') }}</th>
                    <th>{{ translate('messages.Plan_Name') }}</th>
                    <th>{{ translate('messages.Plan_Type') }}</th>
                    <th>{{ translate('messages.Plan_Price') }}</th>
                    <th>{{ translate('messages.Start_Date') }}</th>
                    <th>{{ translate('messages.End_Date') }}</th>
                    <th>{{ translate('messages.Total_Orders') }}</th>
                    <th>{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['subscriptions'] as $key => $sub)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $sub->user ? trim(($sub->user->f_name ?? '') . ' ' . ($sub->user->l_name ?? '')) : translate('messages.N/A') }}</td>
                        <td>{{ $sub->user?->email ?? translate('messages.N/A') }}</td>
                        <td>{{ $sub->user?->phone ?? translate('messages.N/A') }}</td>
                        <td>{{ $sub->plan_name }}</td>
                        <td>{{ $sub->plan_type === 'free_trial' ? translate('messages.Free_Trial') : translate('messages.Paid') }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency((float) $sub->plan_price) }}</td>
                        <td>{{ $sub->start_at ? \Carbon\Carbon::parse($sub->start_at)->format('d M Y H:i') : translate('messages.N/A') }}</td>
                        <td>{{ $sub->end_at ? \Carbon\Carbon::parse($sub->end_at)->format('d M Y H:i') : translate('messages.N/A') }}</td>
                        <td>{{ $sub->total_orders ?? 0 }}</td>
                        <td>{{ ucfirst($sub->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
