<?php

namespace App\Services;

class ThemeColorRelocation
{
    /**
     * 1:1 byte-faithful mapping from the (pre-297) templates.* colour columns
     * to the tier-1 --np-color-* tokens. The data-preservation contract: a
     * configured colour must land in the Theme byte-identical — no
     * normalisation, no case-folding, no trimming.
     */
    public const COLUMN_TO_TOKEN = [
        'primary_color'    => 'brand',
        'header_bg_color'  => 'header-bg',
        'footer_bg_color'  => 'footer-bg',
        'nav_link_color'   => 'nav-link',
        'nav_hover_color'  => 'nav-hover',
        'nav_active_color' => 'nav-active',
    ];

    /**
     * Merge a default template's six stored colour columns onto the tier-1
     * concrete defaults, producing the full 13-key Theme palette. A null /
     * absent column keeps the default for that token (so the seven tokens with
     * no template column — bg/surface/text/heading/text-muted/link/border —
     * always take their concrete default). Non-null values pass through
     * byte-identical.
     *
     * @param  array<string, mixed>  $columns  keyed by the pre-297 column name
     * @return array<string, string>  full tier-1 token map (every key concrete)
     */
    public static function mapTemplateColors(array $columns): array
    {
        $out = ColorTokenResolver::defaults();

        foreach (self::COLUMN_TO_TOKEN as $column => $token) {
            $value = $columns[$column] ?? null;
            if (is_string($value) && $value !== '') {
                $out[$token] = $value;
            }
        }

        return $out;
    }
}
