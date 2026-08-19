<?php

if (! function_exists('extractedPluginPackageDirs')) {
    /**
     * Absolute paths of extracted plugin packages under vendor/nonprofitcrm/
     * (session 385, Plugin Architecture arc P9). Standing guards that scan
     * plugins/ extend their scans over these directories so the zero-allowlist
     * guarantees follow a plugin out of the repo. Path-repo packages are
     * vendor symlinks back into plugins/ — already covered by every plugins/
     * scan — and are skipped so no file is ever scanned twice (baseline-diff
     * guards would report symlink duplicates as new violations).
     *
     * @return array<int, string>
     */
    function extractedPluginPackageDirs(): array
    {
        return array_values(array_filter(
            glob(base_path('vendor/nonprofitcrm/*'), GLOB_ONLYDIR) ?: [],
            fn ($p) => ! is_link($p),
        ));
    }
}

if (! function_exists('firstSampleLogo')) {
    function firstSampleLogo(): string
    {
        $files = glob(resource_path('sample-images/logos/*'));
        $files = array_values(array_filter($files, fn ($p) => is_file($p) && ! str_starts_with(basename($p), '.')));
        if (empty($files)) {
            throw new RuntimeException('No sample logos available in resources/sample-images/logos/');
        }
        return $files[0];
    }
}
