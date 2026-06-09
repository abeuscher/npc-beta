<?php

namespace App\Widgets\Table;

use App\Widgets\Contracts\WidgetDefinition;

class TableDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'table';
    }

    public function label(): string
    {
        return 'Table';
    }

    public function description(): string
    {
        return 'A real WYSIWYG table — merge cells, header rows, and paste from Word, Google Docs, or the web. Stored as clean HTML.';
    }

    public function category(): array
    {
        return ['content'];
    }

    /**
     * The table is authored in the embedded editor surface (a ProseMirror
     * instance opened from the inspector), not cell-by-cell on the canvas.
     * The canvas shows a read-only render.
     */
    public function inlineEditable(): bool
    {
        return false;
    }

    public function assets(): array
    {
        // SCSS only — the ProseMirror editor JS ships in the page-builder
        // admin bundle, never on the public site. The public render is plain
        // sanitized HTML with no editor JS.
        return ['scss' => ['app/Widgets/Table/styles.scss']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'table_html', 'type' => 'table', 'label' => 'Table', 'group' => 'content', 'helper' => 'Build the table in the editor — insert rows and columns, merge cells, toggle a header row, set per-column widths, and paste tables from Word, Google Docs, or the web.'],

            // Per-column percentage widths, authored above each column inside
            // the editor (not a standard inspector control) and applied as a
            // <colgroup> at render. Data-only here: kept in config, hidden from
            // the inspector rail.
            ['key' => 'column_widths', 'type' => 'column_widths', 'label' => 'Column widths', 'group' => 'content', 'inspector' => false],

            // ── Appearance ──────────────────────────────────────────────────
            // Every fill and text colour is user-controlled, applied at render
            // via CSS custom properties. The border control reuses the shared
            // E17 tool extended with interior gridlines (allow_interior);
            // alignment reuses the built alignment field. Nothing here touches
            // the stored table HTML.
            ['key' => 'border', 'type' => 'border', 'label' => 'Border & gridlines', 'group' => 'appearance', 'allow_interior' => true, 'helper' => 'Outer edges plus interior row/column gridlines. They share one width and colour.'],

            ['key' => 'header_heading', 'type' => 'heading', 'label' => 'Header row', 'group' => 'appearance'],
            ['key' => 'header_align', 'type' => 'alignment', 'label' => 'Header alignment', 'group' => 'appearance'],
            ['key' => 'header_bg', 'type' => 'color', 'label' => 'Header background', 'group' => 'appearance'],
            ['key' => 'header_text', 'type' => 'color', 'label' => 'Header text', 'group' => 'appearance'],

            ['key' => 'body_heading', 'type' => 'heading', 'label' => 'Body cells', 'group' => 'appearance'],
            ['key' => 'body_align', 'type' => 'alignment', 'label' => 'Body alignment', 'group' => 'appearance'],
            ['key' => 'body_bg', 'type' => 'color', 'label' => 'Cell background', 'group' => 'appearance'],
            ['key' => 'body_text', 'type' => 'color', 'label' => 'Cell text', 'group' => 'appearance'],

            ['key' => 'zebra_heading', 'type' => 'heading', 'label' => 'Zebra striping', 'group' => 'appearance'],
            ['key' => 'zebra', 'type' => 'toggle', 'label' => 'Stripe alternating rows', 'group' => 'appearance'],
            ['key' => 'zebra_bg', 'type' => 'color', 'label' => 'Stripe background', 'group' => 'appearance', 'shown_when' => 'zebra'],
            ['key' => 'zebra_text', 'type' => 'color', 'label' => 'Stripe text', 'group' => 'appearance', 'shown_when' => 'zebra'],
        ];
    }

    public function defaults(): array
    {
        return [
            'table_html'    => '',
            'column_widths' => [],
            'border'        => [
                'top'              => true,
                'right'            => true,
                'bottom'           => true,
                'left'             => true,
                'inner_horizontal' => true,
                'inner_vertical'   => true,
                'width'            => 1,
                'color'            => '#cbd5e1',
                'radius'           => 0,
            ],
            'header_heading' => '',
            'header_align'   => 'center',
            'header_bg'      => '#f1f5f9',
            'header_text'    => '#0f172a',
            'body_heading'   => '',
            'body_align'     => 'middle-left',
            'body_bg'        => '#ffffff',
            'body_text'      => '#1f2937',
            'zebra_heading'  => '',
            'zebra'          => false,
            'zebra_bg'       => '#f8fafc',
            'zebra_text'     => '#1f2937',
        ];
    }

    public function defaultOpen(): bool
    {
        return true;
    }

    public function keywords(): array
    {
        return ['table', 'grid', 'rows', 'columns', 'data', 'comparison', 'spreadsheet'];
    }

    public function demoConfig(): array
    {
        return [
            'table_html' => '<table><tbody>'
                . '<tr><th><p>Plan</p></th><th><p>Price</p></th><th><p>What you get</p></th></tr>'
                . '<tr><td><p>Starter</p></td><td><p>Free</p></td><td><p>Up to 100 contacts</p></td></tr>'
                . '<tr><td><p>Growth</p></td><td><p>$25 / month</p></td><td><p>Unlimited contacts and events</p></td></tr>'
                . '<tr><td><p>Pro</p></td><td><p>$60 / month</p></td><td><p>Everything, plus the public website</p></td></tr>'
                . '</tbody></table>',
        ];
    }
}
