@foreach ($blocks as $block)
    @php
        $inlineStyle = $block['inline_style'] ?? '';
        $isFullWidth = $block['full_width'] ?? false;
    @endphp
    <div
        class="widget widget--{{ $block['handle'] }}"
        id="widget-{{ $block['instance_id'] }}"
        @if ($inlineStyle) style="{{ $inlineStyle }}" @endif
    >
        @if ($isFullWidth)
            {!! $block['html'] !!}
        @else
            <div class="site-container">
                {!! $block['html'] !!}
            </div>
        @endif
    </div>
@endforeach
