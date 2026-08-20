<?php

use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Models\Contact;
use App\Models\PortalAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 395 (Plugin Architecture arc D5): the squash-boundary redraw —
// enabled-twin side. The two portal tables are plugin-owned (created by the
// plugin's database/migrations via the provider's loadMigrationsFrom,
// contract surface 5 — the second sequence-carrying redraw), and the
// contact-admin Portal Access surface renders because the member-portal
// capability is present.

it('creates the two plugin-owned portal tables through the plugin migrations', function () {
    foreach (['portal_accounts', 'portal_password_reset_tokens'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist on the full composition");
    }
});

it('registers the plugin migration path on the migrator', function () {
    expect(app('migrator')->paths())->toContain(base_path('plugins/MemberPortal/database/migrations'));
});

it('writes a portal signup through the plugin-owned schema', function () {
    $this->post(route('portal.signup.post'), [
        'first_name'            => 'Schema',
        'last_name'             => 'Proof',
        'email'                 => 'schemaproof@example.com',
        'password'              => 'longsecurepass1',
        'password_confirmation' => 'longsecurepass1',
    ])->assertRedirect(route('portal.verification.notice'));

    $account = PortalAccount::where('email', 'schemaproof@example.com')->first();

    // The bigint PK defaults through the traveled portal_accounts_id_seq.
    expect($account)->not->toBeNull()
        ->and($account->id)->toBeInt()
        ->and($account->id)->toBeGreaterThanOrEqual(1);
});

it('renders the contact edit screen with the Portal Access section on the full composition', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $contact = Contact::factory()->create();
    PortalAccount::create([
        'contact_id' => $contact->id,
        'email'      => $contact->email,
        'password'   => 'irrelevant-hash',
        'is_active'  => true,
    ]);

    $html = Livewire::actingAs($admin)
        ->test(EditContact::class, ['record' => $contact->getRouteKey()])
        ->assertOk()
        ->html();

    expect($html)->toContain('Portal Access');
});
