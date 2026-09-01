@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])

<div x-data="{ shown: false }" x-ref="{{ $name }}" @keydown.escape.window="shown = false">
    <div
        x-show="shown"
        x-transition.opacity.duration.200ms
        x-cloak
        class="fixed inset-0 z-40 bg-black/60"
        @click="shown = false"
    ></div>

    <div
        x-show="shown"
        x-transition.origin.top.scale.90.duration.200ms
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
    >
        <div class="w-full {{ $maxWidth }} rounded-t-2xl border border-border bg-surface shadow-card-hover sm:rounded-2xl">
            @if ($title)
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 class="text-base font-semibold text-on-surface">{{ $title }}</h3>
                    <button
                        type="button"
                        @click="shown = false"
                        class="rounded-lg p-1.5 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                    >
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>
            @endif

            <div class="px-5 py-4">{{ $slot }}</div>

            @isset($footer)
                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>