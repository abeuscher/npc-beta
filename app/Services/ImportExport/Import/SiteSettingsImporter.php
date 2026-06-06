<?php

namespace App\Services\ImportExport\Import;

use App\Models\SiteSetting;
use App\Services\ImportExport\ImportLog;
use App\Services\ImportExport\SiteSettingsBundlePolicy;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Cache;

/**
 * Imports the payload.site_settings pass — per-key upsert with defensive
 * deny-list re-check. The exporter has already filtered against the same lists,
 * but the importer never trusts the bundle: every inbound key runs through
 * {@see SiteSettingsBundlePolicy::isDenied()} and any `encrypted`-type entry is
 * hard-skipped. Rich-text keys are sanitised on the way in. Session A001/3.
 */
class SiteSettingsImporter
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function import(array $settings, ImportLog $log): void
    {
        foreach ($settings as $key => $entry) {
            if (! is_string($key) || $key === '' || ! is_array($entry)) {
                $log->warning('SiteSettings: skipping malformed entry.');
                continue;
            }

            if (SiteSettingsBundlePolicy::isDenied($key)) {
                $log->warning("SiteSettings: key '{$key}' blocked by importer deny-list (matched secret/credential pattern), skipped.");
                continue;
            }

            $type = $entry['type'] ?? 'string';
            if ($type === 'encrypted') {
                $log->warning("SiteSettings: key '{$key}' has encrypted type, never imported.");
                continue;
            }

            $value = $entry['value'] ?? null;

            // Rich-text sanitisation at the import seam — same allow-list
            // boundary `SiteSetting::set()` uses for through-model writes.
            if (in_array($key, SiteSetting::RICH_TEXT_KEYS, true) && is_string($value)) {
                $value = HtmlSanitizer::sanitize($value);
            }

            SiteSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type'  => $type,
                    'group' => $entry['group'] ?? null,
                ],
            );
            Cache::forget("site_setting:{$key}");
            $log->info("SiteSettings: imported '{$key}'.");
        }
    }
}
