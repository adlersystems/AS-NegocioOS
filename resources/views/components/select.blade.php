@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
])

@php
    $hasError = $errors->has($name);
    $fieldId = $name ?? Str::random(6);
@endphp

<div>
    @if ($label)
        <x-label :for="$fieldId" :required="$required">{{ $label }}</x-label>
    @endif

    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @class([
            'mt-1 w-full rounded-lg border bg-surface px-3 py-2.5 text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30',
            'border-border' => ! $hasError,
            'border-danger' => $hasError,
        ])
        @required($required)
        {{ $attributes }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected(old($name, $selected) == $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        @endif
    </select>

    <x-error :name="$name" />
</div>