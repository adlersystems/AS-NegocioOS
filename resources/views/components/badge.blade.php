@props(['color' => 'gray'])

@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'green' => 'bg-success-soft text-success',
        'red' => 'bg-danger-soft text-danger',
        'yellow' => 'bg-warning-soft text-warning',
        'blue' => 'bg-info-soft text-info',
        'info' => 'bg-info-soft text-info',
        'primary' => 'bg-primary-soft text-primary',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$colors[$color]]) }}>
    {{ $slot }}
</span>