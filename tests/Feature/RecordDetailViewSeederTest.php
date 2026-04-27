<?php

use App\Models\Contact;
use App\Models\Template;
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

it('attaches the recent_notes widget to contact_overview only on first run', function () {
    (new \Database\Seeders\RecordDetailViewSeeder())->run();
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $overview = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->first();

    expect($overview->pageWidgets()->count())->toBe(1)
        ->and($overview->pageWidgets()->first()->widgetType->handle)->toBe('recent_notes');
});

it('seeds page-template chrome Views without widgets', function () {
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $header = RecordDetailView::query()
        ->where('record_type', Template::class)
        ->where('handle', 'page_template_header')
        ->first();

    expect($header->pageWidgets()->count())->toBe(0);
});
