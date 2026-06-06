<?php

namespace App\Services\ImportExport\Import;

use App\Filament\Pages\DesignSystemPage;
use App\Models\SiteSetting;
use App\Services\ColorTokenResolver;
use App\Services\ImportExport\ImportLog;
use App\Services\TypographyResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Imports the payload.design pass — deep-merges each imported design row over
 * its resolver default shape and persists. Never sweeps, replaces wholesale, or
 * zeroes unknown keys: a key absent from the bundle keeps the default concrete
 * value (session 303; the 295 em-rhythm-revert lesson / concrete-values rule).
 * Runs inside the import transaction.
 */
class DesignImporter
{
    /**
     * @param  array<string, mixed>  $design
     */
    public function import(array $design, ImportLog $log): void
    {
        $buttonDefaults = DesignSystemPage::defaultButtonStyles();
        $buttonDefaults['icon']        = DesignSystemPage::defaultIconSettings();
        $buttonDefaults['form_append'] = DesignSystemPage::defaultFormAppendSettings();

        $rows = [
            'theme_colors'  => ColorTokenResolver::defaults(),
            'typography'    => TypographyResolver::defaults(),
            'button_styles' => $buttonDefaults,
        ];

        foreach ($rows as $key => $defaults) {
            if (! array_key_exists($key, $design) || ! is_array($design[$key])) {
                continue;
            }

            $incoming = $design[$key];
            if ($key === 'typography') {
                // Same flat→per-breakpoint normaliser the read path applies.
                $incoming = TypographyResolver::migrate($incoming);
            }

            $merged = $this->deepMergeOverDefaults($defaults, $incoming);

            SiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($merged), 'type' => 'json', 'group' => 'design'],
            );
            Cache::forget("site_setting:{$key}");
            $log->info("Theme: imported '{$key}'.");
        }
    }

    /**
     * Recursive merge of imported values over a concrete default base. Never
     * removes a default key; a null override keeps the default (the 295
     * lesson — change values, never null configuration). List arrays are
     * replaced wholesale, associative arrays recursed.
     *
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $imported
     * @return array<mixed>
     */
    protected function deepMergeOverDefaults(array $defaults, array $imported): array
    {
        foreach ($imported as $key => $value) {
            if (is_array($value)
                && isset($defaults[$key]) && is_array($defaults[$key])
                && ! array_is_list($defaults[$key]) && ! array_is_list($value)) {
                $defaults[$key] = $this->deepMergeOverDefaults($defaults[$key], $value);
            } elseif ($value !== null) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
