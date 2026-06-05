<?php

namespace App\Widgets\PricingChart;

use App\Widgets\Contracts\WidgetDefinition;

class PricingChartDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'pricing_chart';
    }

    public function inlineEditable(): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'Pricing Chart';
    }

    public function description(): string
    {
        return 'A comparison-style pricing table with side-by-side columns. Each column carries an eyebrow, title, price, lead content, attribute rows, and CTAs.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/PricingChart/styles.scss']];
    }

    public function schema(): array
    {
        // 'auto' = fit the number of columns/rows that already have content
        // (the pre-count-setting behaviour). An explicit number renders
        // exactly that many; lowering it hides the extras without deleting
        // their data. 'auto' is a concrete value — an unconfigured or
        // pre-existing chart keeps rendering exactly as it did, with no
        // migration and no public regression.
        $countOptions = fn (int $min, int $max): array => ['auto' => 'Auto (fit existing)']
            + collect(range($min, $max))->mapWithKeys(fn ($n) => [(string) $n => (string) $n])->all();

        $emphasizeOptions = ['0' => 'None'] + collect(range(1, 10))
            ->mapWithKeys(fn ($n) => [(string) $n => "Column {$n}"])
            ->all();

        return [
            ['key' => 'eyebrow_label', 'type' => 'text',     'label' => 'Eyebrow label', 'group' => 'content', 'helper' => 'Small label above the heading (e.g. "Pricing"). Optional.'],
            ['key' => 'heading',       'type' => 'text',     'label' => 'Heading',       'group' => 'content'],
            ['key' => 'subheading',    'type' => 'richtext', 'label' => 'Subheading',    'group' => 'content'],

            ['key' => 'column_count',        'type' => 'select', 'label' => 'Number of columns',    'group' => 'content', 'default' => 'auto', 'options' => $countOptions(1, 10), 'helper' => 'How many pricing columns to show. Fill each column by editing it directly on the page. Lowering this hides the extra columns — their content is kept, not deleted.'],
            ['key' => 'attribute_row_count', 'type' => 'select', 'label' => 'Feature rows per column', 'group' => 'content', 'default' => 'auto', 'options' => $countOptions(0, 12), 'helper' => 'How many comparison rows each column shows. Lowering this hides the extra rows — their content is kept, not deleted.'],
            ['key' => 'emphasized_column',   'type' => 'select', 'label' => 'Emphasized column',    'group' => 'content', 'default' => '0', 'options' => $emphasizeOptions, 'helper' => 'Visually highlight one column as the recommended choice.'],

            // The columns repeater is inspector-visible so operators can set each
            // column's CTAs — the buttons partial is render-only, so CTAs cannot
            // be inline-edited on the canvas the way the text fields are. The
            // text/feature fields are edited directly on the page, so they are
            // marked inspector:false to keep this control focused on Title + CTAs;
            // their data is preserved either way (RepeaterField hides a sub-field,
            // never drops it). Title stays visible to identify the column.
            ['key' => 'columns', 'type' => 'repeater', 'label' => 'Columns', 'group' => 'content', 'item_label' => 'Column', 'helper' => 'Each column\'s text is edited directly on the page; set its buttons (CTAs) here.', 'fields' => [
                ['key' => 'emphasize',    'type' => 'toggle',   'label' => 'Emphasize this column', 'default' => false, 'inspector' => false, 'helper' => 'Visually highlight this column as the recommended choice.'],
                ['key' => 'eyebrow',      'type' => 'text',     'label' => 'Eyebrow',      'default' => '', 'inspector' => false, 'helper' => 'Short label above the title (e.g. "Recommended"). Optional.'],
                ['key' => 'title',        'type' => 'text',     'label' => 'Title',        'default' => ''],
                ['key' => 'price',        'type' => 'richtext', 'label' => 'Price',        'default' => '', 'inspector' => false],
                ['key' => 'lead_content', 'type' => 'richtext', 'label' => 'Lead content', 'default' => '', 'inspector' => false, 'helper' => 'Optional intro block above the attribute rows.'],
                ['key' => 'attribute_rows', 'type' => 'repeater', 'label' => 'Attribute rows', 'item_label' => 'Row', 'inspector' => false, 'fields' => [
                    ['key' => 'label', 'type' => 'text',     'label' => 'Label', 'default' => ''],
                    ['key' => 'value', 'type' => 'richtext', 'label' => 'Value', 'default' => ''],
                ]],
                ['key' => 'ctas', 'type' => 'buttons', 'label' => 'CTAs', 'fields' => [
                    ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                    ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                    ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                        'primary'        => 'Primary',
                        'secondary'      => 'Secondary',
                        'secondary-dark' => 'Secondary (Dark)',
                        'text'           => 'Text Only',
                    ]],
                ]],
            ]],

            ['key' => 'footnote', 'type' => 'richtext', 'label' => 'Footnote', 'group' => 'content', 'helper' => 'Fine-print or asterisk-anchored content rendered below the columns in smaller type.'],

            ['key' => 'heading_alignment', 'type' => 'select', 'label' => 'Heading alignment', 'default' => 'center', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'group' => 'appearance'],
            ['key' => 'gap',               'type' => 'text',   'label' => 'Custom gap',        'default' => '',       'helper' => 'CSS gap between columns (e.g. 1.5rem). Leave blank for default.', 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'eyebrow_label'       => '',
            'heading'             => '',
            'subheading'          => '',
            'column_count'        => 'auto',
            'attribute_row_count' => 'auto',
            'emphasized_column'   => '0',
            'columns'             => [],
            'footnote'            => '',
            'heading_alignment'   => 'center',
            'gap'                 => '',
        ];
    }

    public function defaultOpen(): bool
    {
        return true;
    }

    public function defaultAppearanceConfig(): array
    {
        return [
            'background' => [
                'color' => '#ffffff',
            ],
            'text' => [
                'color' => '#000000',
            ],
            'layout' => [
                'background_full_width' => true,
                'content_full_width'    => false,
                'padding' => [
                    'top'    => 100,
                    'right'  => 0,
                    'bottom' => 100,
                    'left'   => 0,
                ],
                'margin' => [
                    'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0,
                ],
                'border' => [
                    'top' => false, 'right' => false, 'bottom' => false, 'left' => false,
                    'width' => 0, 'color' => '#000000', 'radius' => 0,
                ],
            ],
        ];
    }

    public function keywords(): array
    {
        return ['pricing', 'comparison', 'table', 'tiers', 'plans'];
    }

    public function demoConfig(): array
    {
        return [
            'eyebrow_label'       => 'PRICING',
            'heading'             => 'Three ways to try this.',
            'subheading'          => '<p>Pick the one that fits where you are.</p>',
            'column_count'        => 'auto',
            'attribute_row_count' => 'auto',
            'columns'             => $this->marketingSiteTiers(),
            'footnote'      => '<p><em>* You ever notice how people put really small writing at the bottom of pricing sheets? What\'s that about? Seems sketchy.</em></p>',
        ];
    }

    public function presets(): array
    {
        return [];
    }

    /**
     * Shared "Marketing site tiers" data used by demoConfig() and exported by
     * the pricing page build (session 289) when configuring Band 2 against
     * this widget.
     */
    public function marketingSiteTiers(): array
    {
        return [
            [
                'emphasize'    => false,
                'eyebrow'      => '',
                'title'        => 'Instant Demo',
                'price'        => '<p><strong>Free</strong></p>',
                'lead_content' => '<p>24 hours.</p>',
                'attribute_rows' => [
                    ['label' => 'Setup',       'value' => '<p>Self-serve, no email required</p>'],
                    ['label' => 'Data',        'value' => '<p>Shared sandbox, resets every 24 hours</p>'],
                    ['label' => 'At the end',  'value' => '<p>Comes back fresh tomorrow with new data</p>'],
                ],
                'ctas' => [
                    ['text' => 'Try the demo', 'url' => '/demo', 'style' => 'primary'],
                ],
            ],
            [
                'emphasize'    => false,
                'eyebrow'      => '',
                'title'        => '7-Day Trial',
                'price'        => '<p><strong>Free</strong></p>',
                'lead_content' => '<p>7 days.</p>',
                'attribute_rows' => [
                    ['label' => 'Setup',       'value' => '<p>Email me; I set it up for you</p>'],
                    ['label' => 'Data',        'value' => '<p>Your data, your isolated instance</p>'],
                    ['label' => 'At the end',  'value' => '<p>We talk about next steps</p>'],
                ],
                'ctas' => [
                    ['text' => 'Request a trial', 'url' => '/contact', 'style' => 'secondary'],
                ],
            ],
            [
                'emphasize'    => true,
                'eyebrow'      => 'Recommended',
                'title'        => 'Monthly',
                'price'        => '<p><strong>$150</strong> per month</p>',
                'lead_content' => '<p>Ongoing.</p>',
                'attribute_rows' => [
                    ['label' => 'Setup',        'value' => '<p>Email me or use the contact form</p>'],
                    ['label' => 'Data',         'value' => '<p>Your data, your instance</p>'],
                    ['label' => 'First month',  'value' => '<p>$50 until I have steady customers*</p>'],
                    ['label' => 'Annual',       'value' => '<p>$1,500 per year (two months free) if you want to pay up front</p>'],
                    ['label' => 'At the end',   'value' => '<p>Cancel anytime</p>'],
                ],
                'ctas' => [
                    ['text' => 'Get started', 'url' => '/contact', 'style' => 'primary'],
                ],
            ],
        ];
    }
}
