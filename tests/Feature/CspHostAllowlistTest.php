<?php

use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rules\ValidCspHostList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Admin-editable CSP host allow-list (session 370 follow-up). External hosts for
 * analytics/embeds are managed from CMS Settings (stored as SiteSettings), merged
 * onto the env floor, and validated so the policy can only widen to named hosts.
 */

function cspRulePasses(string $value): bool
{
    return Validator::make(['f' => $value], ['f' => [new ValidCspHostList()]])->passes();
}

it('merges an admin-configured script host into the enforced public CSP', function () {
    SiteSetting::set('csp_script_src_extra', 'https://www.googletagmanager.com');
    (new AppServiceProvider(app()))->boot();

    $csp = $this->get('/robots.txt')->headers->get('Content-Security-Policy');
    expect($csp)->not->toBeNull();

    preg_match('/script-src ([^;]*)/', $csp, $m);
    expect($m[1])->toContain('https://www.googletagmanager.com')
        ->and($m[1])->toContain("'self'")
        ->and($m[1])->toContain("'nonce-")
        ->and($m[1])->not->toContain("'unsafe-inline'");
});

it('accepts multiple hosts one per line and keeps the env floor', function () {
    config(['security.csp.extra.img_src' => 'https://floor.example.com']);
    SiteSetting::set('csp_img_src_extra', "https://a.example.com\nhttps://b.example.com");
    (new AppServiceProvider(app()))->boot();

    $csp = $this->get('/robots.txt')->headers->get('Content-Security-Policy');

    preg_match('/img-src ([^;]*)/', $csp, $m);
    expect($m[1])->toContain('https://a.example.com')
        ->and($m[1])->toContain('https://b.example.com')
        ->and($m[1])->toContain('https://floor.example.com');
});

it('validation accepts real hosts and rejects wildcards, keywords, and bare schemes', function () {
    expect(cspRulePasses('https://www.googletagmanager.com'))->toBeTrue();
    expect(cspRulePasses('*.google.com'))->toBeTrue();
    expect(cspRulePasses('www.google-analytics.com'))->toBeTrue();
    expect(cspRulePasses("https://a.com\nhttps://b.com"))->toBeTrue();
    expect(cspRulePasses(''))->toBeTrue();

    expect(cspRulePasses('*'))->toBeFalse();
    expect(cspRulePasses("'unsafe-inline'"))->toBeFalse();
    expect(cspRulePasses("'self'"))->toBeFalse();
    expect(cspRulePasses('https:'))->toBeFalse();
    expect(cspRulePasses('data:'))->toBeFalse();
    expect(cspRulePasses('notahost'))->toBeFalse();
    expect(cspRulePasses("https://ok.com\n*"))->toBeFalse();
});

it('persists the allow-list from the CMS Settings form', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CmsSettingsPage::class)
        ->fillForm(['csp_script_src_extra' => 'https://www.googletagmanager.com'])
        ->call('saveSection', 'csp-hosts', 'Allowed External Hosts')
        ->assertHasNoFormErrors();

    expect(SiteSetting::get('csp_script_src_extra'))->toBe('https://www.googletagmanager.com');
});

it('rejects a policy-weakening entry at the form layer', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CmsSettingsPage::class)
        ->fillForm(['csp_script_src_extra' => "'unsafe-inline'"])
        ->call('saveSection', 'csp-hosts', 'Allowed External Hosts')
        ->assertHasFormErrors(['csp_script_src_extra']);

    expect(SiteSetting::get('csp_script_src_extra', ''))->toBe('');
});
