@props([
    'name',
    'options' => [],
    'required' => false,
    'value' => null,
    'layout' => 'inline',
])

@php
    $selected = $value ?? old($name);
@endphp

<div
    x-data="{ value: @js($selected ?? '') }"
    class="radio-group radio-group--{{ $layout }}"
    role="radiogroup"
>
    <input type="hidden" name="{{ $name }}" :value="value" {{ $required ? 'required' : '' }}>

    @foreach ($options as $i => $opt)
        @php
            $optValue = $opt['value'] ?? '';
            $optLabel = $opt['label'] ?? $optValue;
            $optId = $name . '_' . $i;
        @endphp
        <button
            type="button"
            role="radio"
            :aria-checked="value === @js($optValue) ? 'true' : 'false'"
            class="radio-group__option"
            :class="value === @js($optValue) && 'is-selected'"
            @click="value = @js($optValue)"
            id="{{ $optId }}"
        >{{ $optLabel }}</button>
    @endforeach
</div>
