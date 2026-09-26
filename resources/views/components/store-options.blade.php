@props([
    'stores' => [],
    'selected' => null,
    'placeholder' => null,
    'includeAll' => false,
    'allLabel' => null,
    'allValue' => 'all',
])

{{--
    Centralized store <option> list. Stamps data-verified on every option so the
    global select2 renderer (verified-select2.js) can show the verified badge.
    Usage:
        <select class="js-select2-custom">
            <x-store-options :stores="$stores" :selected="$id" />
        </select>
--}}

@if (!is_null($placeholder))
    <option value="" disabled {{ in_array((string) $selected, ['', null], true) ? 'selected' : '' }}>{{ $placeholder }}</option>
@endif

@if ($includeAll)
    <option value="{{ $allValue }}" {{ (string) $selected === (string) $allValue ? 'selected' : '' }}>
        {{ $allLabel ?? translate('messages.all') }}
    </option>
@endif

@foreach ($stores as $store)
    <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}"
        {{ (string) $selected === (string) $store->id ? 'selected' : '' }}>
        {{ $store->name }}
    </option>
@endforeach
