@props(['title' => null, 'description' => null, 'icon' => 'box'])

<div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border-strong bg-surface px-6 py-12 text-center">
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-raised text-on-surface-muted">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    @if ($title)
        <h3 class="text-base font-semibold text-on-surface">{{ $title }}</h3>
    @endif
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-on-surface-muted">{{ $description }}</p>
    @endif
</div>