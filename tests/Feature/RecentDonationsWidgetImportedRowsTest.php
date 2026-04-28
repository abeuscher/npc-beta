<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\User;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $this->resolver = app(ContractResolver::class);

    $this->crmEditor = User::factory()->create();
    $this->crmEditor->assignRole('crm_editor');
});

it('surfaces imported donations in the recent donations contract resolver', function () {
    $contact = Contact::factory()->create();
    $fund = Fund::factory()->create();

    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'status'     => 'active',
        'source'     => Source::IMPORT,
        'amount'     => '75.00',
        'started_at' => now()->subDay(),
    ]);

    $this->actingAs($this->crmEditor);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: [
            'donation_id',
            'donation_amount',
            'donation_status',
        ],
        filters: [],
        model: 'donation',
        requiredPermission: 'view_donation',
    );

    $dto = $this->resolver->resolve(
        [$contract],
        new SlotContext(new RecordDetailAmbientContext($contact), publicSurface: false),
    )[0];

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['donation_id'])->toBe((string) $donation->id)
        ->and($dto['items'][0]['donation_status'])->toBe('active')
        ->and($dto['items'][0]['donation_amount'])->toBe('75.00');
});
