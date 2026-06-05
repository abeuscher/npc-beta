<?php

use App\Models\Donation;
use App\Models\MailingList;
use App\Models\NavigationMenu;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\Template;
use App\Models\WidgetType;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Organization ─────────────────────────────────────────────────────────────

it('duplicates an organization with its own fields and tags but not its related records', function () {
    $org = Organization::factory()->create([
        'name'        => 'Acme Corp',
        'industry'    => 'Manufacturing',
        'source'      => Source::IMPORT,
        'external_id' => 'EXT-1',
    ]);
    $org->tags()->attach(Tag::create(['name' => 'Sponsor', 'type' => 'organization'])->id);
    Donation::factory()->create(['organization_id' => $org->id]);

    $copy = $org->duplicate();

    expect($copy->id)->not->toBe($org->id);
    expect($copy->name)->toBe('Copy of Acme Corp');
    expect($copy->industry)->toBe('Manufacturing');
    // Provenance + source reset on a hand-made copy.
    expect($copy->source)->toBe(Source::HUMAN);
    expect($copy->external_id)->toBeNull();
    // Tags carry; real related records do not.
    expect($copy->tags()->count())->toBe(1);
    expect($copy->donations()->count())->toBe(0);
});

// ── Template ─────────────────────────────────────────────────────────────────

it('duplicates a template with its owned widgets and never as the default', function () {
    $wt = WidgetType::create([
        'handle'                => 'dup_test_' . uniqid(),
        'label'                 => 'Dup Test',
        'render_mode'           => 'server',
        'collections'           => [],
        'config_schema'         => [],
        'background_full_width' => false,
        'content_full_width'    => false,
    ]);

    $tpl = Template::factory()->create([
        'name'        => 'Home',
        'type'        => 'page',
        'is_default'  => true,
        'custom_scss' => '.brand { color: red; }',
    ]);
    $tpl->widgets()->create([
        'widget_type_id'    => $wt->id,
        'layout_id'         => null,
        'column_index'      => null,
        'label'             => 'Hero',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $copy = $tpl->duplicate();

    expect($copy->id)->not->toBe($tpl->id);
    expect($copy->name)->toBe('Copy of Home');
    expect($copy->custom_scss)->toBe('.brand { color: red; }');
    expect($copy->is_default)->toBeFalse();
    expect($copy->widgets()->count())->toBe(1);
    // Original untouched.
    expect($tpl->fresh()->is_default)->toBeTrue();
});

// ── Navigation menu ──────────────────────────────────────────────────────────

it('duplicates a navigation menu with a unique handle and parent-remapped items', function () {
    $menu   = NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    $parent = $menu->items()->create(['label' => 'About', 'url' => '/about', 'sort_order' => 0]);
    $child  = $menu->items()->create(['label' => 'Team', 'url' => '/team', 'parent_id' => $parent->id, 'sort_order' => 0]);

    $copy = $menu->duplicate();

    expect($copy->label)->toBe('Copy of Main');
    expect($copy->handle)->toBe('main-copy');
    expect($copy->items()->count())->toBe(2);

    $newParent = $copy->items()->whereNull('parent_id')->first();
    $newChild  = $copy->items()->whereNotNull('parent_id')->first();
    // The child's parent_id is remapped onto the NEW parent row, not the old one.
    expect($newChild->parent_id)->toBe($newParent->id);
    expect($newParent->id)->not->toBe($parent->id);
    expect($newChild->id)->not->toBe($child->id);
});

it('increments the handle when the -copy handle already exists', function () {
    NavigationMenu::create(['label' => 'Main', 'handle' => 'main']);
    NavigationMenu::create(['label' => 'Main copy', 'handle' => 'main-copy']);

    $original = NavigationMenu::where('handle', 'main')->first();
    $copy     = $original->duplicate();

    expect($copy->handle)->toBe('main-copy-2');
});

// ── Mailing list ─────────────────────────────────────────────────────────────

it('duplicates a mailing list with its filter rules re-parented to the copy', function () {
    $list = MailingList::create(['name' => 'Active Donors', 'conjunction' => 'and']);
    $list->filters()->create(['field' => 'email', 'operator' => 'contains', 'value' => '@example.com', 'sort_order' => 0]);
    $list->filters()->create(['field' => 'first_name', 'operator' => 'is_not_empty', 'value' => null, 'sort_order' => 1]);

    $copy = $list->duplicate();

    expect($copy->id)->not->toBe($list->id);
    expect($copy->name)->toBe('Copy of Active Donors');
    expect($copy->conjunction)->toBe('and');
    expect($copy->filters()->count())->toBe(2);
    expect($copy->filters()->pluck('mailing_list_id')->unique()->all())->toBe([$copy->id]);
    // Original keeps its own filters.
    expect($list->filters()->count())->toBe(2);
});
