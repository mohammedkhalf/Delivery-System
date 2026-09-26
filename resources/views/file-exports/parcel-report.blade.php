<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('Parcel Report') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('filter_criteria') }} -</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('module') }} - {{ $data['module']?translate($data['module']):translate('all') }}
                        <br>
                        {{ translate('zone') }} - {{ $data['zone']??translate('all') }}
                        <br>
                        {{ translate('customer') }} - {{ $data['customer']??translate('all') }}
                        @if ($data['from'])
                            <br>{{ translate('from') }} - {{ Carbon\Carbon::parse($data['from'])->format('d M Y') }}
                        @endif
                        @if ($data['to'])
                            <br>{{ translate('to') }} - {{ Carbon\Carbon::parse($data['to'])->format('d M Y') }}
                        @endif
                        <br>{{ translate('filter') }} - {{ translate($data['filter']) }}
                        <br>{{ translate('Search_Bar_Content') }} - {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.sl') }}</th>
                    <th>{{ translate('messages.order_id') }}</th>
                    <th>{{ translate('messages.customer_name') }}</th>
                    <th>{{ translate('messages.referral_discount') }}</th>
                    <th>{{ translate('messages.Pro_Discount') }}</th>
                    <th>{{ translate('messages.tax') }}</th>
                    <th>{{ translate('messages.delivery_charge') }}</th>
                    <th>{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('messages.additional_charge') }}</th>
                    <th>{{ translate('messages.total_amount') }}</th>
                    <th>{{ translate('messages.amount_received_by') }}</th>
                    <th>{{ translate('messages.payment_method') }}</th>
                    <th>{{ translate('messages.order_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['orders'] as $key => $order)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $order->id }}</td>
                        <td>
                            @if ($order->is_guest)
                                @php($customer_details = json_decode($order['delivery_address'], true))
                                {{ $customer_details['contact_person_name'] ?? translate('messages.Guest_user') }}
                            @elseif ($order->customer)
                                {{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}
                            @else
                                {{ translate('messages.not_found') }}
                            @endif
                        </td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['ref_bonus_amount']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short(\App\CentralLogics\OrderLogic::pro_discount_total($order)) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short(\App\CentralLogics\DeliveryFeeLogic::proDeliveryBreakdown($order)['original_fee']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['additional_charge']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                        <td>{{ isset($order->transaction) ? $order->transaction->received_by : translate('messages.not_received_yet') }}</td>
                        <td>{{ translate(str_replace('_', ' ', $order['payment_method'])) }}</td>
                        <td>{{ translate($order->order_status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
