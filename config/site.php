<?php

return [
    'name'          => env('SITE_NAME', 'Nonprofit CRM'),
    // Marketing-site instance only — exposes /docs + the docs AEO surfaces.
    // Deliberately a plain env read (not a SiteSetting): per-instance, not
    // operator-configurable. Read via isPublicWebsite().
    'public_website' => env('PUBLIC_WEBSITE', false),
    'blog_prefix'   => (static function () {
        try {
            return \App\Models\SiteSetting::get('blog_prefix', env('BLOG_PREFIX', 'news'));
        } catch (\Throwable) {
            return env('BLOG_PREFIX', 'news');
        }
    })(),
    'events_prefix' => (static function () {
        try {
            return \App\Models\SiteSetting::get('events_prefix', env('EVENTS_PREFIX', 'events'));
        } catch (\Throwable) {
            return env('EVENTS_PREFIX', 'events');
        }
    })(),
    'system_prefix' => (static function () {
        try {
            return \App\Models\SiteSetting::get('system_prefix', env('SYSTEM_PREFIX', 'system'));
        } catch (\Throwable) {
            return env('SYSTEM_PREFIX', 'system');
        }
    })(),
];
