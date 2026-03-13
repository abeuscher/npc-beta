<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            $settings = SiteSetting::all()->keyBy('key');
            config([
                'site.name'          => $settings->get('site_name')?->value    ?? config('app.name'),
                'site.base_url'      => $settings->get('base_url')?->value      ?? 'http://localhost',
                'site.blog_prefix'   => $settings->get('blog_prefix')?->value   ?? 'news',
                'site.description'   => $settings->get('site_description')?->value ?? '',
                'site.timezone'      => $settings->get('timezone')?->value      ?? 'America/Chicago',
                'site.contact_email' => $settings->get('contact_email')?->value ?? '',
                'site.use_pico'      => (bool) ($settings->get('use_pico')?->value ?? false),
                'site.custom_css'    => $settings->get('custom_css_path')?->value ?? null,
                'site.logo'          => $settings->get('logo_path')?->value     ?? null,
            ]);
        } catch (\Throwable $e) {
            // DB not ready (fresh install before migrations) — fall through to defaults
        }
    }
}

