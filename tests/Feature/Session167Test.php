<?php

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\EditPageDetails;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\EditPostDetails;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

// ── EditPageDetails ────────────────────────────────────────────────────────

it('loads EditPageDetails and renders the metadata form', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $page = Page::factory()->create([
        'type'   => 'default',
        'status' => 'draft',
    ]);

    Livewire::test(EditPageDetails::class, ['record' => $page->id])
        ->assertSuccessful()
        ->assertSee('Page Details');
});

it('saves metadata changes on EditPageDetails', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $page = Page::factory()->create([
        'type'   => 'default',
        'status' => 'draft',
        'title'  => 'Old Title',
    ]);

    Livewire::test(EditPageDetails::class, ['record' => $page->id])
        ->fillForm([
            'title'  => 'New Title',
            'status' => 'published',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->title)->toBe('New Title')
        ->and($page->status)->toBe('published');
});

it('has correct breadcrumbs on EditPageDetails', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $page = Page::factory()->create([
        'type'   => 'default',
        'status' => 'draft',
    ]);

    $component = Livewire::test(EditPageDetails::class, ['record' => $page->id]);

    $breadcrumbs = $component->instance()->getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(3)
        ->and(array_values($breadcrumbs)[0])->toBe('Pages')
        ->and(array_values($breadcrumbs)[1])->toBe('Edit Page')
        ->and(array_values($breadcrumbs)[2])->toBe('Page Details');
});

// ── EditPage (builder-primary view) ────────────────────────────────────────

it('renders EditPage as a builder-only view', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $page = Page::factory()->create([
        'type'   => 'default',
        'status' => 'draft',
    ]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->assertSuccessful();
});

// ── EditPostDetails ────────────────────────────────────────────────────────

it('loads EditPostDetails and renders the metadata form', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $post = Page::factory()->create([
        'type'   => 'post',
        'slug'   => 'news/sample-post',
        'status' => 'draft',
    ]);

    Livewire::test(EditPostDetails::class, ['record' => $post->id])
        ->assertSuccessful()
        ->assertSee('Post Details');
});

it('saves metadata changes on EditPostDetails', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $post = Page::factory()->create([
        'type'   => 'post',
        'slug'   => 'news/test-post',
        'status' => 'draft',
        'title'  => 'Old Post Title',
    ]);

    Livewire::test(EditPostDetails::class, ['record' => $post->id])
        ->fillForm([
            'title'  => 'New Post Title',
            'status' => 'published',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();
    expect($post->title)->toBe('New Post Title')
        ->and($post->status)->toBe('published');
});

it('has correct breadcrumbs on EditPostDetails', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $post = Page::factory()->create([
        'type'   => 'post',
        'slug'   => 'news/breadcrumb-post',
        'status' => 'draft',
    ]);

    $component = Livewire::test(EditPostDetails::class, ['record' => $post->id]);

    $breadcrumbs = $component->instance()->getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(3)
        ->and(array_values($breadcrumbs)[0])->toBe('Blog Posts')
        ->and(array_values($breadcrumbs)[1])->toBe('Edit Post')
        ->and(array_values($breadcrumbs)[2])->toBe('Post Details');
});

// ── EditPost (builder-primary view) ────────────────────────────────────────

it('renders EditPost as a builder-only view', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $post = Page::factory()->create([
        'type'   => 'post',
        'slug'   => 'news/builder-post',
        'status' => 'draft',
    ]);

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->assertSuccessful();
});

// ── Security: system pages cannot be deleted from details ──────────────────

it('hides delete action on EditPageDetails for system pages', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $page = Page::factory()->create([
        'type'   => 'system',
        'slug'   => 'system/test',
        'status' => 'published',
    ]);

    Livewire::test(EditPageDetails::class, ['record' => $page->id])
        ->assertSuccessful()
        ->assertActionHidden(\Filament\Actions\DeleteAction::class);
});
