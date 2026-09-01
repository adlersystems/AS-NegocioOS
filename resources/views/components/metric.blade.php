@props(['label' => '', 'value' => '', 'icon' => null, 'color' => 'primary', 'hint' => null])

@php
    $colors = [
        'primary' => 'bg-primary-soft text-primary',
        'success' => 'bg-success-soft text-success',
        'danger' => 'bg-danger-soft text-danger',
        'warning' => 'bg-warning-soft text-warning',
        'info' => 'bg-info-soft text-info',
    ];
@endphp

<div class="flex items-center gap-3">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $colors[$color] }}">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <div class="min-w-0">
        <p class="truncate text-xs font-medium text-on-surface-muted">{{ $label }}</p>
        <p class="truncate text-xl font-bold text-on-surface">{{ $value }}</p>
        @if ($hint)
            <p class="truncate text-xs text-on-surface-muted">{{ $hint }}</p>
        @endif
    </div>
</div>