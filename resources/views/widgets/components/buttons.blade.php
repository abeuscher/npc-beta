@php
    $alignment = $alignment ?? 'left';
@endphp

@if (!empty($buttons))
    <div class="btn-group btn-group--{{ $alignment }}">
        @foreach ($buttons as $btn)
            @if (!empty($btn['text']))
                <a href="{{ e($btn['url'] ?? '#') }}" class="btn btn--{{ $btn['style'] ?? 'primary' }}">
                    {{ $btn['text'] }}
                </a>
            @endif
        @endforeach
    </div>
@endif
