<?php

use App\Filament\Pages\Settings\GeneralSettingsPage;
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
        ['key' => 'base_url',         'value' => 'http://localhost',  'group' => 'general', 'type' => 'string'],
        ['key' => 'blog_prefix',      'value' => 'news',             'group' => 'general', 'type' => 'string'],
        ['key' => 'events_prefix',    'value' => 'events',           'group' => 'general', 'type' => 'string'],
        ['key' => 'portal_prefix',    'value' => 'members',          'group' => 'general', 'type' => 'string'],
        ['key' => 'site_description', 'value' => '',                 'group' => 'general', 'type' => 'string'],
        ['key' => 'timezone',         'value' => 'America/Chicago',  'group' => 'general', 'type' => 'string'],
        ['key' => 'contact_email',    'value' => '',                 'group' => 'general', 'type' => 'string'],
        ['key' => 'use_pico',         'value' => 'false',            'group' => 'styles',  'type' => 'boolean'],
    ];
    foreach ($defaults as $s) {
        SiteSetting::firstOrCreate(['key' => $s['key']], $s);
    }
    config(['site.blog_prefix' => 'news']);
});

it('rejects a blog prefix that matches an existing page slug', function () {
    CmsPage::factory()->create([
        'title'        => 'About Us',
        'slug'         => 'about',
        'type'         => 'default',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm([
            'site_url'      => 'http://localhost',
            'blog_prefix'   => 'about',
            'events_prefix' => 'events',
            'portal_prefix' => 'members',
        ])
        ->call('save')
        ->assertHasFormErrors(['blog_prefix']);
});

it('rejects a blog prefix that is a reserved word', function () {
    foreach (['admin', 'horizon', 'up', 'login', 'logout', 'register'] as $reserved) {
        Livewire::test(GeneralSettingsPage::class)
            ->fillForm([
                'site_url'      => 'http://localhost',
                'blog_prefix'   => $reserved,
                'events_prefix' => 'events',
                'portal_prefix' => 'members',
            ])
            ->call('save')
            ->assertHasFormErrors(['blog_prefix']);
    }
});

it('accepts a valid blog prefix', function () {
    Livewire::test(GeneralSettingsPage::class)
        ->fillForm([
            'site_url'      => 'http://localhost',
            'blog_prefix'   => 'stories',
            'events_prefix' => 'events',
            'portal_prefix' => 'members',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('changing the prefix renames all post slugs', function () {
    // Create blog index page
    CmsPage::factory()->create([
        'title'        => 'News',
        'slug'         => 'news',
        'type'         => 'default',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // Create posts with the old prefix
    $post1 = CmsPage::factory()->create([
        'title'        => 'Post One',
        'slug'         => 'news/post-one',
        'type'         => 'post',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $post2 = CmsPage::factory()->create([
        'title'        => 'Post Two',
        'slug'         => 'news/post-two',
        'type'         => 'post',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm([
            'site_url'      => 'http://localhost',
            'blog_prefix'   => 'stories',
            'events_prefix' => 'events',
            'portal_prefix' => 'members',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post1->fresh()->slug)->toBe('stories/post-one');
    expect($post2->fresh()->slug)->toBe('stories/post-two');
});

it('the trailing-slash query avoids false positives when renaming prefixes', function () {
    // Create a page whose slug starts with 'news' but is NOT a post (e.g. 'newsroom')
    // It should NOT be renamed when the blog prefix changes from 'news' to 'stories'.
    $newsroomPage = CmsPage::factory()->create([
        'title'        => 'Newsroom',
        'slug'         => 'newsroom',
        'type'         => 'default',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // Blog index page
    CmsPage::factory()->create([
        'title'        => 'News',
        'slug'         => 'news',
        'type'         => 'default',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $post = CmsPage::factory()->create([
        'title'        => 'A Post',
        'slug'         => 'news/a-post',
        'type'         => 'post',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm([
            'site_url'      => 'http://localhost',
            'blog_prefix'   => 'stories',
            'events_prefix' => 'events',
            'portal_prefix' => 'members',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Post slug should be updated
    expect($post->fresh()->slug)->toBe('stories/a-post');

    // Newsroom should be untouched
    expect($newsroomPage->fresh()->slug)->toBe('newsroom');
});

it('changing the prefix also renames the blog index page slug', function () {
    $blogIndexPage = CmsPage::factory()->create([
        'title'        => 'News',
        'slug'         => 'news',
        'type'         => 'default',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm([
            'site_url'      => 'http://localhost',
            'blog_prefix'   => 'stories',
            'events_prefix' => 'events',
            'portal_prefix' => 'members',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($blogIndexPage->fresh()->slug)->toBe('stories');
});
