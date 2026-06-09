@php
    use App\Services\AppearanceStyleComposer;
    use App\Support\HtmlSanitizer;

    $tableHtml    = (string) ($config['table_html'] ?? '');
    $columnWidths = is_array($config['column_widths'] ?? null) ? $config['column_widths'] : [];
    $headerAlign  = (string) ($config['header_align'] ?? 'center');
    $bodyAlign    = (string) ($config['body_align'] ?? 'middle-left');
    $zebra        = ! empty($config['zebra']);
    $border       = is_array($config['border'] ?? null) ? $config['border'] : [];

    // Concrete-value guard: colours come from the inspector colour picker (hex),
    // but config can be set out-of-band, so fall back to the schema default if
    // the stored value is not a clean hex.
    $hex = fn (?string $v, string $fallback): string =>
        is_string($v) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) ? $v : $fallback;

    $headerBg   = $hex($config['header_bg'] ?? null, '#f1f5f9');
    $headerText = $hex($config['header_text'] ?? null, '#0f172a');
    $bodyBg     = $hex($config['body_bg'] ?? null, '#ffffff');
    $bodyText   = $hex($config['body_text'] ?? null, '#1f2937');
    $zebraBg    = $hex($config['zebra_bg'] ?? null, '#f8fafc');
    $zebraText  = $hex($config['zebra_text'] ?? null, '#1f2937');

    // The 9-point alignment value maps to both axes of cell content:
    // horizontal → text-align, vertical → vertical-align.
    $hAlign = function (string $v): string {
        foreach (['left', 'center', 'right'] as $h) {
            if (str_contains($v, $h)) {
                return $h;
            }
        }
        return 'left';
    };
    $vAlign = function (string $v): string {
        if (str_contains($v, 'top')) {
            return 'top';
        }
        if (str_contains($v, 'bottom')) {
            return 'bottom';
        }
        return 'middle';
    };

    // Interior gridlines share the border's width + colour (no separate swatch).
    $borderWidth = max(0, (int) ($border['width'] ?? 0));
    $borderColor = $hex($border['color'] ?? null, '#cbd5e1');
    $innerH      = ! empty($border['inner_horizontal']);
    $innerV      = ! empty($border['inner_vertical']);

    // Outer box edges via the shared E17 composer (radius is suppressed in the
    // table border control, so none is emitted here).
    $borderProps = AppearanceStyleComposer::composeBorderProps($border);

    // Per-column percentage widths → a trusted, template-generated <colgroup>
    // injected after the (already-sanitised) <table>. Only valid 1–100 ints
    // become a width; anything else is an auto column.
    $cols = '';
    $hasWidth = false;
    foreach ($columnWidths as $w) {
        $wInt = is_numeric($w) ? (int) $w : 0;
        if ($wInt >= 1 && $wInt <= 100) {
            $cols .= '<col style="width:' . $wInt . '%">';
            $hasWidth = true;
        } else {
            $cols .= '<col>';
        }
    }

    $rendered = HtmlSanitizer::sanitize($tableHtml);
    if ($cols !== '' && $rendered !== '') {
        $rendered = preg_replace('/(<table\b[^>]*>)/', '$1<colgroup>' . $cols . '</colgroup>', $rendered, 1) ?? $rendered;
    }

    $wrapStyle = "--np-table-header-bg:{$headerBg};"
        . "--np-table-header-text:{$headerText};"
        . "--np-table-body-bg:{$bodyBg};"
        . "--np-table-body-text:{$bodyText};"
        . "--np-table-border-w:{$borderWidth}px;"
        . "--np-table-border-c:{$borderColor};"
        . "--np-table-header-align:{$hAlign($headerAlign)};"
        . "--np-table-header-valign:{$vAlign($headerAlign)};"
        . "--np-table-body-align:{$hAlign($bodyAlign)};"
        . "--np-table-body-valign:{$vAlign($bodyAlign)};";
    if ($zebra) {
        $wrapStyle .= "--np-table-zebra-bg:{$zebraBg};--np-table-zebra-text:{$zebraText};";
    }

    $classes = ['np-table'];
    if ($zebra)    { $classes[] = 'np-table--zebra'; }
    if ($innerH)   { $classes[] = 'np-table--inner-h'; }
    if ($innerV)   { $classes[] = 'np-table--inner-v'; }
    if ($hasWidth) { $classes[] = 'np-table--fixed'; }
@endphp

<div class="{{ implode(' ', $classes) }}" style="{{ $wrapStyle }}">
    <div class="np-table__scroll"@if (! empty($borderProps)) style="{{ implode(';', $borderProps) }}"@endif>
        @if (trim($rendered) !== '')
            {!! $rendered !!}
        @endif
    </div>
</div>
