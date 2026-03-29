@foreach ($blocks as $block)
    @php
        $sc = $block['style_config'] ?? [];
        $styleProps = [];
        $paddingKeys = ['padding_top' => 'padding-top', 'padding_right' => 'padding-right', 'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left'];
        $marginKeys  = ['margin_top'  => 'margin-top',  'margin_right'  => 'margin-right',  'margin_bottom'  => 'margin-bottom',  'margin_left'  => 'margin-left'];
        foreach (array_merge($paddingKeys, $marginKeys) as $key => $cssProp) {
            $val = isset($sc[$key]) && $sc[$key] !== '' ? (int) $sc[$key] : null;
            if ($val !== null) {
                $styleProps[] = $cssProp . ':' . $val . 'px';
            }
        }
        $inlineStyle = implode(';', $styleProps);
    @endphp
    <div
        class="widget widget--{{ $block['handle'] }} mb-6"
        id="widget-{{ $block['instance_id'] }}"
        @if ($inlineStyle) style="{{ $inlineStyle }}" @endif
    >
        {!! $block['html'] !!}
    </div>
@endforeach
