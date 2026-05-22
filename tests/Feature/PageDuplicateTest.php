<?php

use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use App\Models\WidgetType;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('super_admin');
    $this->actingAs($this->user);
});

it('duplicate produces a draft copy with -copy slug authored by the current user', function () {
    $author = User::factory()->create();
    $original = Page::factory()->create([
        'title'        => 'Annual Report',
        'slug'         => 'annual-report',
        'type'         => 'default',
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'author_id'    => $author->id,
        'source'       => Source::SCRUB_DATA,
    ]);

    $copy = $original->duplicate();

    expect($copy->id)->not->toBe($original->id);
    expect($copy->title)->toBe('Copy of Annual Report');
    expect($copy->slug)->toBe('annual-report-copy');
    expect($copy->status)->toBe('draft');
    expect($copy->published_at)->toBeNull();
    expect($copy->author_id)->toBe($this->user->id);
    expect($copy->source)->toBe(Source::HUMAN);
    expect($copy->type)->toBe('default');
});

it('duplicate increments the slug when -copy is already taken', function () {
    $original = Page::factory()->create(['slug' => 'about']);
    Page::factory()->create(['slug' => 'about-copy']);

    $copy = $original->duplicate();

    expect($copy->slug)->toBe('about-copy-2');
});

it('duplicate clones the widget stack including layouts and nested widgets', function () {
    $original = Page::factory()->create(['type' => 'default']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $original->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'Root'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $layout = $original->layouts()->create([
        'label'      => 'Two Col',
        'display'    => 'grid',
        'columns'    => 2,
        'sort_order' => 1,
    ]);

    $original->widgets()->create([
        'widget_type_id' => $wt->id,
        'layout_id'      => $layout->id,
        'column_index'   => 0,
        'config'         => ['heading' => 'Left'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $copy = $original->duplicate();

    expect($copy->widgets()->count())->toBe(2);
    expect($copy->layouts()->count())->toBe(1);

    $nested = $copy->widgets()->whereNotNull('layout_id')->first();
    expect($nested->config)->toBe(['heading' => 'Left']);
    expect($nested->layout->owner_id)->toBe($copy->id);
});

it('duplicate re-points tags to the same tag rows without cloning them', function () {
    $original = Page::factory()->create();
    $tag = Tag::factory()->create();
    $original->tags()->attach($tag);

    $copy = $original->duplicate();

    expect($copy->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
    expect(Tag::count())->toBe(1);
});

it('list-level duplicate action creates a copy and redirects to its editor', function () {
    $original = Page::factory()->create(['slug' => 'mission', 'type' => 'default']);

    Livewire::actingAs($this->user)
        ->test(PageResource\Pages\ListPages::class)
        ->callTableAction('duplicate', $original)
        ->assertRedirect(PageResource::getUrl('edit', [
            'record' => Page::where('slug', 'mission-copy')->firstOrFail(),
        ]));

    expect(Page::where('slug', 'mission-copy')->where('status', 'draft')->exists())->toBeTrue();
});

it('duplicate action is hidden for system pages', function () {
    $system = Page::factory()->create(['type' => 'system', 'slug' => 'system/maintenance']);

    Livewire::actingAs($this->user)
        ->test(PageResource\Pages\ListPages::class)
        ->assertTableActionHidden('duplicate', $system);
});
