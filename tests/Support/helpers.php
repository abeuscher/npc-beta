<?php

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
