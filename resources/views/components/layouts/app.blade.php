@props(['title' => 'AS-NegocioOS'])

@php
    $user = auth()->user();

    $routes = config('sidebar.links', []);
    $roleMap = config('sidebar.roles', []);
    $allRoles = ['admin', 'vendedor', 'encargado'];

    $visibleRoutes = array_values(array_filter($routes, function (array $route) use ($user, $roleMap, $allRoles) {
        $roles = $roleMap[$route['name']] ?? $allRoles;

        return in_array($user->role, $roles, true);
    }));

    $companyName = \App\Models\Setting::get('company_name', 'AS-NegocioOS');
    $slogan = \App\Models\Setting::get('tagline') ?: __('app.app_tagline');
    $logoUrl = \App\Models\Setting::logoUrl();

    // Product (maker) brand — hardcoded in config/brand.php, not editable by users.
    $productBrand = config('brand.product', []);
    $productName = $productBrand['name'] ?? 'AS-NegocioOS';
    $productSlogan = __($productBrand['slogan_key'] ?? 'app.app_tagline');
    $productLogo = $productBrand['logo'] ?? null;
    $productLogoUrl = $productLogo && file_exists(public_path($productLogo)) ? asset($productLogo) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ rtrim(url('/'), '/') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/logo-icon.svg') }}">

    <title>{{ $title }} — {{ $companyName }}</title>

    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', stored === 'dark' || (stored === null && prefersDark));
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-sunken text-on-surface antialiased">

    <div x-data="sidebar" class="min-h-screen lg:flex">

        {{-- Mobile overlay --}}
        <div
            x-show="sidebarOpen"
            @click="sidebarOpen = false"
            x-transition.opacity.duration.200ms
            x-cloak
            class="fixed inset-0 z-30 bg-black/60 lg:hidden"
        ></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 flex max-lg:w-64 flex-col border-r border-border bg-surface transition-[width,transform] duration-200 lg:translate-x-0"
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-20' : 'lg:w-64']"
        >
            <div class="flex items-center gap-3 border-b border-border px-5 py-5" :class="collapsed ? 'lg:justify-center' : ''">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="h-10 w-10 shrink-0 rounded-xl border border-border object-contain bg-surface-sunken p-1" :class="collapsed ? 'lg:hidden' : ''">
                @else
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                        <x-icon name="dashboard" class="h-5 w-5" />
                    </div>
                @endif
                <div class="min-w-0" :class="collapsed ? 'lg:hidden' : ''">
                    <p class="truncate text-sm font-bold text-on-surface">{{ $companyName }}</p>
                    <p class="text-xs text-on-surface-muted">{{ $slogan }}</p>
                </div>
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    class="ml-auto rounded-lg p-1.5 text-on-surface-muted hover:bg-surface-sunken lg:hidden"
                >
                    <x-icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach ($visibleRoutes as $route)
                    @php
                        $segments = explode('.', $route['name']);
                        $pattern = count($segments) > 1 ? $segments[0].'.*' : $route['name'];
                        $isActive = request()->routeIs($route['name']) || request()->routeIs($pattern);
                    @endphp
                    <a
                        href="{{ route($route['name']) }}"
                        :title="collapsed ? '{{ __($route['label']) }}' : null"
                        @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                            'bg-primary-soft text-primary' => $isActive,
                            'text-on-surface-muted hover:bg-surface-sunken hover:text-on-surface' => ! $isActive,
                        ])
                        :class="collapsed ? 'lg:justify-center' : ''"
                    >
                        <x-icon :name="$route['icon']" class="h-5 w-5 shrink-0" />
                        <span :class="collapsed ? 'lg:hidden' : ''">{{ __($route['label']) }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- App logout --}}
            {{-- 
            <div class="border-t border-border px-3 py-3">
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface" :class="collapsed ? 'lg:justify-center' : ''" :title="collapsed ? '{{ __('app.logout') }}' : null">
                        <x-icon name="logout" class="h-5 w-5 shrink-0" />
                        <span :class="collapsed ? 'lg:hidden' : ''">{{ __('app.logout') }}</span>
                    </button>
                </form>
            </div>
             --}}

            {{-- Product identity (maker's brand: logo + name + slogan) --}}
            <div class="border-t border-border px-5 py-6">
                <div class="flex items-center gap-3">
                    @if ($productLogoUrl)
                        <img src="{{ $productLogoUrl }}" alt="{{ $productName }}" class="h-9 w-9 shrink-0 rounded-xl border border-border object-contain bg-surface-sunken p-0.5" :class="collapsed ? 'lg:hidden' : ''">
                    @else
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary lg:hidden">
                            <x-icon name="dashboard" class="h-4 w-4" />
                        </div>
                    @endif
                    <div class="min-w-0" :class="collapsed ? 'lg:hidden' : ''">
                        <p class="truncate text-sm font-bold text-on-surface">{{ $productName }}</p>
                        <p class="text-xs text-on-surface-muted">{{ $productSlogan }}</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col transition-[padding-left] duration-200" :class="collapsed ? 'lg:pl-20' : 'lg:pl-64'">

            {{-- Topbar --}}
            <header class="sticky top-0 z-20 border-b border-border bg-surface/90 backdrop-blur">
                <div class="flex items-center gap-2 px-4 py-3 sm:px-6">

                    <button
                        type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface lg:hidden"
                        aria-label="{{ __('app.toggle_menu') }}"
                    >
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>

                    <button
                        type="button"
                        @click="toggleCollapsed()"
                        class="hidden rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface lg:inline-flex"
                        :title="collapsed ? '{{ __('app.expand_sidebar') }}' : '{{ __('app.collapse_sidebar') }}'"
                        aria-label="{{ __('app.collapse_sidebar') }}"
                    >
                        <template x-if="collapsed"><x-icon name="arrow-right-end-on-rectangle" class="h-9 w-9 shrink-0 rounded-xl border border-border object-contain bg-surface-sunken p-1" /></template>
                        <template x-if="!collapsed"><x-icon name="arrow-left-end-on-rectangle" class="h-9 w-9 shrink-0 rounded-xl border border-border object-contain bg-surface-sunken p-1" /></template>
                    </button>

                    <h1 class="truncate text-base font-bold text-on-surface sm:text-lg ml-4">{{ $title }}</h1>

                    <div class="ml-auto flex items-center gap-1.5">

                        {{-- Dark mode toggle --}}
                        <div x-data="themeToggle" class="relative">
                            <button
                                type="button"
                                @click="toggle()"
                                class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                :title="theme === 'dark' ? '{{ __('app.light_mode') }}' : '{{ __('app.dark_mode') }}'"
                            >
                                <template x-if="theme === 'dark'"><x-icon name="sun" class="h-5 w-5" /></template>
                                <template x-if="theme === 'light'"><x-icon name="moon" class="h-5 w-5" /></template>
                            </button>
                        </div>

                        {{-- Language switcher --}}
                        <div
                            x-data="languageSwitcher"
                            class="relative"
                        >
                            <div x-data="{ langOpen: false }">
                                <button
                                    type="button"
                                    @click="langOpen = !langOpen"
                                    class="flex items-center gap-1.5 rounded-lg px-2 py-2 text-xs font-bold uppercase text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                >
                                    <x-icon name="globe" class="h-5 w-5" />
                                    <span class="hidden sm:inline">{{ app()->getLocale() }}</span>
                                </button>

                                <div
                                    x-show="langOpen"
                                    @click.outside="langOpen = false"
                                    x-transition
                                    x-cloak
                                    class="absolute right-0 z-30 mt-2 w-36 overflow-hidden rounded-xl border border-border bg-surface shadow-card-hover"
                                >
                                    <button
                                        type="button"
                                        :disabled="switching"
                                        @click="langOpen = false; setLocale('es')"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-on-surface transition hover:bg-surface-sunken"
                                    >
                                        <span class="text-base">🇪🇸</span> {{ __('app.spanish') }}
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="switching"
                                        @click="langOpen = false; setLocale('en')"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-on-surface transition hover:bg-surface-sunken"
                                    >
                                        <span class="text-base">🇬🇧</span> {{ __('app.english') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- User menu --}}
                        <div class="relative" x-data="{ userMenu: false }" @click.outside="userMenu = false">
                            <button
                                type="button"
                                @click="userMenu = !userMenu"
                                class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-surface-sunken"
                            >
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </span>
                                <span class="hidden min-w-0 text-left sm:block">
                                    <span class="block max-w-32 truncate text-sm font-medium text-on-surface">{{ $user->name }}</span>
                                    <span class="block text-xs text-on-surface-muted">{{ __('app.roles.'.$user->role) }}</span>
                                </span>
                                <x-icon name="chevron-down" class="hidden h-4 w-4 text-on-surface-muted sm:block" />
                            </button>

                            <div
                                x-show="userMenu"
                                x-transition
                                x-cloak
                                class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-xl border border-border bg-surface shadow-card-hover"
                            >
                                <div class="border-b border-border px-4 py-3">
                                    <p class="truncate text-sm font-semibold text-on-surface">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-on-surface-muted">{{ $user->email }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-on-surface transition hover:bg-surface-sunken">
                                        <x-icon name="logout" class="h-4 w-4" />
                                        {{ __('app.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Content --}}
            <main class="flex-1 px-4 py-6 sm:px-6">
                <div class="mx-auto w-full max-w-7xl">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-border px-4 py-4 text-center text-xs text-on-surface-muted sm:px-6">
                © {{ date('Y') }} {{ $companyName }} — <span>AS-NegocioOS</span>
            </footer>
        </div>
    </div>

    <x-toast />
</body>
</html>