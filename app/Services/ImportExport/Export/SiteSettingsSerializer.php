<?php

namespace App\Services\ImportExport\Export;

use App\Models\SiteSetting;
use App\Services\ImportExport\SiteSettingsBundlePolicy;

/**
 * Collects the curated visual/SEO/routing slice of SiteSettings for the
 * `payload.site_settings` section. Source of truth for what travels is
 * {@see SiteSettingsBundlePolicy::ALLOW_LIST}; the deny-list is applied here
 * defensively so secrets can never enter the bundle even if a key accidentally
 * appears on both lists. Encrypted-type rows are hard-skipped. Session A001/3.
 *
 * The per-key envelope captures `value` + `type` + `group` exactly as stored,
 * so a round-trip re-creates the row faithfully regardless of cast semantics.
 */
class SiteSettingsSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $settings = [];
        $rows = SiteSetting::whereIn('key', SiteSettingsBundlePolicy::ALLOW_LIST)->get();

        foreach ($rows as $row) {
            // Defence-in-depth: even allow-listed keys are dropped if they
            // match the deny-list (e.g. accidental future overlap) or carry
            // the `encrypted` type. The importer re-checks on the inbound
            // side so neither half trusts the other.
            if (SiteSettingsBundlePolicy::isDenied($row->key)) {
                continue;
            }
            if ($row->type === 'encrypted') {
                continue;
            }

            $settings[$row->key] = [
                'value' => $row->value,
                'type'  => $row->type,
                'group' => $row->group,
            ];
        }

        return $settings;
    }
}
