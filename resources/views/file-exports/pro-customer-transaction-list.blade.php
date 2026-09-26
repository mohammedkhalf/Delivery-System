<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('messages.Pro_Customer_Transaction_List') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('messages.Transaction_Analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Total_Transactions') }}: {{ $data['transactions']->count() }}
                        <br>
                        {{ translate('messages.Total_Earned') }}: {{ \App\CentralLogics\Helpers::format_currency((float) $data['transactions']->where('plan_type', 'paid')->where('payment_status', 'success')->sum('plan_price')) }}
                    </th>
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
                </tr>
                <tr>
                    <th>{{ translate('messages.Filter_Criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Plan') }}: {{ $data['plan_name'] ?? translate('messages.All') }}
                        <br>
                        {{ translate('messages.Plan_Type') }}: {{ $data['plan_type'] ? ucfirst(str_replace('_', ' ', $data['plan_type'])) : translate('messages.All') }}
                        <br>
                        {{ translate('messages.Date_Range') }}: {{ $data['dates'] ?? translate('messages.N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.SL') }}</th>
                    <th>{{ translate('messages.Transaction_ID') }}</th>
                    <th>{{ translate('messages.Transaction_Date') }}</th>
                    <th>{{ translate('messages.Customer_Name') }}</th>
                    <th>{{ translate('messages.Email') }}</th>
                    <th>{{ translate('messages.Plan_Name') }}</th>
                    <th>{{ translate('messages.Pricing') }}</th>
                    <th>{{ translate('messages.Plan_Validity') }}</th>
                    <th>{{ translate('messages.Payment_By') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['transactions'] as $key => $tx)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>#{{ $tx->id }}</td>
                        <td>{{ ($tx->paid_at ?? $tx->created_at)?->format('d M Y H:i') ?? translate('messages.N/A') }}</td>
                        <td>{{ $tx->user ? trim(($tx->user->f_name ?? '') . ' ' . ($tx->user->l_name ?? '')) : translate('messages.N/A') }}</td>
                        <td>{{ $tx->user?->email ?? translate('messages.N/A') }}</td>
                        <td>{{ $tx->plan_name }}</td>
                        <td>{{ $tx->plan_type === 'free_trial' ? translate('messages.Free_Trial') : \App\CentralLogics\Helpers::format_currency((float) $tx->plan_price) }}</td>
                        <td>
                            {{ $tx->subscription?->start_at ? \Carbon\Carbon::parse($tx->subscription->start_at)->format('d M Y') : '' }}
                            -
                            {{ $tx->subscription?->end_at ? \Carbon\Carbon::parse($tx->subscription->end_at)->format('d M Y') : '' }}
                        </td>
                        <td>{{ $tx->payment_method ? ucwords(str_replace('_', ' ', $tx->payment_method)) : ($tx->plan_type === 'free_trial' ? translate('messages.Free_Trial') : translate('messages.N/A')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
