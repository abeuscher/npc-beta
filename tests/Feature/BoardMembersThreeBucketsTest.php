<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Widget type seeder ──────────────────────────────────────────────────────

it('seeder creates board_members widget with correct config schema', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'board_members')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Board Members')
        ->and($wt->category)->toBe(['content'])
        ->and($wt->collections)->toBe(['members']);

    $keys = collect($wt->config_schema)->pluck('key')->all();
    expect($keys)->toContain('heading')
        ->toContain('collection_handle')
        ->toContain('image_field')
        ->toContain('name_field')
        ->toContain('title_field')
        ->toContain('department_field')
        ->toContain('description_field')
        ->toContain('linkedin_field')
        ->toContain('github_field')
        ->toContain('extra_url_field')
        ->toContain('extra_url_label_field')
        ->toContain('items_per_row')
        ->toContain('row_alignment')
        ->toContain('image_shape')
        ->toContain('image_aspect_ratio')
        ->toContain('grid_background_color')
        ->not->toContain('background_color')
        ->toContain('pane_color')
        ->toContain('border_color')
        ->toContain('border_radius');

    $appearanceFields = collect($wt->config_schema)->filter(fn ($f) => ($f['group'] ?? null) === 'appearance');
    $contentFields    = collect($wt->config_schema)->filter(fn ($f) => ($f['group'] ?? null) === 'content');
    // Color fields are in 'appearance'
    expect($appearanceFields->pluck('key'))
        ->toContain('grid_background_color')
        ->toContain('pane_color')
        ->toContain('border_color');
    expect($contentFields->pluck('key'))
        ->toContain('heading')
        ->toContain('collection_handle');
});

it('seeder creates three_buckets widget with correct config schema', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'three_buckets')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Three Buckets')
        ->and($wt->category)->toBe(['content', 'layout'])
        ->and($wt->collections)->toBe([]);

    $keys = collect($wt->config_schema)->pluck('key')->all();
    expect($keys)->toContain('heading_1')
        ->toContain('body_1')
        ->toContain('ctas_1')
        ->toContain('heading_2')
        ->toContain('body_2')
        ->toContain('ctas_2')
        ->toContain('heading_3')
        ->toContain('body_3')
        ->toContain('ctas_3')
        ->toContain('heading_alignment')
        ->toContain('body_alignment')
        ->toContain('button_alignment')
        ->toContain('gap');
});

// ── Board members demo seeder ───────────────────────────────────────────────

it('board members demo seeder creates collection and items', function () {
    $this->artisan('db:seed', ['--class' => 'App\\Widgets\\BoardMembers\\DemoSeeder']);

    $collection = Collection::where('handle', 'board-members-demo')->first();

    expect($collection)->not->toBeNull()
        ->and($collection->name)->toBe('Board Members Demo')
        ->and($collection->is_public)->toBeTrue();

    $fieldKeys = collect($collection->fields)->pluck('key')->all();
    expect($fieldKeys)->toContain('name')
        ->toContain('photo')
        ->toContain('job_title')
        ->toContain('bio')
        ->toContain('linkedin')
        ->toContain('github');

    $items = CollectionItem::where('collection_id', $collection->id)->get();
    expect($items)->toHaveCount(6);
    expect($items->first()->is_published)->toBeTrue();
});

// ── Board members blade rendering ───────────────────────────────────────────

it('board members template renders member cards with semantic HTML', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Test Members',
        'handle'      => 'test-members',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name',     'type' => 'text', 'label' => 'Name'],
            ['key' => 'title',    'type' => 'text', 'label' => 'Title'],
            ['key' => 'dept',     'type' => 'text', 'label' => 'Department'],
            ['key' => 'bio',      'type' => 'text', 'label' => 'Bio'],
            ['key' => 'linkedin', 'type' => 'text', 'label' => 'LinkedIn'],
            ['key' => 'github',   'type' => 'text', 'label' => 'GitHub'],
        ],
    ]);

    CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => [
            'name'     => 'Jane Doe',
            'title'    => 'Executive Director',
            'dept'     => 'Leadership',
            'bio'      => '<p>Experienced leader.</p>',
            'linkedin' => 'https://linkedin.com/in/janedoe',
            'github'   => 'https://github.com/janedoe',
        ],
        'sort_order'   => 0,
        'is_published' => true,
    ]);

    $page = Page::factory()->create(['slug' => 'board-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'board_members')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'           => 'Our Board',
            'collection_handle' => 'test-members',
            'name_field'        => 'name',
            'title_field'       => 'title',
            'department_field'  => 'dept',
            'description_field' => 'bio',
            'linkedin_field'    => 'linkedin',
            'github_field'      => 'github',
            'items_per_row'     => 3,
            'row_alignment'     => 'center',
            'image_shape'       => 'circle',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/board-test');

    $response->assertOk();
    $response->assertSee('Our Board');
    $response->assertSee('Jane Doe');
    $response->assertSee('Executive Director');
    $response->assertSee('Leadership');
    $response->assertSee('<p>Experienced leader.</p>', false);
    $response->assertSee('<article', false);
    $response->assertSee('linkedin.com/in/janedoe');
    $response->assertSee('github.com/janedoe');
});

// ── Three buckets blade rendering ───────────────────────────────────────────

it('three buckets template renders three columns with headings body and buttons', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $page = Page::factory()->create(['slug' => 'buckets-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'three_buckets')->first();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading_1' => 'Mission',
            'body_1'    => '<p>We serve the community.</p>',
            'ctas_1'    => [['text' => 'Learn More', 'url' => '/about', 'style' => 'primary']],
            'heading_2' => 'Vision',
            'body_2'    => '<p>A better tomorrow.</p>',
            'ctas_2'    => [['text' => 'Our Plan', 'url' => '/plan', 'style' => 'secondary']],
            'heading_3' => 'Values',
            'body_3'    => '<p>Integrity and trust.</p>',
            'ctas_3'    => [],
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/buckets-test');

    $response->assertOk();
    $response->assertSee('Mission');
    $response->assertSee('Vision');
    $response->assertSee('Values');
    $response->assertSee('<p>We serve the community.</p>', false);
    $response->assertSee('<p>A better tomorrow.</p>', false);
    $response->assertSee('<p>Integrity and trust.</p>', false);
    $response->assertSee('Learn More');
    $response->assertSee('Our Plan');
    $response->assertSee('widget-three-buckets', false);
    $response->assertSee('three-buckets__bucket', false);
});
