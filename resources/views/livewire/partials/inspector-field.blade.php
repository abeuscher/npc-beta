@php
    $hiddenWhen = $field['hidden_when'] ?? null;
    $shownWhen  = $field['shown_when'] ?? null;
    $inGroup    = $inGroup ?? false;
@endphp

<div @if (!$inGroup && $hiddenWhen) x-show="!$wire.block.config.{{ $hiddenWhen }}" @endif @if (!$inGroup && $shownWhen) x-show="$wire.block.config.{{ $shownWhen }}" @endif>
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
