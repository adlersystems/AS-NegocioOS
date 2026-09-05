<x-layouts.app :title="__('app.menu.audit')">

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
        $hasFilters = collect($filters)->contains(fn ($value) => filled($value));
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.audit') }}</h2>
            <p class="text-sm text-on-surface-muted">{{ __('app.audit.list_desc') }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <x-card class="mt-6">
        <form method="GET" action="{{ route('audit.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-select
                name="model"
                :selected="$filters['model']"
                :options="['' => __('app.audit.all_models')] + collect($models)->mapWithKeys(fn ($key) => [$key => __('app.models.'.$key)])->all()"
            />

            <x-select
                name="action"
                :selected="$filters['action']"
                :options="['' => __('app.audit.all_actions')] + collect($actions)->mapWithKeys(fn ($action) => [$action => $actionLabel[$action] ?? $action])->all()"
            />

            <x-select
                name="user_id"
                :selected="$filters['user_id']"
                :options="['' => __('app.audit.all_users')] + $users->pluck('name', 'id')->all()"
            />

            <div class="grid grid-cols-2 gap-3">
                <x-input type="date" name="from" :label="__('app.sales.from_date')" :value="$filters['from']" />
                <x-input type="date" name="to" :label="__('app.sales.to_date')" :value="$filters['to']" />
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                <x-button type="submit" size="sm" icon="filter">{{ __('app.actions.apply') }}</x-button>
                @if ($hasFilters)
                    <x-button :href="route('audit.index')" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
                @endif
            </div>
        </form>
    </x-card>

    <div class="divider">&nbsp;</div>

    {{-- Logs --}}
    <x-card class="mt-4">
        @if ($logs->isEmpty())
            <x-empty-state :title="__('app.audit.no_logs')" :description="__('app.audit.no_logs_desc')" icon="audit" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                            <th class="py-2 pr-4">{{ __('app.labels.date') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.user') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.action') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.model') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.record') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.ip') }}</th>
                            <th class="py-2 text-right">{{ __('app.labels.details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap py-3 pr-4 text-on-surface-muted">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-3 pr-4 text-on-surface">{{ $log->user?->name ?? '—' }}</td>
                                <td class="py-3 pr-4">
                                    <x-badge :color="$actionColor[$log->action] ?? 'gray'">
                                        {{ $actionLabel[$log->action] ?? $log->action }}
                                    </x-badge>
                                </td>
                                <td class="py-3 pr-4 text-on-surface-muted">{{ $log->modelShortName() }}</td>
                                <td class="py-3 pr-4">
                                    @php
                                        $auditable = $log->auditable;
                                        $recordHref = match (true) {
                                            $auditable instanceof \App\Models\Client && $auditable->exists => route('clients.show', $auditable),
                                            $auditable instanceof \App\Models\Product && $auditable->exists => route('products.show', $auditable),
                                            $auditable instanceof \App\Models\Sale && $auditable->exists => route('sales.show', $auditable),
                                            default => null,
                                        };
                                    @endphp
                                    @if ($recordHref)
                                        <a href="{{ $recordHref }}" class="font-semibold text-on-surface transition hover:text-primary">{{ $log->contextLabel() }}</a>
                                    @else
                                        <span class="text-on-surface">{{ $log->contextLabel() }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-on-surface-muted">{{ $log->ip_address ?? '—' }}</td>
                                <td class="py-3 text-right">
                                    <x-button :href="route('audit.show', $log)" variant="ghost" size="sm" icon="eye">{{ __('app.actions.view') }}</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Pagination --}}
    @if ($logs->hasPages())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-on-surface-muted">
                {{ __('app.pagination.showing', ['from' => $logs->firstItem(), 'to' => $logs->lastItem(), 'total' => $logs->total()]) }}
            </p>
            {{ $logs->links() }}
        </div>
    @endif

</x-layouts.app>