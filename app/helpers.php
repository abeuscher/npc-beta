<?php

if (! function_exists('isDemoMode')) {
    function isDemoMode(): bool
    {
        return app()->environment('demo');
    }
}
