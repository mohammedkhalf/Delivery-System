{{-- Customer / user / delivery-man avatar with optional pro-status badge.

Usage:
    @include('partials._user-avatar', [
        'imageUrl'     => $customer->image_full_url,
        'proStatus'    => $customer->pro_status ?? false,
        'size'         => 40,                              // optional, default 40
        'imgClass'     => 'rounded-circle aspect-1-1 object-cover',  // optional
        'wrapperClass' => '',                              // optional extras
        'placeholder'  => asset('...'),                    // optional fallback
        'alt'          => 'Image Description',             // optional
        'badgeSize'    => 14,                              // optional
    ])
--}}
<div class="position-relative flex-shrink-0 {{ $wrapperClass ?? '' }}"
     style="width:{{ $size ?? 40 }}px;height:{{ $size ?? 40 }}px;">
    <img @if(!empty($imgId)) id="{{ $imgId }}" @endif
         class="onerror-image w-100 h-100 {{ $imgClass ?? 'rounded-circle aspect-1-1 object-cover' }}"
         width="{{ $size ?? 40 }}" height="{{ $size ?? 40 }}"
         data-onerror-image="{{ $placeholder ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"
         src="{{ $imageUrl ?? ($placeholder ?? asset('public/assets/admin/img/160x160/img1.jpg')) }}"
         alt="{{ $alt ?? 'Image Description' }}">
    @if (!empty($proStatus) && \App\CentralLogics\Helpers::get_business_settings('pro_member_status') == 1)
        <img width="{{ $badgeSize ?? 14 }}" height="{{ $badgeSize ?? 14 }}"
             src="{{ asset('public/assets/admin/img/subscriber-icon.png') }}"
             alt="Pro" data-toggle="tooltip" title="{{ translate('messages.pro_customer') }}"
             class="rounded-circle position-absolute end-cus-0 top-0">
    @endif
</div>
