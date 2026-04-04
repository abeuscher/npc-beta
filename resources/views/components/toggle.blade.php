@props([
    'name' => null,
    'id' => null,
    'checked' => false,
    'label' => null,
    'alpineChecked' => null,
    'alpineChange' => null,
])

@php
    $inputId = $id ?? ($name ? 'toggle_' . $name : null);
@endphp

<label
    class="toggle {{ $attributes->get('class', '') }}"
    @if ($inputId) for="{{ $inputId }}" @endif
>
    <span class="toggle__track">
        <input
            type="checkbox"
            class="toggle__input visually-hidden"
            role="switch"
            @if ($inputId) id="{{ $inputId }}" @endif
            @if ($name) name="{{ $name }}" value="1" @endif
            @if ($alpineChecked) :checked="{{ $alpineChecked }}" @elseif ($checked) checked @endif
            @if ($alpineChange) @change="{{ $alpineChange }}" @endif
            :aria-checked="$el.checked.toString()"
            {{ $attributes->except(['class']) }}
        >
        <span class="toggle__slider" aria-hidden="true"></span>
    </span>
    @if ($label)
        <span class="toggle__label">{{ $label }}</span>
    @endif
</label>
