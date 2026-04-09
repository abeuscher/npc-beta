<?php

use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('serves the home page at /', function () {
    Page::factory()->create([
        'title'        => 'Home',
        'slug'         => 'home',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')->assertOk()->assertSee('Home');
});

it('serves a published page at /{slug}', function () {
    Page::factory()->create([
        'title'        => 'About Us',
        'slug'         => 'about',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get('/about')->assertOk()->assertSee('About Us');
});

it('serves a published page with a nested slug', function () {
    Page::factory()->create([
        'title'        => 'Board Meeting',
        'slug'         => 'events/board-meeting',
        'type'         => 'event',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get('/events/board-meeting')->assertOk()->assertSee('Board Meeting');
});

it('returns 404 for an unpublished page', function () {
    Page::factory()->create([
        'title'        => 'Draft Page',
        'slug'         => 'draft',
        'status' => 'draft',
    ]);

    $this->get('/draft')->assertNotFound();
});

it('returns 404 for a non-existent slug', function () {
    $this->get('/does-not-exist')->assertNotFound();
});

it('auto-generates a slug from the title', function () {
    $user = \App\Models\User::factory()->create();
    $page = Page::create([
        'author_id'    => $user->id,
        'title'        => 'Our Mission Statement',
        'status' => 'draft',
    ]);

    expect($page->slug)->toBe('our-mission-statement');
});

it('returns 404 at / when no published home page exists', function () {
    $this->get('/')->assertNotFound();
});

it('page type defaults to default', function () {
    $page = Page::factory()->create([
        'title'        => 'Simple Page',
        'slug'         => 'simple',
        'status' => 'draft',
    ]);

    expect($page->type)->toBe('default');
});

it('renders a page layout with grid CSS and child widgets', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create([
        'title'        => 'Layout Page',
        'slug'         => 'layout-test',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $layout = PageLayout::create([
        'page_id'       => $page->id,
        'label'         => 'Two Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [
            'grid_template_columns' => '2fr 1fr',
            'gap'                   => '1.5rem',
        ],
        'sort_order'    => 0,
    ]);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    PageWidget::create([
        'page_id'        => $page->id,
        'layout_id'      => $layout->id,
        'column_index'   => 0,
        'widget_type_id' => $wt->id,
        'label'          => 'Left',
        'config'         => array_merge($wt->getDefaultConfig(), ['content' => 'Left content']),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    PageWidget::create([
        'page_id'        => $page->id,
        'layout_id'      => $layout->id,
        'column_index'   => 1,
        'widget_type_id' => $wt->id,
        'label'          => 'Right',
        'config'         => array_merge($wt->getDefaultConfig(), ['content' => 'Right content']),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $response = $this->get('/layout-test');

    $response->assertOk();
    $response->assertSee('page-layout', false);
    $response->assertSee('display:grid', false);
    $response->assertSee('grid-template-columns:2fr 1fr', false);
    $response->assertSee('Left content', false);
    $response->assertSee('Right content', false);
});
