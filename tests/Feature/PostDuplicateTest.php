<?php

use App\Filament\Resources\PostResource;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('super_admin');
    $this->actingAs($this->user);
});

it('list-level duplicate action creates a draft post copy and redirects to its editor', function () {
    $original = Page::factory()->create([
        'title'  => 'Spring Newsletter',
        'slug'   => 'spring-newsletter',
        'type'   => 'post',
        'status' => 'published',
    ]);

    Livewire::actingAs($this->user)
        ->test(PostResource\Pages\ListPosts::class)
        ->callTableAction('duplicate', $original)
        ->assertRedirect(PostResource::getUrl('edit', [
            'record' => Page::where('slug', 'spring-newsletter-copy')->firstOrFail(),
        ]));

    $copy = Page::where('slug', 'spring-newsletter-copy')->firstOrFail();
    expect($copy->type)->toBe('post');
    expect($copy->status)->toBe('draft');
    expect($copy->title)->toBe('Copy of Spring Newsletter');
});

it('post builder edit screen exposes the duplicate action in its ellipsis group', function () {
    $original = Page::factory()->create(['slug' => 'gala-recap', 'type' => 'post']);

    Livewire::actingAs($this->user)
        ->test(PostResource\Pages\EditPost::class, ['record' => $original->id])
        ->callAction('duplicatePost')
        ->assertRedirect(PostResource::getUrl('edit', [
            'record' => Page::where('slug', 'gala-recap-copy')->firstOrFail(),
        ]));

    expect(Page::where('slug', 'gala-recap-copy')->where('status', 'draft')->exists())->toBeTrue();
});
