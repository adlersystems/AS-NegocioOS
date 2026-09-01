@props(['variant' => 'primary', 'type' => 'button', 'size' => 'md', 'href' => null, 'icon' => null, 'full' => false])

@php
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover shadow-sm',
        'secondary' => 'border border-border bg-surface-raised text-on-surface hover:bg-surface-sunken',
        'danger' => 'bg-danger text-white hover:opacity-90 shadow-sm',
        'success' => 'bg-success text-white hover:opacity-90 shadow-sm',
        'ghost' => 'text-on-surface-muted hover:bg-surface-sunken hover:text-on-surface',
    ];

    $sizes = [
        'xs' => 'px-2 py-1 text-xs',
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-3 text-sm sm:text-base',
    ];

    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60 '.
        $sizes[$size].' '.$variants[$variant].($full ? ' w-full' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base]) }}>
        @if ($icon) <x-icon :name="$icon" class="h-4 w-4 shrink-0" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base]) }}>
        @if ($icon) <x-icon :name="$icon" class="h-4 w-4 shrink-0" /> @endif
        {{ $slot }}
    </button>
@endif