@php
    $toastMessages = [
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ];
@endphp

<div class="as-toast-region pointer-events-none fixed inset-x-0 top-0 z-[100]" aria-live="polite"></div>

<script type="application/json" data-toast-initial>@json($toastMessages)</script>
