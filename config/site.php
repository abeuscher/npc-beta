<?php

return [
    'name'          => env('SITE_NAME', 'Nonprofit CRM'),
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
];
