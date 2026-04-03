<div>
    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
        {{ $field['label'] }}
    </label>

    @php
        $fieldPartial = 'livewire.partials.inspector-fields.' . ($field['type'] ?? 'text');
        $fieldExists  = view()->exists($fieldPartial);
    @endphp

    @if ($fieldExists)
        @include($fieldPartial)
    @else
        @include('livewire.partials.inspector-fields.text')
    @endif
</div>
