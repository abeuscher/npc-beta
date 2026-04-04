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
    x-data="{
        open: false,
        value: @js($selected ?? ''),
        label: @js($selected ? $selectedLabel : $placeholder),
        activeIndex: -1,
        allOptions: @js(collect($options)->map(fn ($o) => ['value' => $o['value'] ?? '', 'label' => $o['label'] ?? $o['value'] ?? ''])->values()->all()),
        searchable: {{ $isSearchable ? 'true' : 'false' }},
        query: '',
        placeholder: @js($placeholder),
        get isEmpty() { return this.value === '' || this.value === null; },
        get filtered() {
            if (!this.searchable || !this.query.trim()) return this.allOptions;
            const q = this.query.toLowerCase();
            return this.allOptions.filter(o => o.label.toLowerCase().includes(q));
        },
        toggle() { this.open ? this.close() : this.openDropdown(); },
        openDropdown() {
            this.open = true;
            this.query = '';
            const opts = this.filtered;
            this.activeIndex = opts.findIndex(o => o.value === this.value);
            if (this.activeIndex < 0) this.activeIndex = 0;
            this.$nextTick(() => {
                if (this.searchable && this.$refs.search) this.$refs.search.focus();
                this.scrollToActive();
            });
        },
        close() { this.open = false; this.activeIndex = -1; this.query = ''; },
        select(index) {
            const opt = this.filtered[index];
            if (!opt) return;
            this.value = opt.value;
            this.label = opt.label;
            this.close();
            this.$refs.nativeSelect.value = opt.value;
            this.$refs.nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            this.$dispatch('custom-select-change', { value: opt.value });
            this.$refs.trigger.focus();
        },
        onKeydown(e) {
            if (!this.open && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                this.openDropdown();
                return;
            }
            if (!this.open) return;
            const opts = this.filtered;
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.activeIndex = Math.min(this.activeIndex + 1, opts.length - 1);
                    this.scrollToActive();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.activeIndex = Math.max(this.activeIndex - 1, 0);
                    this.scrollToActive();
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.select(this.activeIndex);
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    this.$refs.trigger.focus();
                    break;
                case 'Tab':
                    this.close();
                    break;
            }
        },
        onSearchKeydown(e) {
            if (e.key === ' ') return;
            this.onKeydown(e);
        },
        scrollToActive() {
            const list = this.$refs.listbox;
            if (!list) return;
            const items = list.querySelectorAll('[role=option]');
            const el = items[this.activeIndex];
            if (el) el.scrollIntoView({ block: 'nearest' });
        },
        optionId(index) { return '{{ $inputId }}_opt_' + index; }
    }"
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
