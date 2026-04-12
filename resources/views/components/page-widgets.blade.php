@foreach ($blocks as $block)
    @php
        $ac = $block['appearance_config'] ?? [];
        $styleProps = [];

        $bgColor = $ac['background']['color'] ?? null;
        if (! empty($bgColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bgColor)) {
            $styleProps[] = 'background-color:' . $bgColor;
        }
        $textColor = $ac['text']['color'] ?? null;
        if (! empty($textColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $textColor)) {
            $styleProps[] = 'color:' . $textColor;
        }

        $padding = $ac['layout']['padding'] ?? [];
        $margin  = $ac['layout']['margin'] ?? [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
            }
        }

        $inlineStyle = implode(';', $styleProps);
        // Per-instance override in appearance_config takes precedence over widget type default
        $instanceFullWidth = $ac['layout']['full_width'] ?? null;
        $isFullWidth = $instanceFullWidth !== null ? (bool) $instanceFullWidth : ($block['full_width'] ?? false);
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
