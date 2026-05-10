@foreach ($blocks as $block)
    @php
        $inlineStyle      = $block['inline_style'] ?? '';
        $bgFullWidth      = $block['background_full_width'] ?? true;
        $contentFullWidth = $block['content_full_width'] ?? false;
        if (! $bgFullWidth && $contentFullWidth) {
            $bgFullWidth = true;
        }
        // Layout blocks emit their own inner content wrapper inside .page-layout
        // (PageBlockRenderer::renderLayoutBlock). Widget blocks rely on the inner
        // wrap below.
        $isLayoutBlock = ($block['handle'] ?? '') === 'page_layout';
    @endphp
    @if (! $bgFullWidth)
        <div class="site-container">
    @endif
    <div
        class="widget widget--{{ $block['handle'] }}"
        id="widget-{{ $block['instance_id'] }}"
        @if ($inlineStyle) style="{{ $inlineStyle }}" @endif
    >
        @if ($contentFullWidth || $isLayoutBlock)
            {!! $block['html'] !!}
        @else
            <div class="site-container">
                {!! $block['html'] !!}
            </div>
        @endif
    </div>
    @if (! $bgFullWidth)
        </div>
    @endif
@endforeach
