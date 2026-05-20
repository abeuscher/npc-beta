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

    // Structure is driven by the count settings. 'auto' (or any non-numeric)
    // fits the number of stored columns/rows — the exact pre-setting
    // behaviour, so existing and unconfigured charts render byte-identically
    // with no migration. An explicit number renders exactly that many slots;
    // lowering it hides the extras while their data stays untouched in
    // config (kept, not pruned) — raising it back brings the content right
    // back. The columns grid still only renders when there is at least one
    // column (>0), so an unconfigured/empty chart renders nothing publicly,
    // exactly as before.
    $cc            = $config['column_count'] ?? 'auto';
    $rc            = $config['attribute_row_count'] ?? 'auto';
    $storedColumns = is_array($columns) ? count($columns) : 0;

    $columnCount = (is_numeric($cc) && (int) $cc >= 1)
        ? min(10, (int) $cc)
        : $storedColumns;

    $derivedMaxRows = 0;
    for ($i = 0; $i < $columnCount; $i++) {
        $rows = $columns[$i]['attribute_rows'] ?? [];
        if (is_array($rows)) {
            $derivedMaxRows = max($derivedMaxRows, count($rows));
        }
    }
    $maxAttrRows = (is_numeric($rc) && (int) $rc >= 0)
        ? min(12, (int) $rc)
        : $derivedMaxRows;

    $emphasizedColumn = (int) ($config['emphasized_column'] ?? 0);

    $hasHeader = $eyebrowLabel !== '' || $heading !== '' || trim(strip_tags((string) $subheading)) !== '';

    $rootStyle = "--pc-columns: {$columnCount}; --pc-attr-rows: {$maxAttrRows};";
    if ($gap !== '') {
        $rootStyle .= ' --pc-gap: ' . e($gap) . ';';
    }
@endphp

<div class="widget-pricing-chart" style="{{ $rootStyle }}">
    @if ($hasHeader || ($inlineEditing ?? false))
        <div class="pricing-chart__header pricing-chart__header--{{ $headingAlignment }}">
            @include('widget-shared.inline-prose', ['tag' => 'p', 'class' => 'pricing-chart__eyebrow-label', 'key' => 'eyebrow_label', 'type' => 'text', 'value' => $eyebrowLabel, 'label' => 'Eyebrow label'])
            @include('widget-shared.inline-prose', ['tag' => 'h2', 'class' => 'pricing-chart__heading', 'key' => 'heading', 'type' => 'text', 'value' => $heading, 'label' => 'Heading'])
            @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__subheading', 'key' => 'subheading', 'type' => 'richtext', 'value' => $subheading, 'label' => 'Subheading'])
        </div>
    @endif

    @if ($columnCount > 0)
        <div class="pricing-chart__columns" role="list">
            @for ($ci = 0; $ci < $columnCount; $ci++)
                @php
                    $col = is_array($columns[$ci] ?? null) ? $columns[$ci] : [];

                    $emphasize  = ! empty($col['emphasize']) || ($emphasizedColumn === $ci + 1);
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
            @endfor
        </div>
    @endif

    @include('widget-shared.inline-prose', ['tag' => 'div', 'class' => 'pricing-chart__footnote', 'key' => 'footnote', 'type' => 'richtext', 'value' => $footnote, 'label' => 'Footnote'])
</div>
