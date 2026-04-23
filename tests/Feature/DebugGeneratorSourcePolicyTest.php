<?php

use App\Filament\Widgets\DashboardDebugGeneratorWidget;
use App\Models\Contact;
use App\Models\Donation;
use App\WidgetPrimitive\DataSink;
use App\WidgetPrimitive\Exceptions\SourceRejectedException;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('DebugGenerator writes contacts through DataSink with Source::DEMO (accepted)', function () {
    $widget = new DashboardDebugGeneratorWidget;
    $widget->type = 'contacts';
    $widget->quantity = 5;
    $widget->generate();

    expect(Contact::count())->toBe(5);
});

it('DebugGenerator writes members (Contact + Membership + PortalAccount) where Contact passes the DEMO policy', function () {
    $widget = new DashboardDebugGeneratorWidget;
    $widget->type = 'members';
    $widget->quantity = 3;
    $widget->generate();

    expect(Contact::count())->toBeGreaterThanOrEqual(3);
});

it('DebugGenerator writes blog posts (Pages) through DataSink with Source::DEMO', function () {
    \App\Models\User::factory()->create();

    $widget = new DashboardDebugGeneratorWidget;
    $widget->type = 'blog_posts';
    $widget->quantity = 2;
    $widget->generate();

    expect(\App\Models\Page::where('type', 'post')->count())->toBe(2);
});

it('proves the sink refuses a Donation write with Source::DEMO — the invariant that protects finance tables', function () {
    $sink = app(DataSink::class);

    $caught = null;
    try {
        $sink->write(Donation::class, Source::DEMO, [
            'contact_id' => Contact::factory()->create()->id,
            'type'       => 'one_off',
            'amount'     => 100.0,
            'currency'   => 'usd',
            'status'     => 'active',
        ]);
    } catch (SourceRejectedException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(SourceRejectedException::class)
        ->and(Donation::count())->toBe(0);
});
