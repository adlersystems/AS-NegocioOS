@props(['product'])

@php
    if ($product->isExpired()) {
        $color = 'red';
        $label = __('app.products.expired');
    } elseif ($product->isOutOfStock()) {
        $color = 'red';
        $label = __('app.products.out_of_stock');
    } elseif ($product->isLowStock()) {
        $color = 'yellow';
        $label = __('app.products.low_stock');
    } elseif (! $product->is_active) {
        $color = 'gray';
        $label = __('app.products.inactive');
    } else {
        $color = 'green';
        $label = __('app.products.in_stock');
    }
@endphp

<div class="flex flex-wrap items-center gap-1.5">
    <x-badge :color="$color">{{ $label }}</x-badge>

    @if ($product->is_active && $product->isExpiringSoon() && ! $product->isExpired())
        <x-badge color="info">
            <x-icon name="calendar" class="h-3 w-3" />
            {{ $product->expiration_date->format('d/m/y') }}
        </x-badge>
    @endif
</div>