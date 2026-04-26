<?php

use App\Models\Event;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('renders events starting in the next seven days and omits events further out', function () {
    Event::factory()->create(['title' => 'Tomorrow Talk',   'starts_at' => now()->addDay(),     'status' => 'published']);
    Event::factory()->create(['title' => 'Late Week Lunch', 'starts_at' => now()->addDays(6),   'status' => 'published']);
    Event::factory()->create(['title' => 'Far Future Gala', 'starts_at' => now()->addDays(30),  'status' => 'published']);
    Event::factory()->create(['title' => 'Draft Workshop',  'starts_at' => now()->addDays(2),   'status' => 'draft']);

    $wt = WidgetType::where('handle', 'this_weeks_events')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['days_ahead' => 7],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)
        ->toContain('Tomorrow Talk')
        ->toContain('Late Week Lunch')
        ->not->toContain('Far Future Gala')
        ->not->toContain('Draft Workshop');
});

it('renders the empty-state copy when no events match the window', function () {
    Event::factory()->create(['title' => 'Far Future Only', 'starts_at' => now()->addDays(60), 'status' => 'published']);

    $wt = WidgetType::where('handle', 'this_weeks_events')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['days_ahead' => 7],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)->toContain('No events in the next 7 days');
});

it('the ContractResolver returns only whitelisted Event fields (fail-closed on undeclared columns)', function () {
    Event::factory()->create([
        'title'     => 'Gate Test',
        'starts_at' => now()->addDay(),
        'status'    => 'published',
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['title', 'internal_notes', 'internal_email'],
        filters: ['date_range' => ['from' => 'now', 'to' => '+7 days']],
        model: 'event',
    );

    $ctx = new SlotContext(new PageContext(), null, publicSurface: false);
    $dto = app(ContractResolver::class)->resolve([$contract], $ctx)[0];

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['title'])->toBe('Gate Test')
        ->and($dto['items'][0]['internal_notes'])->toBe('')
        ->and($dto['items'][0]['internal_email'])->toBe('')
        ->and($dto['items'][0])->toHaveKeys(['title', 'internal_notes', 'internal_email'])
        ->and($dto['items'][0])->not->toHaveKey('description')
        ->and($dto['items'][0])->not->toHaveKey('author_id')
        ->and($dto['items'][0])->not->toHaveKey('price');
});

it('the ContractResolver date_range filter clips events outside the window', function () {
    Event::factory()->create(['title' => 'Inside',  'starts_at' => now()->addDay(),    'status' => 'published']);
    Event::factory()->create(['title' => 'Outside', 'starts_at' => now()->addDays(30), 'status' => 'published']);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['title'],
        filters: ['date_range' => ['from' => 'now', 'to' => '+7 days']],
        model: 'event',
    );

    $ctx = new SlotContext(new PageContext(), null, publicSurface: false);
    $dto = app(ContractResolver::class)->resolve([$contract], $ctx)[0];

    $titles = array_column($dto['items'], 'title');

    expect($titles)->toBe(['Inside']);
});

it('an unknown system model handle returns an empty item set', function () {
    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['title'],
        model: 'widget_type_never_heard_of',
    );

    $ctx = new SlotContext(new PageContext(), null, publicSurface: false);
    $dto = app(ContractResolver::class)->resolve([$contract], $ctx)[0];

    expect($dto)->toBe(['items' => []]);
});
