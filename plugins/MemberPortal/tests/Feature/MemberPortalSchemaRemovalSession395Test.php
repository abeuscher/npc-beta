<?php

use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Models\Contact;
use App\Models\PortalAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Fixtures\Plugins\MemberPortalRemovedTestCase;

uses(MemberPortalRemovedTestCase::class, RefreshDatabase::class);

// Session 395 (Plugin Architecture arc D5): the squash-boundary redraw —
// removed-mirror side. With the MemberPortal line stripped, the contact edit
// screen renders clean without the Portal Access surface (the
// composition-safety gates on the member-portal capability: the
// ContactResource section, the four EditContact portal actions), while the
// fixture's migrator path keeps the schema present for the data-kept
// assertions (disabled ≠ uninstalled; a never-installed composition is
// proven by the per-composition fresh-install identity check, not here).

it('keeps the two portal tables through the fixture migrator path', function () {
    foreach (['portal_accounts', 'portal_password_reset_tokens'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist — disabled ≠ uninstalled");
    }
});

it('keeps portal account data queryable when the portal is absent', function () {
    $contact = Contact::factory()->create();
    PortalAccount::create([
        'contact_id' => $contact->id,
        'email'      => $contact->email,
        'password'   => 'irrelevant-hash',
        'is_active'  => true,
    ]);

    expect(PortalAccount::where('email', $contact->email)->exists())->toBeTrue();
});

it('renders the contact edit screen clean without the Portal Access surface when the portal is absent', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    // Data kept: a portal account row exists, but the gated section and the
    // four gated actions must not surface it — their absence proves the
    // capability gates, not an empty table.
    $contact = Contact::factory()->create();
    PortalAccount::create([
        'contact_id' => $contact->id,
        'email'      => $contact->email,
        'password'   => 'irrelevant-hash',
        'is_active'  => true,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(EditContact::class, ['record' => $contact->getRouteKey()])
        ->assertOk();

    expect($component->html())->not->toContain('Portal Access')
        ->not->toContain('Suspend portal access');
});
