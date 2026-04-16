<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function sparseUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view_page', 'update_page']);
    return $user;
}

function sparsePage(): Page
{
    return Page::factory()->create([
        'title'  => 'Sparse',
        'slug'   => 'sparse-' . uniqid(),
        'status' => 'published',
    ]);
}

function sparseNavWidget(Page $page, array $config = []): PageWidget
{
    $wt = WidgetType::where('handle', 'nav')->firstOrFail();
    return $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'label'          => 'Nav',
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

it('strips keys equal to the resolved default on update', function () {
    $user = sparseUser();
    $page = sparsePage();
    $pw   = sparseNavWidget($page);

    $this->actingAs($user)
        ->putJson("/admin/api/page-builder/widgets/{$pw->id}", [
            'config' => [
                'link_color'  => '#1d4ed8', // equals default — should strip
                'hover_color' => '#abcdef', // differs — should keep
            ],
        ])
        ->assertOk();

    $pw->refresh();
    expect($pw->config)->not->toHaveKey('link_color');
    expect($pw->config['hover_color'])->toBe('#abcdef');
});

it('keeps keys that differ from the resolved default on update', function () {
    $user = sparseUser();
    $page = sparsePage();
    $pw   = sparseNavWidget($page);

    $this->actingAs($user)
        ->putJson("/admin/api/page-builder/widgets/{$pw->id}", [
            'config' => [
                'link_color'    => '#ff0000',
                'branding_type' => 'text',
                'branding_text' => 'Hello',
            ],
        ])
        ->assertOk();

    $pw->refresh();
    expect($pw->config['link_color'])->toBe('#ff0000');
    expect($pw->config['branding_type'])->toBe('text');
    expect($pw->config['branding_text'])->toBe('Hello');
});

it('creates new widgets with an empty config (sparse from birth — API)', function () {
    $user = sparseUser();
    $page = sparsePage();
    $wt   = WidgetType::where('handle', 'nav')->firstOrFail();

    $response = $this->actingAs($user)
        ->postJson("/admin/api/page-builder/pages/{$page->id}/widgets", [
            'widget_type_id' => $wt->id,
        ])
        ->assertCreated();

    $id = $response->json('widget.id');
    $pw = PageWidget::findOrFail($id);
    expect($pw->config)->toBe([]);
});

it('creates new widgets with an empty config (sparse from birth — Livewire)', function () {
    $user = sparseUser();
    $this->actingAs($user);
    $page = sparsePage();
    $wt   = WidgetType::where('handle', 'nav')->firstOrFail();

    \Livewire\Livewire::test(\App\Livewire\PageBuilder::class, ['pageId' => $page->id])
        ->call('openAddModal', null, null, null)
        ->call('createBlock', $wt->id);

    $pw = PageWidget::forOwner($page)
        ->where('widget_type_id', $wt->id)
        ->firstOrFail();

    expect($pw->config)->toBe([]);
});

it('includes resolved_defaults in the widget payload', function () {
    $user = sparseUser();
    $page = sparsePage();
    $pw   = sparseNavWidget($page, ['link_color' => '#ff0000']);

    $response = $this->actingAs($user)
        ->getJson("/admin/api/page-builder/pages/{$page->id}/widgets")
        ->assertOk();

    $widget = collect($response->json('widgets'))->firstWhere('id', $pw->id);
    expect($widget)->not->toBeNull();
    expect($widget['resolved_defaults']['link_color'])->toBe('#1d4ed8');
    expect($widget['resolved_defaults']['hover_color'])->toBe('#60a5fa');
    expect($widget['config']['link_color'])->toBe('#ff0000');
});
