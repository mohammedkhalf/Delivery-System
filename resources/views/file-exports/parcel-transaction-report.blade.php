<div class="row">
    <div class="col-lg-12 text-center ">
        <h1>{{ translate('parcel_transactions_report') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Search_Criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('module')}} - {{ $data['module'] ? translate($data['module']) : translate('all') }}
                        <br>
                        {{ translate('zone')}} - {{ $data['zone'] ?? translate('all') }}
                        @if ($data['from'])
                            <br>
                            {{ translate('from')}} -
                            {{ $data['from'] ? Carbon\Carbon::parse($data['from'])->format('d M Y') : '' }}
                        @endif
                        @if ($data['to'])
                            <br>
                            {{ translate('to')}} - {{ $data['to'] ? Carbon\Carbon::parse($data['to'])->format('d M Y') : '' }}
                        @endif
                        <br>
                        {{ translate('filter')  }}- {{  translate($data['filter']) }}
                        <br>
                        {{ translate('Search_Bar_Content')  }}- {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Transaction_Analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Completed_Transactions')  }}- {{ $data['delivered'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Refunded_Transactions')  }}- {{ $data['canceled'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Earning_Analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Admin_Earnings')  }} - {{ $data['admin_earned'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Delivery_Man_Earnings')  }} - {{ $data['deliveryman_earned'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('sl') }}</th>
                    <th>{{ translate('messages.order_id') }}</th>
                    <th>{{ translate('messages.customer_name') }}</th>
                    <th>{{ translate('messages.referral_discount') }}</th>
                    <th>{{ translate('messages.vat/tax') }}</th>
                    <th>{{ translate('messages.delivery_charge') }}</th>
                    <th>{{ translate('messages.order_amount') }}</th>
                    <th>{{ translate('messages.admin_discount') }}</th>
                    <th>{{ translate('messages.admin_commission') }}</th>
                    <th>{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('messages.additional_charge') }}</th>
                    <th>{{ translate('commision_on_delivery_charge') }}</th>
                    <th>{{ translate('admin_net_income') }}</th>
                    <th>{{ translate('messages.amount_received_by') }}</th>
                    <th>{{ translate('messages.payment_method') }}</th>
                    <th>{{ translate('messages.payment_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['order_transactions'] as $key => $ot)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $ot->order_id }}</td>
                        <td>
                            @php($delivery_address = $ot->order ? (is_array($ot->order->delivery_address) ? $ot->order->delivery_address : json_decode($ot->order->delivery_address, true)) : null)
                            @if ($ot->order && $ot->order->customer)
                                {{ $ot->order->customer['f_name'] . ' ' . $ot->order->customer['l_name'] }}
                            @elseif (!empty($delivery_address['contact_person_name']))
                                {{ $delivery_address['contact_person_name'] }}
                            @else
                                {{ translate('messages.not_found') }}
                            @endif
                        </td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order['ref_bonus_amount']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->tax) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_charge + ($ot->pro_delivery_discount ?? 0)) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order_amount) }}</td>
                        {{-- admin_discount --}}
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->admin_expense) }}</td>
                        {{-- admin_commission --}}
                        <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->admin_commission + $ot->admin_expense) - $ot->delivery_fee_comission - $ot->additional_charge) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->additional_charge)) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_fee_comission) }}</td>
                        {{-- admin_net_income --}}
                        <td>{{ \App\CentralLogics\Helpers::format_currency(\App\CentralLogics\OrderLogic::admin_net_income($ot)) }}</td>
                        @if ($ot->received_by == 'admin')
                            <td>{{ translate('messages.admin') }}</td>
                        @elseif ($ot->received_by == 'deliveryman')
                            <td>
                                <div>{{ translate('messages.delivery_man') }}</div>
                                <div>
                                    @if (isset($ot->delivery_man) && $ot->delivery_man->earning == 1)
                                        {{ translate('messages.freelance') }}
                                    @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'restaurant_wise')
                                        {{ translate('messages.restaurant') }}
                                    @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'zone_wise')
                                        {{ translate('messages.admin') }}
                                    @endif
                                </div>
                            </td>
                        @elseif ($ot->received_by == 'store')
                            <td>{{ translate('messages.store') }}</td>
                        @else
                            <td></td>
                        @endif
                        <td>{{ translate(str_replace('_', ' ', $ot->order['payment_method'])) }}</td>
                        <td>
                            @if ($ot->status)
                                {{ translate('messages.refunded') }}
                            @else
                                {{ translate('messages.completed') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
