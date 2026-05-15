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
    @if ($hasHeader)
        <div class="pricing-chart__header pricing-chart__header--{{ $headingAlignment }}">
            @if ($eyebrowLabel !== '')
                <p class="pricing-chart__eyebrow-label">{{ $eyebrowLabel }}</p>
            @endif
            @if ($heading !== '')
                <h2 class="pricing-chart__heading">{{ $heading }}</h2>
            @endif
            @if (trim(strip_tags((string) $subheading)) !== '')
                <div class="pricing-chart__subheading">{!! $subheading !!}</div>
            @endif
        </div>
    @endif

    @if ($columnCount > 0)
        <div class="pricing-chart__columns" role="list">
            @foreach ($columns as $col)
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
                    <div class="pricing-chart__column-eyebrow">
                        @if ($colEyebrow !== '')
                            <span>{{ $colEyebrow }}</span>
                        @endif
                    </div>

                    <div class="pricing-chart__column-title">
                        @if ($colTitle !== '')
                            <h3>{{ $colTitle }}</h3>
                        @endif
                    </div>

                    <div class="pricing-chart__column-price">
                        @if (trim(strip_tags((string) $colPrice)) !== '')
                            {!! $colPrice !!}
                        @endif
                    </div>

                    <div class="pricing-chart__column-lead">
                        @if (trim(strip_tags((string) $colLead)) !== '')
                            {!! $colLead !!}
                        @endif
                    </div>

                    @for ($r = 0; $r < $maxAttrRows; $r++)
                        @php $row = $attrRows[$r] ?? null; @endphp
                        @if (is_array($row) && (($row['label'] ?? '') !== '' || trim(strip_tags((string) ($row['value'] ?? ''))) !== ''))
                            <div class="pricing-chart__attribute-row">
                                @if (($row['label'] ?? '') !== '')
                                    <div class="pricing-chart__attribute-label">{{ $row['label'] }}</div>
                                @endif
                                @if (trim(strip_tags((string) ($row['value'] ?? ''))) !== '')
                                    <div class="pricing-chart__attribute-value">{!! $row['value'] !!}</div>
                                @endif
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

    @if (trim(strip_tags((string) $footnote)) !== '')
        <div class="pricing-chart__footnote">{!! $footnote !!}</div>
    @endif
</div>
