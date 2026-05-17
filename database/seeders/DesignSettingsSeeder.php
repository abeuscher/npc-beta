<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Version-controlled source of truth for the install's design configuration.
 *
 * `typography` and `button_styles` were previously DB-only (no seeder, no file)
 * — a `migrate:fresh --seed` / fresh environment wiped them and the resolver
 * fell back to code defaults (zero margins, default sizes). This seeder makes
 * the configured design durable.
 *
 * Idempotent via firstOrCreate keyed on `key`: it seeds only when the row is
 * ABSENT, so it restores a reset/fresh DB but never clobbers a site whose
 * design has since been edited through the admin UI.
 *
 * Provenance of the values below: recovered at session 295 from live DB +
 * built-bundle inspection.
 *  - button_styles: COMPLETE — only the `secondary` variant was ever
 *    configured (verified raw `site_settings` row). Other variants were never
 *    set and correctly fall through to code defaults.
 *  - typography: the VERIFIED deviations only — h1/h2/h3/p xl font-size + the
 *    deliberate rem bottom-margins. Stored in the legacy flat `{value,unit}`
 *    shape on purpose: `TypographyResolver::migrate()` upgrades it to the
 *    per-breakpoint shape idempotently on read, and `load()` merges this
 *    partial tree over code defaults — so unspecified elements/fields (h4–h6,
 *    list items, families, weights, buckets, sample_text) correctly inherit
 *    the code defaults. If a full recovered `typography` JSON is brought
 *    forward, replace $typography wholesale with it.
 */
class DesignSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $remMargin = fn (float $bottom): array => [
            'top' => 0, 'right' => 0, 'bottom' => $bottom, 'left' => 0, 'unit' => 'rem',
        ];
        $remSize = fn (float $value): array => ['value' => $value, 'unit' => 'rem'];

        $typography = [
            'elements' => [
                'h1' => ['font' => ['size' => $remSize(3.5)],  'margin' => $remMargin(1.5)],
                'h2' => ['font' => ['size' => $remSize(2.5)],  'margin' => $remMargin(1.25)],
                'h3' => ['font' => ['size' => $remSize(1.75)], 'margin' => $remMargin(1.0)],
                'p'  => ['font' => ['size' => $remSize(1.0)],  'margin' => $remMargin(1.0)],
            ],
        ];

        $buttonStyles = [
            'secondary' => [
                'border_radius'  => 'slightly-rounded',
                'bg_color'       => '#ffffff',
                'text_color'     => '#111827',
                'border_color'   => '#111827',
                'border_width'   => '1px',
                'hover'          => 'opacity',
                'font_weight'    => '600',
                'text_transform' => 'none',
            ],
        ];

        SiteSetting::firstOrCreate(
            ['key' => 'typography'],
            ['value' => json_encode($typography), 'group' => 'design', 'type' => 'json'],
        );

        SiteSetting::firstOrCreate(
            ['key' => 'button_styles'],
            ['value' => json_encode($buttonStyles), 'group' => 'design', 'type' => 'json'],
        );
    }
}
