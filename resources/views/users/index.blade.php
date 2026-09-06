<x-layouts.app :title="__('app.menu.users')">

    @php
        $search = request('search');
    @endphp

    <div x-data="{ deleteUser: null }">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.users') }}</h2>
                <p class="text-sm text-on-surface-muted">{{ __('app.users.list_desc') }}</p>
            </div>
            <x-button :href="route('users.create')" icon="plus">{{ __('app.users.new_user') }}</x-button>
        </div>

        <div class="divider">&nbsp;</div>

        {{-- Toolbar --}}
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" action="{{ route('users.index') }}" class="relative w-full sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-muted">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('app.users.search_hint') }}"
                    class="w-full rounded-lg border border-border bg-surface py-2.5 pl-10 pr-4 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30"
                >
                <button type="submit" class="sr-only">{{ __('app.actions.search') }}</button>
            </form>

            @if ($search)
                <x-button :href="route('users.index')" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
            @endif
        </div>

        <div class="divider">&nbsp;</div>

        {{-- User list --}}
        <x-card class="mt-4">
            @if ($users->isEmpty())
                @if ($search)
                    <x-empty-state :title="__('app.users.no_results')" :description="__('app.users.no_results_desc')" icon="users" />
                @else
                    <x-empty-state :title="__('app.users.no_users')" :description="__('app.users.no_users_desc')" icon="users" />
                @endif
            @else
                <div class="divide-y divide-border">
                    @foreach ($users as $user)
                        @php
                            $isSelf = auth()->id() === $user->id;
                            $initials = collect(explode(' ', trim($user->name)))
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
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-on-surface">{{ $user->name }}</span>
                                    @if ($isSelf)
                                        <x-badge color="primary">{{ __('app.users.you') }}</x-badge>
                                    @endif
                                </div>
                                <p class="truncate text-xs text-on-surface-muted">{{ $user->email }}</p>
                            </div>

                            <x-badge :color="$user->role === 'admin' ? 'primary' : ($user->role === 'encargado' ? 'blue' : 'gray')">
                                {{ __('app.roles.' . $user->role) }}
                            </x-badge>

                            <div class="flex items-center gap-1">
                                <a
                                    href="{{ route('users.edit', $user) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    :title="'{{ __('app.actions.edit') }}'"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                @unless ($isSelf)
                                    <button
                                        type="button"
                                        @click="deleteUser = @js(['name' => $user->name, 'url' => route('users.destroy', $user)])"
                                        class="rounded-lg p-2 text-on-surface-muted transition hover:bg-danger-soft hover:text-danger"
                                        :title="'{{ __('app.actions.delete') }}'"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Pagination --}}
        @if ($users->hasPages())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-on-surface-muted">
                    {{ __('app.pagination.showing', ['from' => $users->firstItem(), 'to' => $users->lastItem(), 'total' => $users->total()]) }}
                </p>
                {{ $users->links() }}
            </div>
        @endif

        {{-- Delete confirmation modal --}}
        <div
            x-show="deleteUser"
            x-transition.opacity.duration.200ms
            x-cloak
            class="fixed inset-0 z-40 bg-black/60"
            @click="deleteUser = null"
        ></div>

        <div
            x-show="deleteUser"
            x-transition.origin.top.scale.90.duration.200ms
            x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
        >
            <div class="w-full max-w-lg rounded-t-2xl border border-border bg-surface shadow-card-hover sm:rounded-2xl">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 class="text-base font-semibold text-on-surface">{{ __('app.users.confirm_delete_title') }}</h3>
                    <button
                        type="button"
                        @click="deleteUser = null"
                        class="rounded-lg p-1.5 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                    >
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="px-5 py-4">
                    <p class="text-sm text-on-surface-muted">
                        {{ __('app.users.confirm_delete_desc') }}
                        <span class="font-semibold text-on-surface" x-text="deleteUser && deleteUser.name"></span>.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
                    <x-button variant="secondary" @click="deleteUser = null">{{ __('app.actions.cancel') }}</x-button>

                    <form method="POST" :action="deleteUser ? deleteUser.url : ''">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash">{{ __('app.actions.yes_delete') }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-layouts.app>
