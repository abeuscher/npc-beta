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
            @if ($heading)
                <h3 class="three-buckets__heading" data-config-key="heading_{{ $i }}" data-config-type="text">{{ $heading }}</h3>
            @endif

            @if ($body)
                <div class="three-buckets__body" data-config-key="body_{{ $i }}" data-config-type="richtext">{!! $body !!}</div>
            @endif

            @if (!empty($ctas))
                @include('widget-shared.buttons', [
                    'buttons'   => $ctas,
                    'alignment' => $buttonAlignment,
                ])
            @endif
        </div>
    @endfor
</div>
