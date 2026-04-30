@php
    $verticalAlign = in_array($config['vertical_align'] ?? 'middle', ['top', 'middle', 'bottom'], true)
        ? $config['vertical_align']
        : 'middle';
@endphp
<div class="widget-text-block widget-text-block--vertical-{{ $verticalAlign }}" data-config-key="content" data-config-type="richtext">{!! $config['content'] ?? '' !!}</div>
