<x-layouts.app :title="__('app.audit.detail_title')">

    @php
        $actionLabel = [
            'created' => __('app.audit.action_created'),
            'updated' => __('app.audit.action_updated'),
            'deleted' => __('app.audit.action_deleted'),
        ];
        $actionColor = [
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
        ];
        $changes = collect([]);
        if ($log->isUpdate()) {
            $changes = collect($log->old_values ?? [])
                ->map(fn ($old, $key) => [
                    'key' => $key,
                    'old' => $old,
                    'new' => $log->new_values[$key] ?? null,
                ]);
        } elseif ($log->action === 'created') {
            $changes = collect($log->new_values ?? [])->map(fn ($new, $key) => [
                'key' => $key,
                'old' => null,
                'new' => $new,
            ]);
        } else {
            $changes = collect($log->old_values ?? [])->map(fn ($old, $key) => [
                'key' => $key,
                'old' => $old,
                'new' => null,
            ]);
        }
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.audit.detail_title') }}</h2>
            <p class="text-sm text-on-surface-muted">{{ __('app.audit.detail_desc') }}</p>
        </div>
        <x-button :href="route('audit.index')" variant="secondary" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    </div>

    {{-- Summary card --}}
    <x-card class="mt-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.date') }}</p>
                <p class="mt-1 font-semibold text-on-surface">{{ $log->created_at->format('d/m/Y H:i:s') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.user') }}</p>
                <p class="mt-1 font-semibold text-on-surface">{{ $log->user?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.action') }}</p>
                <p class="mt-1"><x-badge :color="$actionColor[$log->action] ?? 'gray'">{{ $actionLabel[$log->action] ?? $log->action }}</x-badge></p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.model') }}</p>
                <p class="mt-1 font-semibold text-on-surface">{{ $log->modelShortName() }} <span class="text-on-surface-muted">#{{ $log->auditable_id }}</span></p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.record') }}</p>
                <p class="mt-1 font-semibold text-on-surface">{{ $log->contextLabel() }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.ip') }}</p>
                <p class="mt-1 text-on-surface">{{ $log->ip_address ?? '—' }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-muted">{{ __('app.labels.user_agent') }}</p>
                <p class="mt-1 break-words text-sm text-on-surface-muted">{{ $log->user_agent ?? '—' }}</p>
            </div>
        </div>
    </x-card>

    {{-- Changes --}}
    <x-card class="mt-4">
        <h3 class="text-base font-bold text-on-surface">{{ __('app.audit.changes') }}</h3>

        @if ($changes->isEmpty())
            <p class="mt-3 text-sm text-on-surface-muted">{{ __('app.audit.no_changes') }}</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                            <th class="py-2 pr-4">{{ __('app.labels.attribute') }}</th>
                            <th class="py-2 pr-4">{{ __('app.audit.old_value') }}</th>
                            <th class="py-2">{{ __('app.audit.new_value') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($changes as $change)
                            <tr>
                                <td class="py-3 pr-4">
                                    <span class="font-mono text-xs text-on-surface-muted">{{ $change['key'] }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="font-mono text-xs text-on-surface">{{ $change['old'] !== null ? json_encode($change['old'], JSON_UNESCAPED_UNICODE) : '—' }}</span>
                                </td>
                                <td class="py-3">
                                    <span class="font-mono text-xs font-semibold text-on-surface">{{ $change['new'] !== null ? json_encode($change['new'], JSON_UNESCAPED_UNICODE) : '—' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

</x-layouts.app>