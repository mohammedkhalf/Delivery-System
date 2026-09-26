<?php
    $proRowOrder     = $order ?? null;
    $proRowBreakdown = $proRowOrder ? \App\CentralLogics\DeliveryFeeLogic::proDeliveryBreakdown($proRowOrder) : null;
    $proRowLayout    = $layout ?? 'dl';
?>

@if ($proRowBreakdown && $proRowBreakdown['has_reduction'])
    <?php $proRowAmount = $proRowBreakdown['reduction']; ?>
    @if ($proRowLayout === 'tr3')
        <tr>
            <td style="width: 40%"></td>
            <td class="p-1 px-3">{{ translate('messages.Pro_Discount') }}</td>
            <td class="text-right p-1 px-3">
                - {{ \App\CentralLogics\Helpers::format_currency($proRowAmount) }}
            </td>
        </tr>
    @elseif ($proRowLayout === 'tr_parcel')
        <tr>
            <td>{{ translate('messages.Pro_Discount') }}</td>
            <td class="text-center"></td>
            <td>- {{ \App\CentralLogics\Helpers::format_currency($proRowAmount) }}</td>
        </tr>
    @elseif ($proRowLayout === 'tr')
        <tr>
            <td>{{ translate('messages.Pro_Discount') }}</td>
            <td class="text-right">- {{ \App\CentralLogics\Helpers::format_currency($proRowAmount) }}</td>
        </tr>
    @else
        <dt class="col-6 {{ $dtClass ?? '' }}">{{ translate('messages.Pro_Discount') }} :</dt>
        <dd class="col-6 {{ $ddClass ?? 'text-right' }}">
            - {{ \App\CentralLogics\Helpers::format_currency($proRowAmount) }}
        </dd>
    @endif
@endif
