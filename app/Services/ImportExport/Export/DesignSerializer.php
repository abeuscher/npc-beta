<?php

namespace App\Services\ImportExport\Export;

use App\Models\SiteSetting;

/**
 * Collects the site-wide theme/design slice — the three design-group
 * SiteSetting rows (theme_colors, typography, button_styles) captured exactly
 * as stored. Shared by the standalone design export and the page/template
 * `with_design` opt-in. Import deep-merges these over resolver defaults and
 * never sweeps (session 303). A theme bundle carries no media.
 */
class DesignSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $design = [];
        foreach (['theme_colors', 'typography', 'button_styles'] as $key) {
            $value = SiteSetting::get($key);
            if (is_array($value)) {
                $design[$key] = $value;
            }
        }

        return $design;
    }
}
