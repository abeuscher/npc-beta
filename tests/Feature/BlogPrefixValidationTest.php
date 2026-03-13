<?php

use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Models\Page as CmsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');
    $this->actingAs($user);

    // Seed required settings so the page can mount
    $defaults = [
        ['key' => 'site_name',        'value' => 'My Org',           'group' => 'general', 'type' => 'string'],
        ['key' => 'base_url',         'value' => 'http://localhost',  'group' => 'general', 'type' => 'string'],
        ['key' => 'blog_prefix',      'value' => 'news',             'group' => 'general', 'type' => 'string'],
        ['key' => 'site_description', 'value' => '',                 'group' => 'general', 'type' => 'string'],
        ['key' => 'timezone',         'value' => 'America/Chicago',  'group' => 'general', 'type' => 'string'],
        ['key' => 'contact_email',    'value' => '',                 'group' => 'general', 'type' => 'string'],
        ['key' => 'use_pico',         'value' => 'false',            'group' => 'styles',  'type' => 'boolean'],
    ];
    foreach ($defaults as $s) {
        SiteSetting::firstOrCreate(['key' => $s['key']], $s);
    }
});

it('rejects a blog prefix that matches an existing page slug', function () {
    CmsPage::create([
        'title'        => 'About Us',
        'slug'         => 'about',
        'content'      => '<p>About.</p>',
        'is_published' => true,
        'published_at' => now(),
    ]);

    Livewire::test(CmsSettingsPage::class)
        ->fillForm(['blog_prefix' => 'about'])
        ->call('save')
        ->assertHasFormErrors(['blog_prefix']);
});

it('rejects a blog prefix that is a reserved word', function () {
    foreach (['admin', 'horizon', 'up', 'login', 'logout', 'register'] as $reserved) {
        Livewire::test(CmsSettingsPage::class)
            ->fillForm(['blog_prefix' => $reserved])
            ->call('save')
            ->assertHasFormErrors(['blog_prefix']);
    }
});

it('accepts a valid blog prefix', function () {
    Livewire::test(CmsSettingsPage::class)
        ->fillForm([
            'site_name'   => 'My Org',
            'blog_prefix' => 'stories',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});
