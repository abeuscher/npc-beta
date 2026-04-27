<?php

use App\Models\Contact;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\WidgetType;
use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('seeds contact_overview, page_template_header, and page_template_footer Views', function () {
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $overview = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->first();
    $header = RecordDetailView::query()
        ->where('record_type', Template::class)
        ->where('handle', 'page_template_header')
        ->first();
    $footer = RecordDetailView::query()
        ->where('record_type', Template::class)
        ->where('handle', 'page_template_footer')
        ->first();

    expect($overview)->not->toBeNull()
        ->and($overview->label)->toBe('Overview')
        ->and($header)->not->toBeNull()
        ->and($header->label)->toBe('Header')
        ->and($footer)->not->toBeNull()
        ->and($footer->label)->toBe('Footer');
});

it('attaches both recent_notes and membership_status widgets to contact_overview on fresh seed', function () {
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $overview = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->first();

    $handles = $overview->pageWidgets()
        ->with('widgetType')
        ->orderBy('sort_order')
        ->get()
        ->map(fn (PageWidget $pw) => $pw->widgetType->handle)
        ->all();

    expect($handles)->toBe(['recent_notes', 'membership_status']);
});

it('is idempotent across re-runs — exactly one of each widget on contact_overview', function () {
    (new \Database\Seeders\RecordDetailViewSeeder())->run();
    (new \Database\Seeders\RecordDetailViewSeeder())->run();
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $overview = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->first();

    $counts = $overview->pageWidgets()
        ->with('widgetType')
        ->get()
        ->groupBy(fn (PageWidget $pw) => $pw->widgetType->handle)
        ->map(fn ($group) => $group->count())
        ->all();

    expect($counts)->toBe([
        'recent_notes'      => 1,
        'membership_status' => 1,
    ]);
});

it('adds membership_status to an install that has only recent_notes attached (5d-1 migration path)', function () {
    $view = RecordDetailView::create([
        'record_type' => Contact::class,
        'handle'      => 'contact_overview',
        'label'       => 'Overview',
        'sort_order'  => 0,
    ]);
    $recentNotes = WidgetType::where('handle', 'recent_notes')->firstOrFail();
    PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'layout_id'         => null,
        'column_index'      => null,
        'widget_type_id'    => $recentNotes->id,
        'label'             => 'Recent Notes',
        'config'            => ['limit' => 5],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $handles = $view->pageWidgets()
        ->with('widgetType')
        ->orderBy('sort_order')
        ->get()
        ->map(fn (PageWidget $pw) => $pw->widgetType->handle)
        ->all();

    expect($handles)->toBe(['recent_notes', 'membership_status']);
});

it('seeds page-template chrome Views without widgets', function () {
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $header = RecordDetailView::query()
        ->where('record_type', Template::class)
        ->where('handle', 'page_template_header')
        ->first();

    expect($header->pageWidgets()->count())->toBe(0);
});
