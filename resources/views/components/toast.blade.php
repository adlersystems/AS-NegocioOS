<div
    x-data="toast"
    @if (session('success')) data-flash-success="{{ session('success') }}" @endif
    @if (session('error')) data-flash-error="{{ session('error') }}" @endif
    @if (session('warning')) data-flash-warning="{{ session('warning') }}" @endif
    @if (session('info')) data-flash-info="{{ session('info') }}" @endif
    class="pointer-events-none fixed inset-x-0 top-0 z-[100] flex flex-col items-center gap-2 px-4 pt-4 sm:items-end sm:pr-6"
>
    <template x-for="t in active" :key="t.id">
        <div
            x-show="t.show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="{ 'ring-2': t.leaving }"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-border bg-surface p-4 shadow-card-hover"
        >
            <span
                class="shrink-0"
                :class="{
                    'text-success': t.type === 'success',
                    'text-danger': t.type === 'error',
                    'text-warning': t.type === 'warning',
                    'text-info': t.type === 'info'
                }"
            >
                <template x-if="t.type === 'success'"><x-icon name="check" class="h-5 w-5" /></template>
                <template x-if="t.type === 'error'"><x-icon name="alert" class="h-5 w-5" /></template>
                <template x-if="t.type === 'warning'"><x-icon name="alert" class="h-5 w-5" /></template>
                <template x-if="t.type === 'info'"><x-icon name="info" class="h-5 w-5" /></template>
            </span>

            <p class="flex-1 text-sm font-medium text-on-surface" x-text="t.message"></p>

            <button
                type="button"
                @click="close(t.id)"
                class="shrink-0 rounded-md p-0.5 text-on-surface-muted transition hover:text-on-surface"
            >
                <x-icon name="close" class="h-4 w-4" />
            </button>
        </div>
    </template>
</div>