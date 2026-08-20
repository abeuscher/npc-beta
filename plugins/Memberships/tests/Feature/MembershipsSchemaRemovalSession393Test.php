<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\PortalAccount;
use App\Models\User;
use Database\Seeders\SystemPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Plugins\MembershipsRemovedTestCase;

uses(MembershipsRemovedTestCase::class, RefreshDatabase::class);

// Session 393 (Plugin Architecture arc D4): the squash-boundary redraw —
// removed-mirror side. With the Memberships line stripped, the seeded portal
// signup page renders without the tier/checkout half (the composition-safety
// gates on the membership.checkout route — the resolver arm, the template,
// and the controller's tier handling all key off route presence), while the
// fixture's migrator path keeps the schema present for the data-kept
// assertions (disabled ≠ uninstalled; a never-installed composition is
// proven by the fresh-install identity check, not here).

it('keeps the two membership tables through the fixture migrator path', function () {
    foreach (['membership_tiers', 'memberships'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist — disabled ≠ uninstalled");
    }
});

it('renders the seeded signup page without the tier picker when memberships are absent', function () {
    User::factory()->create();
    $this->seed(SystemPageSeeder::class);
    // Data kept: a tier row exists, but the gated resolver arm must not offer
    // it — the picker's absence proves the gate, not an empty table.
    MembershipTier::factory()->create(['is_active' => true, 'is_archived' => false]);

    $this->get('/signup')
        ->assertOk()
        ->assertDontSee('Membership tier');
});

it('accepts a signup carrying a tier_id and creates no membership when memberships are absent', function () {
    $tier = MembershipTier::factory()->create([
        'is_active'     => true,
        'is_archived'   => false,
        'default_price' => null,
    ]);

    $this->post(route('portal.signup.post'), [
        'first_name'            => 'Gate',
        'last_name'             => 'Proof',
        'email'                 => 'gateproof@example.com',
        'password'              => 'longsecurepass1',
        'password_confirmation' => 'longsecurepass1',
        'tier_id'               => $tier->id,
        '_form_start'           => time() - 10,
    ])->assertRedirect(route('portal.verification.notice'));

    $contact = Contact::where('email', 'gateproof@example.com')->first();

    expect(PortalAccount::where('email', 'gateproof@example.com')->exists())->toBeTrue()
        ->and($contact)->not->toBeNull()
        ->and(Membership::where('contact_id', $contact->id)->exists())->toBeFalse();
});
