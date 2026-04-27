<?php

use App\Models\Contact;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\SlotContext;
use App\WidgetPrimitive\Slots\RecordDetailSidebarSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('declares the record-detail-sidebar identity and null config surface', function () {
    $slot = new RecordDetailSidebarSlot();

    expect($slot->handle())->toBe('record_detail_sidebar')
        ->and($slot->label())->toBe('Record Detail Sidebar')
        ->and($slot->configSurface())->toBeNull();
});

it('reports compact, column-stackable, bounded appearance constraints', function () {
    $constraints = (new RecordDetailSidebarSlot())->layoutConstraints();

    expect($constraints)->toBe([
        'allowed_appearance_fields' => ['background', 'text'],
        'dimensions'                => null,
        'column_stackable'          => true,
        'full_width_allowed'        => false,
    ]);
});

it('builds a SlotContext carrying the record on a RecordDetailAmbientContext', function () {
    $contact = Contact::factory()->create();

    $ctx = (new RecordDetailSidebarSlot())->ambientContext($contact);

    expect($ctx)->toBeInstanceOf(SlotContext::class)
        ->and($ctx->ambient)->toBeInstanceOf(RecordDetailAmbientContext::class)
        ->and($ctx->ambient->record->id)->toBe($contact->id);
});

it('marks the record-detail surface as non-public (admin-only)', function () {
    $contact = Contact::factory()->create();

    $ctx = (new RecordDetailSidebarSlot())->ambientContext($contact);

    expect($ctx->publicSurface)->toBeFalse();
});

it('returns null from currentPage() — record-detail ambient is not a page ambient', function () {
    $contact = Contact::factory()->create();

    $ctx = (new RecordDetailSidebarSlot())->ambientContext($contact);

    expect($ctx->currentPage())->toBeNull();
});
