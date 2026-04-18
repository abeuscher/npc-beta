@props(['class' => 'h-4 w-4'])

<svg {{ $attributes->merge(['class' => $class . ' animate-spin shrink-0']) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="4" />
    <path fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
</svg>
