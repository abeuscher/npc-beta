<?php

if (! function_exists('isDemoMode')) {
    function isDemoMode(): bool
    {
        return app()->environment('demo');
    }
}

if (! function_exists('isPublicWebsite')) {
    function isPublicWebsite(): bool
    {
        return (bool) config('site.public_website');
    }
}
