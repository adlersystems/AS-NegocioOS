<x-layouts.app :title="__('app.menu.clients')">

    @php
        $search = request('search');
        $exportQuery = $search ? ['search' => $search] : [];
    @endphp

    <div x-data="{ deleteClient: null }">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.clients') }}</h2>
                <p class="text-sm text-on-surface-muted">{{ __('app.clients.list_desc') }}</p>
            </div>
            <x-button :href="route('clients.create')" icon="plus">{{ __('app.clients.new_client') }}</x-button>
        </div>

        {{-- Toolbar --}}
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" action="{{ route('clients.index') }}" class="relative w-full sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-muted">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('app.clients.search_hint') }}"
                    class="w-full rounded-lg border border-border bg-surface py-2.5 pl-10 pr-4 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30"
                >
                <button type="submit" class="sr-only">{{ __('app.actions.search') }}</button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                @if ($search)
                    <x-button :href="route('clients.index')" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
                @endif
                <x-button :href="route('clients.export.pdf', $exportQuery)" variant="secondary" size="sm" icon="download">{{ __('app.actions.export_pdf') }}</x-button>
                <x-button :href="route('clients.export.excel', $exportQuery)" variant="secondary" size="sm" icon="download">{{ __('app.actions.export_excel') }}</x-button>
            </div>
        </div>

        {{-- Client list --}}
        <x-card class="mt-4">
            @if ($clients->isEmpty())
                @if ($search)
                    <x-empty-state :title="__('app.clients.no_results')" :description="__('app.clients.no_results_desc')" icon="clients" />
                @else
                    <x-empty-state :title="__('app.clients.no_clients')" :description="__('app.clients.no_clients_desc')" icon="clients" />
                @endif
            @else
                <div class="divide-y divide-border">
                    @foreach ($clients as $client)
                        @php
                            $hasDebt = (float) $client->pending_balance > 0;
                            $initials = collect(explode(' ', trim($client->name)))
                                ->filter()
                                ->take(2)
                                ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->join('');
                        @endphp

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-soft font-bold text-primary">
                                {{ $initials }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('clients.show', $client) }}" class="block truncate text-sm font-semibold text-on-surface transition hover:text-primary">
                                    {{ $client->name }}
                                </a>
                                <p class="truncate text-xs text-on-surface-muted">
                                    {{ $client->email ?? '—' }}<span class="mx-1">·</span>{{ $client->nit ?? '—' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-on-surface-muted">
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="sales" class="h-4 w-4" />
                                    {{ number_format($client->sales_count) }} {{ __('app.clients.sales_count') }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="receipt" class="h-4 w-4" />
                                    {{ \App\Models\Setting::formatMoney($client->sales_sum_total ?? 0) }}
                                </span>
                            </div>

                            <x-badge :color="$hasDebt ? 'yellow' : 'green'">
                                {{ __('app.clients.pending_balance') }}: {{ \App\Models\Setting::formatMoney($client->pending_balance) }}
                            </x-badge>

                            <div class="flex items-center gap-1">
                                <a
                                    href="{{ route('clients.show', $client) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    :title="'{{ __('app.actions.view') }}'"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('clients.edit', $client) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    :title="'{{ __('app.actions.edit') }}'"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <button
                                    type="button"
                                    @click="deleteClient = @js(['name' => $client->name, 'url' => route('clients.destroy', $client)])"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-danger-soft hover:text-danger"
                                    :title="'{{ __('app.actions.delete') }}'"
                                >
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Pagination --}}
        @if ($clients->hasPages())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-on-surface-muted">
                    {{ __('app.pagination.showing', ['from' => $clients->firstItem(), 'to' => $clients->lastItem(), 'total' => $clients->total()]) }}
                </p>
                {{ $clients->links() }}
            </div>
        @endif

        {{-- Delete confirmation modal --}}
        <div
            x-show="deleteClient"
            x-transition.opacity.duration.200ms
            x-cloak
            class="fixed inset-0 z-40 bg-black/60"
            @click="deleteClient = null"
        ></div>

        <div
            x-show="deleteClient"
            x-transition.origin.top.scale.90.duration.200ms
            x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
        >
            <div class="w-full max-w-lg rounded-t-2xl border border-border bg-surface shadow-card-hover sm:rounded-2xl">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 class="text-base font-semibold text-on-surface">{{ __('app.clients.confirm_delete_title') }}</h3>
                    <button
                        type="button"
                        @click="deleteClient = null"
                        class="rounded-lg p-1.5 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                    >
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="px-5 py-4">
                    <p class="text-sm text-on-surface-muted">
                        {{ __('app.clients.confirm_delete_desc') }}
                        <span class="font-semibold text-on-surface" x-text="deleteClient && deleteClient.name"></span>.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
                    <x-button variant="secondary" @click="deleteClient = null">{{ __('app.actions.cancel') }}</x-button>

                    <form method="POST" :action="deleteClient ? deleteClient.url : ''">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash">{{ __('app.actions.yes_delete') }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-layouts.app>