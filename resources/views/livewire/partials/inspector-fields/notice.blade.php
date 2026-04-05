@php
    $variant = $field['variant'] ?? 'info';
    $content = $field['content'] ?? '';
@endphp

<div class="rounded-lg border-l-4 px-3 py-2.5 text-xs leading-relaxed
    {{ $variant === 'warning'
        ? 'border-amber-400 bg-amber-50 text-amber-800 dark:border-amber-500 dark:bg-amber-900/20 dark:text-amber-200'
        : 'border-blue-400 bg-blue-50 text-blue-800 dark:border-blue-500 dark:bg-blue-900/20 dark:text-blue-200' }}">
    <span class="mr-1">
        @if ($variant === 'warning')
            <svg xmlns="http://www.w3.org/2000/svg" class="inline h-3.5 w-3.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" class="inline h-3.5 w-3.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @endif
    </span>
    <span class="[&_a]:underline [&_a]:font-medium">{!! $content !!}</span>
</div>
