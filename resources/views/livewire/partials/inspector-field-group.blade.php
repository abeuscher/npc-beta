{{-- Renders a group of config fields with layout grouping and an optional advanced accordion. --}}
@php
    $primaryFields  = $primaryFields ?? [];
    $advancedFields = $advancedFields ?? [];
    $emptyMessage   = $emptyMessage ?? null;
@endphp

@if (count($primaryFields) > 0 || count($advancedFields) > 0)
    @php
        $__groupOpen = null;
        $__semanticGroups = ['content', 'appearance'];
    @endphp
    @foreach ($primaryFields as $field)
        @php $__fieldGroup = $field['group'] ?? null; @endphp
        @php $__isLayoutGroup = $__fieldGroup && ! in_array($__fieldGroup, $__semanticGroups); @endphp
        @if ($__groupOpen && ($__isLayoutGroup ? $__groupOpen !== $__fieldGroup : true))
            </div>
            @php $__groupOpen = null; @endphp
        @endif
        @if ($__isLayoutGroup && $__groupOpen !== $__fieldGroup)
            @php
                $__shownWhen = $field['shown_when'] ?? null;
            @endphp
            <div class="grid grid-cols-2 gap-3" @if ($__shownWhen) x-show="$wire.block.config.{{ $__shownWhen }}" @endif>
            @php $__groupOpen = $__fieldGroup; @endphp
        @endif
        @include('livewire.partials.inspector-field', ['field' => $field, 'inGroup' => (bool) $__groupOpen])
    @endforeach
    @if ($__groupOpen)
        </div>
    @endif

    @if (count($advancedFields) > 0)
        <div x-data="{ cfgAdvOpen: false }">
            <button
                type="button"
                x-on:click="cfgAdvOpen = !cfgAdvOpen"
                class="flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-3.5 w-3.5 transition-transform duration-150"
                    x-bind:class="{ 'rotate-90': cfgAdvOpen }"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                Carousel Settings
            </button>

            <div x-show="cfgAdvOpen" x-cloak class="mt-3 space-y-4">
                @foreach ($advancedFields as $field)
                    @include('livewire.partials.inspector-field', ['field' => $field])
                @endforeach
            </div>
        </div>
    @endif
@elseif ($emptyMessage)
    <p class="text-sm text-gray-400 dark:text-gray-500 italic">{{ $emptyMessage }}</p>
@endif
