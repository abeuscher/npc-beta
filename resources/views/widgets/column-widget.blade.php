<div class="widget-columns" style="grid-template-columns:{{ $config['grid_template_columns'] ?? '1fr 1fr' }}">
    @php $numColumns = isset($config['num_columns']) && $config['num_columns'] !== '' ? (int) $config['num_columns'] : 2; @endphp
    @for ($i = 0; $i < $numColumns; $i++)
        <div>
            @foreach ($children[$i] ?? [] as $child)
                @php
                    $sc = $child['style_config'] ?? [];
                    $styleProps = [];
                    $paddingKeys = ['padding_top' => 'padding-top', 'padding_right' => 'padding-right', 'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left'];
                    $marginKeys  = ['margin_top'  => 'margin-top',  'margin_right'  => 'margin-right',  'margin_bottom'  => 'margin-bottom',  'margin_left'  => 'margin-left'];
                    $styleAttr = [];
                    foreach (array_merge($paddingKeys, $marginKeys) as $key => $cssProp) {
                        $val = isset($sc[$key]) && $sc[$key] !== '' ? (int) $sc[$key] : null;
                        if ($val !== null) {
                            $styleAttr[] = $cssProp . ':' . $val . 'px';
                        }
                    }
                    $inlineStyle = implode(';', $styleAttr);
                @endphp
                <div
                    class="widget widget--{{ $child['handle'] }}"
                    id="widget-{{ $child['instance_id'] }}"
                    @if ($inlineStyle) style="{{ $inlineStyle }}" @endif
                >
                    {!! $child['html'] !!}
                </div>
            @endforeach
        </div>
    @endfor
</div>
