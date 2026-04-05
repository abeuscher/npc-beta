<?php

use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Seeder ─────────────────────────────────────────────────────────────────

it('seeder creates social_sharing widget with correct config schema', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'social_sharing')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Social Sharing')
        ->and($wt->category)->toBe(['content'])
        ->and($wt->collections)->toBe([]);

    $keys = collect($wt->config_schema)->pluck('key')->filter()->values()->all();
    expect($keys)->toContain('platforms')
        ->toContain('alignment')
        ->toContain('icon_size')
        ->toContain('mastodon_instance');

    $platforms = collect($wt->config_schema)->firstWhere('key', 'platforms');
    expect($platforms['type'])->toBe('checkboxes')
        ->and($platforms['default'])->toBe(['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook']);
});

it('getDefaultConfig returns all platforms enabled by default', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'social_sharing')->first();
    $defaults = $wt->getDefaultConfig();

    expect($defaults['platforms'])->toBe(['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook'])
        ->and($defaults['alignment'])->toBe('center')
        ->and($defaults['icon_size'])->toBe('small')
        ->and($defaults['mastodon_instance'])->toBe('mastodon.social');
});

// ── Blade rendering ────────────────────────────────────────────────────────

it('renders share links for all enabled platforms', function () {
    $html = Blade::render(
        file_get_contents(resource_path('views/widgets/social-sharing.blade.php')),
        ['config' => [
            'heading'           => 'Share this page',
            'platforms'         => ['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook'],
            'alignment'         => 'center',
            'icon_size'         => 'small',
            'background_color'  => '',
            'text_color'        => '',
            'full_width'        => false,
            'mastodon_instance' => 'mastodon.social',
        ]]
    );

    expect($html)
        ->toContain('bsky.app/intent/compose')
        ->toContain('mastodon.social/share')
        ->toContain('mailto:?subject=')
        ->toContain('linkedin.com/sharing/share-offsite')
        ->toContain('facebook.com/sharer/sharer.php')
        ->toContain('Share this page');
});

it('omits disabled platforms', function () {
    $html = Blade::render(
        file_get_contents(resource_path('views/widgets/social-sharing.blade.php')),
        ['config' => [
            'heading'           => '',
            'platforms'         => ['bluesky', 'email'],
            'alignment'         => 'center',
            'icon_size'         => 'small',
            'background_color'  => '',
            'text_color'        => '',
            'full_width'        => false,
            'mastodon_instance' => 'mastodon.social',
        ]]
    );

    expect($html)
        ->toContain('bsky.app/intent/compose')
        ->toContain('mailto:?subject=')
        ->not->toContain('mastodon.social/share')
        ->not->toContain('linkedin.com')
        ->not->toContain('facebook.com');
});

it('renders nothing when platforms array is empty', function () {
    $html = Blade::render(
        file_get_contents(resource_path('views/widgets/social-sharing.blade.php')),
        ['config' => [
            'heading'           => 'Share',
            'platforms'         => [],
            'alignment'         => 'center',
            'icon_size'         => 'small',
            'background_color'  => '',
            'text_color'        => '',
            'full_width'        => false,
            'mastodon_instance' => 'mastodon.social',
        ]]
    );

    expect($html)->not->toContain('widget-social-sharing');
});

it('renders Alpine copy-link markup when copy_link is enabled', function () {
    $html = Blade::render(
        file_get_contents(resource_path('views/widgets/social-sharing.blade.php')),
        ['config' => [
            'heading'           => '',
            'platforms'         => ['copy_link'],
            'alignment'         => 'center',
            'icon_size'         => 'small',
            'background_color'  => '',
            'text_color'        => '',
            'full_width'        => false,
            'mastodon_instance' => 'mastodon.social',
        ]]
    );

    expect($html)
        ->toContain('navigator.clipboard.writeText')
        ->toContain('Copied!')
        ->toContain('<button');
});

it('uses configured mastodon instance domain', function () {
    $html = Blade::render(
        file_get_contents(resource_path('views/widgets/social-sharing.blade.php')),
        ['config' => [
            'heading'           => '',
            'platforms'         => ['mastodon'],
            'alignment'         => 'center',
            'icon_size'         => 'small',
            'background_color'  => '',
            'text_color'        => '',
            'full_width'        => false,
            'mastodon_instance' => 'fosstodon.org',
        ]]
    );

    expect($html)
        ->toContain('fosstodon.org/share')
        ->not->toContain('mastodon.social');
});

it('external share links have target blank and rel noopener', function () {
    $html = Blade::render(
        file_get_contents(resource_path('views/widgets/social-sharing.blade.php')),
        ['config' => [
            'heading'           => '',
            'platforms'         => ['bluesky', 'linkedin', 'facebook'],
            'alignment'         => 'center',
            'icon_size'         => 'small',
            'background_color'  => '',
            'text_color'        => '',
            'full_width'        => false,
            'mastodon_instance' => 'mastodon.social',
        ]]
    );

    expect($html)
        ->toContain('target="_blank"')
        ->toContain('rel="noopener"');
});
