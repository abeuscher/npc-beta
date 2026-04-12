<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\AppearanceStyleComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── text-shadow ────────────────────────────────────────────────────────────

it('emits text-shadow when text.shadow is true', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);
    $wt = WidgetType::factory()->create(['handle' => 'shadow_test_' . uniqid()]);

    $pw = PageWidget::create([
        'page_id'           => $page->id,
        'widget_type_id'    => $wt->id,
        'label'             => 'Shadow Test',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => ['text' => ['shadow' => true]],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $result = (new AppearanceStyleComposer())->compose($pw);
    expect($result['inline_style'])->toContain('text-shadow:0 1px 3px rgba(0,0,0,0.6)');
});

it('does not emit text-shadow when text.shadow is absent', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);
    $wt = WidgetType::factory()->create(['handle' => 'no_shadow_' . uniqid()]);

    $pw = PageWidget::create([
        'page_id'           => $page->id,
        'widget_type_id'    => $wt->id,
        'label'             => 'No Shadow Test',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => ['text' => ['color' => '#333']],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $result = (new AppearanceStyleComposer())->compose($pw);
    expect($result['inline_style'])->not->toContain('text-shadow');
});

it('does not emit text-shadow when text.shadow is false', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);
    $wt = WidgetType::factory()->create(['handle' => 'shadow_false_' . uniqid()]);

    $pw = PageWidget::create([
        'page_id'           => $page->id,
        'widget_type_id'    => $wt->id,
        'label'             => 'Shadow False Test',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => ['text' => ['shadow' => false]],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $result = (new AppearanceStyleComposer())->compose($pw);
    expect($result['inline_style'])->not->toContain('text-shadow');
});
