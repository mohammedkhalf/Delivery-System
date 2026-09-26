@php
    $deliveryTypeForBadge = $order->delivery_type ?? null;
    if (!\in_array($deliveryTypeForBadge, ['express', 'slightly_delay'], true)) {
        $deliveryTypeForBadge = null;
    }
    $deliveryTypeBadge = [
        'express'        => ['class' => 'badge-soft-warning',   'label' => 'messages.express'],
        'slightly_delay' => ['class' => 'badge-soft-secondary', 'label' => 'messages.slightly_delay'],
    ];
@endphp
@if ($deliveryTypeForBadge)
    <span class="badge {{ $deliveryTypeBadge[$deliveryTypeForBadge]['class'] }} text-capitalize">
        {{ translate($deliveryTypeBadge[$deliveryTypeForBadge]['label']) }}
    </span>
@endif
