@php
    $headingAlignment = $config['heading_alignment'] ?? 'left';
    $bodyAlignment = $config['body_alignment'] ?? 'left';
    $buttonAlignment = $config['button_alignment'] ?? 'left';
    $gap = $config['gap'] ?? '';

    $classes = [
        'widget-three-buckets',
        'three-buckets--headings-' . $headingAlignment,
        'three-buckets--body-' . $bodyAlignment,
    ];
@endphp

<div class="{{ implode(' ', $classes) }}" @if ($gap) style="--bucket-gap: {{ e($gap) }};" @endif>
    @for ($i = 1; $i <= 3; $i++)
        @php
            $heading = $config["heading_{$i}"] ?? '';
            $body = $config["body_{$i}"] ?? '';
            $ctas = $config["ctas_{$i}"] ?? [];
        @endphp

        <div class="three-buckets__bucket">
            @include('widget-shared.inline-prose', ['tag' => 'h3', 'class' => 'three-buckets__heading', 'key' => "heading_{$i}", 'type' => 'text', 'value' => $heading, 'label' => "Heading {$i}"])

            @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'three-buckets__body', 'key' => "body_{$i}", 'type' => 'richtext', 'value' => $body, 'label' => "Body {$i}"])

            @if (!empty($ctas))
                @include('widget-shared.buttons', [
                    'buttons'   => $ctas,
                    'alignment' => $buttonAlignment,
                ])
            @endif
        </div>
    @endfor
</div>
