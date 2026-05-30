<?php

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\EditPageDetails;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\PostResource\Pages\EditPostDetails;
use App\Models\Page;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function lockUser(array $permissions): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);

    return $user;
}

function lockSuperAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');

    return $user;
}

function lockPage(bool $locked = true): Page
{
    return Page::factory()->create([
        'title'  => 'Lock Test Page',
        'slug'   => 'lock-test-' . uniqid(),
        'status' => 'published',
        'locked' => $locked,
    ]);
}

function lockApiPrefix(): string
{
    return '/' . config('filament.path', env('ADMIN_PATH', 'admin')) . '/api/page-builder';
}

// ── Column default ─────────────────────────────────────────────────────────

it('defaults locked to false (concrete value) on a freshly created page', function () {
    $page = Page::factory()->create();

    expect($page->locked)->toBeFalse();
    $this->assertDatabaseHas('pages', ['id' => $page->id, 'locked' => false]);
});

// ── PagePolicy::update / delete ──────────────────────────────────────────────

it('denies updating a locked page to a user lacking edit_locked_pages', function () {
    $user = lockUser(['view_page', 'update_page', 'delete_page']);

    expect($user->can('update', lockPage()))->toBeFalse();
    expect($user->can('delete', lockPage()))->toBeFalse();
});

it('allows updating a locked page to a user holding edit_locked_pages', function () {
    $user = lockUser(['view_page', 'update_page', 'delete_page', 'edit_locked_pages']);

    expect($user->can('update', lockPage()))->toBeTrue();
    expect($user->can('delete', lockPage()))->toBeTrue();
});

it('allows super_admin to update a locked page via the Gate::before bypass', function () {
    $user = lockSuperAdmin();

    expect($user->can('update', lockPage()))->toBeTrue();
    expect($user->can('delete', lockPage()))->toBeTrue();
});

it('leaves an unlocked page editable by a user with only update_page', function () {
    $user = lockUser(['view_page', 'update_page', 'delete_page']);

    expect($user->can('update', lockPage(locked: false)))->toBeTrue();
    expect($user->can('delete', lockPage(locked: false)))->toBeTrue();
});

// ── Page-builder save API ────────────────────────────────────────────────────

it('blocks page-builder writes on a locked page for a user lacking edit_locked_pages (403)', function () {
    $user = lockUser(['view_page', 'update_page']);
    $page = lockPage();
    $wt   = WidgetType::where('handle', 'text_block')->firstOrFail();

    $this->actingAs($user)
        ->postJson(lockApiPrefix() . "/pages/{$page->id}/widgets", ['widget_type_id' => $wt->id])
        ->assertStatus(403);

    $widget = $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => 'W',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $this->actingAs($user)
        ->putJson(lockApiPrefix() . "/widgets/{$widget->id}", ['label' => 'changed'])
        ->assertStatus(403);

    $this->actingAs($user)
        ->deleteJson(lockApiPrefix() . "/widgets/{$widget->id}")
        ->assertStatus(403);

    $this->actingAs($user)
        ->postJson(lockApiPrefix() . "/pages/{$page->id}/layouts", ['columns' => 2])
        ->assertStatus(403);
});

it('allows page-builder writes on a locked page for a user holding edit_locked_pages', function () {
    $user = lockUser(['view_page', 'update_page', 'edit_locked_pages']);
    $page = lockPage();
    $wt   = WidgetType::where('handle', 'text_block')->firstOrFail();

    $this->actingAs($user)
        ->postJson(lockApiPrefix() . "/pages/{$page->id}/widgets", ['widget_type_id' => $wt->id])
        ->assertCreated();
});

it('still allows page-builder writes on an unlocked page for a plain update_page user', function () {
    $user = lockUser(['view_page', 'update_page']);
    $page = lockPage(locked: false);
    $wt   = WidgetType::where('handle', 'text_block')->firstOrFail();

    $this->actingAs($user)
        ->postJson(lockApiPrefix() . "/pages/{$page->id}/widgets", ['widget_type_id' => $wt->id])
        ->assertCreated();
});

// ── Filament delete protection ───────────────────────────────────────────────

it('makes PageResource::canDelete honour the lock', function () {
    $page = lockPage();

    $this->actingAs(lockUser(['view_page', 'update_page', 'delete_page']));
    expect(PageResource::canDelete($page))->toBeFalse();

    $this->actingAs(lockUser(['view_page', 'update_page', 'delete_page', 'edit_locked_pages']));
    expect(PageResource::canDelete($page))->toBeTrue();
});

it('skips a locked page when a non-permitted user runs the bulk delete action', function () {
    $locked = lockPage();
    $plain  = lockUser(['view_any_page', 'view_page', 'update_page', 'delete_page']);

    Livewire::actingAs($plain)
        ->test(ListPages::class)
        ->callTableBulkAction('delete', [$locked]);

    expect($locked->fresh()->trashed())->toBeFalse();
});

// ── Permission-gated toggle visibility ───────────────────────────────────────

it('shows the lock toggle on the page edit form only to holders of edit_locked_pages', function () {
    $page = lockPage(locked: false);

    $editor = lockUser(['view_any_page', 'view_page', 'update_page', 'edit_locked_pages']);
    Livewire::actingAs($editor)
        ->test(EditPageDetails::class, ['record' => $page->id])
        ->assertSee('Lock editing (Published');

    $plain = lockUser(['view_any_page', 'view_page', 'update_page']);
    Livewire::actingAs($plain)
        ->test(EditPageDetails::class, ['record' => $page->id])
        ->assertDontSee('Lock editing (Published');
});

it('shows the lock toggle on the post edit form only to holders of edit_locked_pages', function () {
    $post = Page::factory()->create([
        'type'   => 'post',
        'slug'   => 'lock-post-' . uniqid(),
        'status' => 'published',
        'locked' => false,
    ]);

    // Posts are the Page model, so EditPostDetails authorizes via PagePolicy
    // (*_page permissions); real post-editing roles hold both sets.
    $editor = lockUser(['view_any_post', 'view_post', 'view_any_page', 'view_page', 'update_page', 'edit_locked_pages']);
    Livewire::actingAs($editor)
        ->test(EditPostDetails::class, ['record' => $post->id])
        ->assertSee('Lock editing (Published');

    $plain = lockUser(['view_any_post', 'view_post', 'view_any_page', 'view_page', 'update_page']);
    Livewire::actingAs($plain)
        ->test(EditPostDetails::class, ['record' => $post->id])
        ->assertDontSee('Lock editing (Published');
});
