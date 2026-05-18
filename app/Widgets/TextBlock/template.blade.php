@php
    $verticalAlign = in_array($config['vertical_align'] ?? 'middle', ['top', 'middle', 'bottom'], true)
        ? $config['vertical_align']
        : 'middle';

    $content = $config['content'] ?? '';
    $ctas    = $config['ctas'] ?? [];

    $ctaAlignment = $config['cta_alignment'] ?? 'inherit';
    if (! in_array($ctaAlignment, ['inherit', 'left', 'center', 'right'], true)) {
        $ctaAlignment = 'inherit';
    }
    if ($ctaAlignment === 'inherit') {
        if (is_string($content) && str_contains($content, 'ql-align-center')) {
            $ctaAlignment = 'center';
        } elseif (is_string($content) && str_contains($content, 'ql-align-right')) {
            $ctaAlignment = 'right';
        } else {
            $ctaAlignment = 'left';
        }
    }
@endphp
<div class="widget-text-block widget-text-block--vertical-{{ $verticalAlign }}">
    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'widget-text-block__content', 'key' => 'content', 'type' => 'richtext', 'value' => $content, 'label' => 'Content', 'always' => true])

    @if (!empty($ctas))
        <div class="widget-text-block__ctas">
            @include('widget-shared.buttons', [
                'buttons'   => $ctas,
                'alignment' => $ctaAlignment,
            ])
        </div>
    @endif
</div>
