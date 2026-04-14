@props([
    'name',
    'id' => null,
    'options' => [],
    'placeholder' => '— Select —',
    'required' => false,
    'value' => null,
    'searchable' => false,
    'alpineModel' => null,
])

@php
    $inputId = $id ?? 'cs_' . $name;
    $selected = $value ?? old($name);
    $selectedLabel = $placeholder;
    foreach ($options as $opt) {
        if ((string) ($opt['value'] ?? '') === (string) $selected) {
            $selectedLabel = $opt['label'] ?? $opt['value'];
            break;
        }
    }
    $isSearchable = filter_var($searchable, FILTER_VALIDATE_BOOLEAN);
@endphp

<div
    x-data="customSelect({
        value: @js($selected ?? ''),
        selectedLabel: @js($selectedLabel),
        placeholder: @js($placeholder),
        searchable: {{ $isSearchable ? 'true' : 'false' }},
        options: @js(collect($options)->map(fn ($o) => ['value' => $o['value'] ?? '', 'label' => $o['label'] ?? $o['value'] ?? ''])->values()->all()),
        inputId: @js($inputId),
    })"
    @click.outside="close()"
    @keydown="!searchable && onKeydown($event)"
    class="custom-select"
>
    {{-- Hidden native select for form submission --}}
    <select
        x-ref="nativeSelect"
        name="{{ $name }}"
        id="{{ $inputId }}"
        {{ $required ? 'required' : '' }}
        class="visually-hidden"
        tabindex="-1"
        aria-hidden="true"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $opt)
            <option
                value="{{ $opt['value'] }}"
                {{ (string) $selected === (string) $opt['value'] ? 'selected' : '' }}
            >{{ $opt['label'] ?? $opt['value'] }}</option>
        @endforeach
    </select>

    {{-- Visible trigger button --}}
    <button
        x-ref="trigger"
        type="button"
        class="custom-select__trigger"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        :aria-activedescendant="activeIndex >= 0 ? optionId(activeIndex) : null"
        aria-controls="{{ $inputId }}_listbox"
        @click="toggle()"
    >
        <span x-text="label" :class="isEmpty && 'custom-select__placeholder'"></span>
        <svg class="custom-select__arrow" :class="open && 'is-open'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        class="custom-select__dropdown"
    >
        @if ($isSearchable)
            <div class="custom-select__search-wrap">
                <input
                    x-ref="search"
                    type="text"
                    class="custom-select__search"
                    placeholder="Search…"
                    x-model="query"
                    @keydown="onSearchKeydown($event)"
                    aria-label="Search options"
                    autocomplete="off"
                >
            </div>
        @endif
        <ul
            x-ref="listbox"
            role="listbox"
            id="{{ $inputId }}_listbox"
            class="custom-select__options"
        >
            <template x-for="(opt, i) in filtered" :key="opt.value">
                <li
                    :id="optionId(i)"
                    role="option"
                    :aria-selected="value === opt.value"
                    :class="{
                        'is-active': activeIndex === i,
                        'is-selected': value === opt.value
                    }"
                    class="custom-select__option"
                    @click="select(i)"
                    @mouseenter="activeIndex = i"
                    x-text="opt.label"
                ></li>
            </template>
            <li x-show="filtered.length === 0" class="custom-select__no-results">No results</li>
        </ul>
    </div>
</div>
