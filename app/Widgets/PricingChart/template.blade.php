@php
    $eyebrowLabel     = $config['eyebrow_label'] ?? '';
    $heading          = $config['heading'] ?? '';
    $subheading       = $config['subheading'] ?? '';
    $columns          = $config['columns'] ?? [];
    $footnote         = $config['footnote'] ?? '';
    $headingAlignment = $config['heading_alignment'] ?? 'center';
    $gap              = $config['gap'] ?? '';

    if (! is_array($columns)) {
        $columns = [];
    }

    $columnCount = count($columns);
    $maxAttrRows = 0;
    foreach ($columns as $col) {
        $rows = $col['attribute_rows'] ?? [];
        if (is_array($rows)) {
            $maxAttrRows = max($maxAttrRows, count($rows));
        }
    }

    $hasHeader = $eyebrowLabel !== '' || $heading !== '' || trim(strip_tags((string) $subheading)) !== '';

    $rootStyle = "--pc-columns: {$columnCount}; --pc-attr-rows: {$maxAttrRows};";
    if ($gap !== '') {
        $rootStyle .= ' --pc-gap: ' . e($gap) . ';';
    }
@endphp

<div class="widget-pricing-chart" style="{{ $rootStyle }}">
    @if ($hasHeader || ($inlineEditing ?? false))
        <div class="pricing-chart__header pricing-chart__header--{{ $headingAlignment }}">
            @if ($eyebrowLabel !== '')
                <p class="pricing-chart__eyebrow-label">{{ $eyebrowLabel }}</p>
            @endif
            @include('widget-shared.inline-prose', ['tag' => 'h2', 'class' => 'pricing-chart__heading', 'key' => 'heading', 'type' => 'text', 'value' => $heading, 'label' => 'Heading'])
            @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__subheading', 'key' => 'subheading', 'type' => 'richtext', 'value' => $subheading, 'label' => 'Subheading'])
        </div>
    @endif

    @if ($columnCount > 0)
        <div class="pricing-chart__columns" role="list">
            @foreach ($columns as $ci => $col)
                @php
                    $emphasize  = ! empty($col['emphasize']);
                    $colEyebrow = $col['eyebrow'] ?? '';
                    $colTitle   = $col['title'] ?? '';
                    $colPrice   = $col['price'] ?? '';
                    $colLead    = $col['lead_content'] ?? '';
                    $attrRows   = $col['attribute_rows'] ?? [];
                    if (! is_array($attrRows)) { $attrRows = []; }
                    $colCtas    = $col['ctas'] ?? [];
                    if (! is_array($colCtas)) { $colCtas = []; }

                    $cardClasses = ['pricing-chart__column'];
                    if ($emphasize) {
                        $cardClasses[] = 'pricing-chart__column--emphasized';
                    }
                @endphp
                <div class="{{ implode(' ', $cardClasses) }}" role="listitem">
                    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__column-eyebrow', 'key' => "columns.{$ci}.eyebrow", 'type' => 'text', 'value' => $colEyebrow, 'label' => 'Eyebrow', 'always' => true])

                    <div class="pricing-chart__column-title">
                        @include('widget-shared.inline-prose', ['tag' => 'h3', 'class' => '', 'key' => "columns.{$ci}.title", 'type' => 'text', 'value' => $colTitle, 'label' => 'Title'])
                    </div>

                    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__column-price', 'key' => "columns.{$ci}.price", 'type' => 'richtext', 'value' => $colPrice, 'label' => 'Price', 'always' => true])

                    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__column-lead', 'key' => "columns.{$ci}.lead_content", 'type' => 'richtext', 'value' => $colLead, 'label' => 'Lead content', 'always' => true])

                    @for ($r = 0; $r < $maxAttrRows; $r++)
                        @php $row = $attrRows[$r] ?? null; @endphp
                        @if (is_array($row) && (($inlineEditing ?? false) || ($row['label'] ?? '') !== '' || trim(strip_tags((string) ($row['value'] ?? ''))) !== ''))
                            <div class="pricing-chart__attribute-row">
                                @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__attribute-label', 'key' => "columns.{$ci}.attribute_rows.{$r}.label", 'type' => 'text', 'value' => $row['label'] ?? '', 'label' => 'Label'])
                                @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__attribute-value', 'key' => "columns.{$ci}.attribute_rows.{$r}.value", 'type' => 'richtext', 'value' => $row['value'] ?? '', 'label' => 'Value'])
                            </div>
                        @else
                            <div class="pricing-chart__attribute-row pricing-chart__attribute-row--empty" aria-hidden="true"></div>
                        @endif
                    @endfor

                    <div class="pricing-chart__column-ctas">
                        @if (! empty($colCtas))
                            @include('widget-shared.buttons', [
                                'buttons'   => $colCtas,
                                'alignment' => 'left',
                            ])
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__footnote', 'key' => 'footnote', 'type' => 'richtext', 'value' => $footnote, 'label' => 'Footnote'])
</div>
