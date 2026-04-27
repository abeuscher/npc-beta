<?php

use App\Models\Contact;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('dispatches SOURCE_RECORD_CONTEXT through the projector with the ambient record', function () {
    $contact = Contact::factory()->create();

    $contract = new DataContract(version: '1.0.0', source: DataContract::SOURCE_RECORD_CONTEXT);
    $ctx = new SlotContext(new RecordDetailAmbientContext($contact), publicSurface: false);

    $dto = app(ContractResolver::class)->resolve([$contract], $ctx)[0];

    expect($dto)->toBe([
        'record_id'   => (string) $contact->id,
        'record_type' => 'Contact',
    ]);
});

it('returns the empty token map when the ambient is not RecordDetailAmbientContext (fail-closed)', function () {
    $contract = new DataContract(version: '1.0.0', source: DataContract::SOURCE_RECORD_CONTEXT);
    $ctx = new SlotContext(new PageAmbientContext());

    $dto = app(ContractResolver::class)->resolve([$contract], $ctx)[0];

    expect($dto)->toBe([
        'record_id'   => '',
        'record_type' => '',
    ]);
});
