<?php

use App\Filament\Pages\DesignSystemPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

// ── Button settings save & load ──

it('saves button styles to site settings as json', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(DesignSystemPage::class)
        ->fillForm([
            'button_styles.primary.bg_color'      => '#ff0000',
            'button_styles.primary.text_color'     => '#ffffff',
            'button_styles.primary.border_radius'  => 'pill',
            'button_styles.primary.font_weight'    => '700',
        ])
        ->call('save');

    $saved = SiteSetting::get('button_styles');
    expect($saved)->toBeArray();
    expect($saved['primary']['bg_color'])->toBe('#ff0000');
    expect($saved['primary']['text_color'])->toBe('#ffffff');
    expect($saved['primary']['border_radius'])->toBe('pill');
    expect($saved['primary']['font_weight'])->toBe('700');
});

it('applies default values when no settings exist', function () {
    $defaults = DesignSystemPage::defaultButtonStyles();

    expect($defaults)->toHaveKeys(['primary', 'secondary', 'text', 'destructive', 'link']);
    expect($defaults['primary']['bg_color'])->toBe('#0172ad');
    expect($defaults['primary']['text_color'])->toBe('#ffffff');
    expect($defaults['primary']['font_weight'])->toBe('600');
    expect($defaults['secondary']['border_width'])->toBe('1px');
    expect($defaults['destructive']['bg_color'])->toBe('#dc2626');
    expect($defaults['link']['font_weight'])->toBe('400');
});

it('loads saved settings merged with defaults on mount', function () {
    SiteSetting::create([
        'key'   => 'button_styles',
        'value' => json_encode(['primary' => ['bg_color' => '#333333']]),
        'type'  => 'json',
        'group' => 'design',
    ]);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $component = Livewire::actingAs($user)->test(DesignSystemPage::class);

    // Should have the saved bg_color merged with defaults for remaining fields
    $data = $component->get('data');
    expect($data['button_styles']['primary']['bg_color'])->toBe('#333333');
    expect($data['button_styles']['primary']['font_weight'])->toBe('600'); // default
});

it('rejects invalid hex color values on save', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(DesignSystemPage::class)
        ->fillForm([
            'button_styles.primary.bg_color' => 'not-a-hex',
        ])
        ->call('save');

    $saved = SiteSetting::get('button_styles');
    expect($saved['primary']['bg_color'])->toBeNull();
});

it('generates button override css in the build pipeline', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(DesignSystemPage::class)
        ->fillForm([
            'button_styles.primary.bg_color'     => '#ff5500',
            'button_styles.primary.border_radius' => 'pill',
            'button_styles.primary.font_weight'   => '700',
        ])
        ->call('save');

    // The build service reads from DB and generates the CSS inline
    $buildService = new \App\Services\AssetBuildService();
    $method = new \ReflectionMethod($buildService, 'generateButtonOverrideCss');
    $css = $method->invoke($buildService);

    expect($css)->toContain('--btn-primary-bg: #ff5500');
    expect($css)->toContain('--btn-primary-radius: 999px');
    expect($css)->toContain('--btn-primary-font-weight: 700');
});

// ── External link detection in button component ──

it('appends external link icon for outbound urls', function () {
    config(['app.url' => 'https://example.org']);

    $view = view('widgets.components.buttons', [
        'buttons' => [
            ['text' => 'Internal', 'url' => '/about', 'style' => 'primary'],
            ['text' => 'External', 'url' => 'https://other-site.com/page', 'style' => 'primary'],
        ],
    ])->render();

    // Internal link should NOT have external icon or target=_blank
    expect($view)->toContain('href="/about"');
    expect($view)->not->toContain('Internal</a>'); // Will have just text, no svg before </a>

    // External link should have the icon and target=_blank
    expect($view)->toContain('target="_blank"');
    expect($view)->toContain('rel="noopener noreferrer"');
});

it('does not flag subdomain urls as external', function () {
    config(['app.url' => 'https://example.org']);

    $view = view('widgets.components.buttons', [
        'buttons' => [
            ['text' => 'Subdomain', 'url' => 'https://blog.example.org/post', 'style' => 'primary'],
        ],
    ])->render();

    expect($view)->not->toContain('target="_blank"');
});

it('does not flag relative urls as external', function () {
    $view = view('widgets.components.buttons', [
        'buttons' => [
            ['text' => 'Relative', 'url' => '/contact', 'style' => 'primary'],
        ],
    ])->render();

    expect($view)->not->toContain('target="_blank"');
});

// ── Page access ──

it('restricts design system page to authorized users', function () {
    $user = User::factory()->create();
    // No role assigned — should not be able to access

    expect(DesignSystemPage::canAccess())->toBeFalse();
});
