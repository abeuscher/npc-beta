@props([
    'name',
    'options' => [],
    'required' => false,
    'value' => null,
    'layout' => 'inline',
])

@php
    $selected = $value ?? old($name);

    // Single-quoted literals rather than Blade's @js(), which compiles to
    // JSON.parse(…) — the public site's CSP-safe Alpine build cannot reach the
    // JSON global from a template expression (session 375).
    $jsString = fn ($v) => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'";
@endphp

<div
    x-data="{ value: {{ $jsString($selected ?? '') }} }"
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
            :aria-checked="value === {{ $jsString($optValue) }} ? 'true' : 'false'"
            class="radio-group__option"
            :class="value === {{ $jsString($optValue) }} && 'is-selected'"
            @click="value = {{ $jsString($optValue) }}"
            id="{{ $optId }}"
        >{{ $optLabel }}</button>
    @endforeach
</div>
