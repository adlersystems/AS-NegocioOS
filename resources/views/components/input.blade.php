@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'step' => null,
    'min' => null,
    'max' => null,
    'prepend' => null,
])

@php
    $hasError = $errors->has($name);
    $isPassword = $type === 'password';
    $fieldId = $name ?? Str::random(6);
@endphp

<div>
    @if ($label)
        <x-label :for="$fieldId" :required="$required">{{ $label }}</x-label>
    @endif

    <div class="relative mt-1">
        @if ($prepend)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-on-surface-muted">{{ $prepend }}</span>
        @endif

        <input
            id="{{ $fieldId }}"
            type="{{ $type }}"
            name="{{ $name }}"
            @unless ($isPassword) value="{{ old($name, $value) }}" @endunless
            placeholder="{{ $placeholder }}"
            step="{{ $step }}"
            min="{{ $min }}"
            max="{{ $max }}"
            @class([
                'w-full rounded-lg border bg-surface px-3 py-2.5 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30',
                'border-border' => ! $hasError,
                'border-danger' => $hasError,
                'pl-9' => $prepend,
            ])
            @required($required)
            {{ $attributes }}
        />
    </div>

    <x-error :name="$name" />
</div>