<?php

use App\Models\Contact;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\RecordContextProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the full closed token map for a real record', function () {
    $contact = Contact::factory()->create();

    $contract = new DataContract(version: '1.0.0', source: DataContract::SOURCE_RECORD_CONTEXT);

    $dto = app(RecordContextProjector::class)->project($contract, $contact);

    expect($dto)->toBe([
        'record_id'   => (string) $contact->id,
        'record_type' => 'Contact',
    ]);
});

it('returns the closed token map with empty strings when the record is null (fail-closed)', function () {
    $contract = new DataContract(version: '1.0.0', source: DataContract::SOURCE_RECORD_CONTEXT);

    $dto = app(RecordContextProjector::class)->project($contract, null);

    expect($dto)->toBe([
        'record_id'   => '',
        'record_type' => '',
    ]);
});
