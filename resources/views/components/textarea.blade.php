@props([
    'name',
    'label' => null,
    'rows' => 3,
    'required' => false,
    'placeholder' => null,
])

@php
    $hasError = $errors->has($name);
    $fieldId = $name ?? Str::random(6);
@endphp

<div>
    @if ($label)
        <x-label :for="$fieldId" :required="$required">{{ $label }}</x-label>
    @endif

    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @class([
            'mt-1 w-full rounded-lg border bg-surface px-3 py-2.5 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30',
            'border-border' => ! $hasError,
            'border-danger' => $hasError,
        ])
        @required($required)
        {{ $attributes }}
    >{{ old($name, $slot) }}</textarea>

    <x-error :name="$name" />
</div>