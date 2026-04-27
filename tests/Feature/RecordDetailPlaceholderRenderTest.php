<?php

use App\Models\Contact;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('renders the placeholder with the contact id and type tokens substituted', function () {
    $contact = Contact::factory()->create();

    $wt = WidgetType::where('handle', 'record_detail_placeholder')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => [],
    ]);
    $pw->setRelation('widgetType', $wt);

    $rendered = WidgetRenderer::render($pw, [], [], 'record_detail_sidebar', $contact);

    expect($rendered['html'])
        ->toContain('Record detail sidebar — Contact #' . $contact->id);
});

it('returns the empty render shape when the record is null on record_detail_sidebar (fail-closed)', function () {
    $wt = WidgetType::where('handle', 'record_detail_placeholder')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => [],
    ]);
    $pw->setRelation('widgetType', $wt);

    $rendered = WidgetRenderer::render($pw, [], [], 'record_detail_sidebar', null);

    expect($rendered)->toBe(['html' => null, 'styles' => '', 'scripts' => '']);
});
