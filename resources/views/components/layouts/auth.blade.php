@props(['title' => 'AS-NegocioOS'])

@php
    // The auth page shows the product (maker's) brand, not the customer's.
    $productBrand = config('brand.product', []);
    $productName = $productBrand['name'] ?? 'AS-NegocioOS';
    $productSlogan = __($productBrand['slogan_key'] ?? 'app.app_tagline');
    $logoPath = $productBrand['logo'] ?? null;
    $logoUrl = $logoPath && file_exists(public_path($logoPath)) ? asset($logoPath) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ $productName }}</title>

    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', stored === 'dark' || (stored === null && prefersDark));
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface-sunken text-on-surface antialiased">

    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">

        <div class="mb-6 flex items-center gap-3">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $productName }}" class="h-11 w-11 rounded-xl border border-border object-contain bg-surface-sunken p-1">
            @else
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-white">
                    <x-icon name="dashboard" class="h-6 w-6" />
                </div>
            @endif
            <div>
                <p class="text-lg font-bold text-on-surface">{{ $productName }}</p>
                <p class="text-xs text-on-surface-muted">{{ $productSlogan }}</p>
            </div>
        </div>

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>

        <div class="mt-6 flex items-center gap-1.5 text-xs text-on-surface-muted">
            <div x-data="languageSwitcher" class="flex items-center gap-2">
                <button type="button" class="underline" :disabled="switching" @click="setLocale('es')">Español</button>
                <span>/</span>
                <button type="button" class="underline" :disabled="switching" @click="setLocale('en')">English</button>
            </div>
        </div>
    </div>

    <x-toast />
</body>
</html>