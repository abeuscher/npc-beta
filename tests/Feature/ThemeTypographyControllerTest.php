<?php

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\TypographyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage_cms_settings']);
});

it('rejects unauthenticated save requests', function () {
    $this->postJson(route('filament.admin.theme.typography.update'), ['typography' => TypographyResolver::defaults()])
        ->assertStatus(401);
});

it('rejects save from a user without manage_cms_settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson(route('filament.admin.theme.typography.update'), ['typography' => TypographyResolver::defaults()])
        ->assertStatus(403);
});

it('saves typography to SiteSetting for users with manage_cms_settings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_cms_settings');

    $payload = TypographyResolver::defaults();
    $payload['buckets']['heading_family'] = "'Inter', sans-serif";
    $payload['sample_text'] = 'Custom sample.';

    $this->actingAs($user)
        ->postJson(route('filament.admin.theme.typography.update'), ['typography' => $payload])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $stored = SiteSetting::get('typography');
    expect($stored['buckets']['heading_family'])->toBe("'Inter', sans-serif");
    expect($stored['sample_text'])->toBe('Custom sample.');
});

it('exports compiled SCSS with the correct headers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_cms_settings');

    $payload = TypographyResolver::defaults();
    $payload['buckets']['heading_family']      = "'Inter', sans-serif";
    $payload['elements']['h1']['font']['family'] = "'Inter', sans-serif";
    SiteSetting::create(['key' => 'typography', 'type' => 'json', 'group' => 'design', 'value' => json_encode($payload)]);

    $res = $this->actingAs($user)->get(route('filament.admin.theme.typography.export'));

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toStartWith('text/x-scss');
    expect($res->headers->get('Content-Disposition'))->toContain('theme-typography.scss');
    expect($res->getContent())->toContain('// Theme typography');
    expect($res->getContent())->toContain("font-family: 'Inter', sans-serif");
});

it('rejects export without manage_cms_settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('filament.admin.theme.typography.export'))->assertStatus(403);
});
