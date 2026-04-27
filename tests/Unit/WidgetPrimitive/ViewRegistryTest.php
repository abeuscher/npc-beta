<?php

use App\Models\Contact;
use App\Models\Template;
use App\WidgetPrimitive\ViewRegistry;
use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('forRecordType returns Views ordered by sort_order then created_at', function () {
    RecordDetailView::factory()->create(['record_type' => Contact::class, 'handle' => 'b', 'sort_order' => 1]);
    RecordDetailView::factory()->create(['record_type' => Contact::class, 'handle' => 'a', 'sort_order' => 0]);
    RecordDetailView::factory()->create(['record_type' => Template::class, 'handle' => 'unrelated', 'sort_order' => 0]);

    $registry = new ViewRegistry();
    $views = $registry->forRecordType(Contact::class);

    expect($views)->toHaveCount(2)
        ->and($views[0]->handle)->toBe('a')
        ->and($views[1]->handle)->toBe('b');
});

it('forRecordType returns an empty Collection for unknown record types', function () {
    $registry = new ViewRegistry();

    expect($registry->forRecordType('App\Models\Nonexistent'))->toBeEmpty();
});

it('findByHandle returns the matching View or null', function () {
    RecordDetailView::factory()->create(['record_type' => Contact::class, 'handle' => 'overview']);

    $registry = new ViewRegistry();

    expect($registry->findByHandle(Contact::class, 'overview'))->not->toBeNull()
        ->and($registry->findByHandle(Contact::class, 'overview')->handle)->toBe('overview')
        ->and($registry->findByHandle(Contact::class, 'missing'))->toBeNull()
        ->and($registry->findByHandle(Template::class, 'overview'))->toBeNull();
});

it('is registered as a singleton in the container', function () {
    $a = app(ViewRegistry::class);
    $b = app(ViewRegistry::class);

    expect($a)->toBe($b);
});
