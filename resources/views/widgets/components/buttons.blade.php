@php
    $styleMap = [
        'primary'   => 'btn-primary',
        'secondary' => 'btn-secondary',
        'text'      => 'btn-text',
    ];
    $alignment = $alignment ?? 'justify-start';
@endphp

@if (!empty($buttons))
    <div class="flex flex-wrap gap-3 {{ $alignment }}">
        @foreach ($buttons as $btn)
            @if (!empty($btn['text']))
                <a href="{{ e($btn['url'] ?? '#') }}" class="{{ $styleMap[$btn['style'] ?? 'primary'] ?? 'btn-primary' }}">
                    {{ $btn['text'] }}
                </a>
            @endif
        @endforeach
    </div>
@endif
