@props(['title' => null, 'description' => null])

@php
    $hasHeader = isset($header) && $header->isNotEmpty();
@endphp

<div class="rounded-xl border border-border bg-surface shadow-card">
    @if ($title || $hasHeader)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-4 sm:px-6">
            <div>
                <h3 class="text-base font-semibold text-on-surface">{{ $title }}</h3>
                @if ($description)
                    <p class="mt-0.5 text-sm text-on-surface-muted">{{ $description }}</p>
                @endif
            </div>
            @if ($hasHeader)
                <div>{{ $header }}</div>
            @endif
        </div>
    @endif

    <div class="px-4 py-4 sm:px-6">{{ $slot }}</div>

    @isset($footer)
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-4 py-3 sm:px-6">{{ $footer }}</div>
    @endisset
</div>