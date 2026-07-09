<?php

use App\Models\CustomFieldDef;
use App\Models\EmailTemplate;
use App\Models\MembershipTier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
});

function makeUser(?string $role = null): User
{
    $user = User::factory()->create(['is_active' => true]);
    if ($role !== null) {
        $user->assignRole($role);
    }
    return $user;
}

// ── Bypass guards: no-role authenticated admin must not reach CRUD URLs ─────
// These probe the "no policy + no canX override = Filament default-allows"
// pattern. Without the resource-side gate, an active User with zero roles can
// reach /admin/{resource}/create + /edit. The fix is per-resource canCreate /
// canEdit / canDelete overrides that mirror the canAccess gate.

it('blocks no-role user from MembershipTier create URL', function () {
    $user = makeUser();

    $response = $this->actingAs($user)->get('/admin/membership-tiers/create');

    expect($response->getStatusCode())->toBe(403);
});

it('blocks no-role user from MembershipTier edit URL', function () {
    $user = makeUser();
    $tier = MembershipTier::factory()->create();

    $response = $this->actingAs($user)->get("/admin/membership-tiers/{$tier->id}/edit");

    expect($response->getStatusCode())->toBe(403);
});

it('allows developer (manage_membership_tiers holder) to reach MembershipTier create', function () {
    $user = makeUser('developer');

    $response = $this->actingAs($user)->get('/admin/membership-tiers/create');

    $response->assertOk();
});

it('blocks no-role user from CustomFieldDef create URL', function () {
    $user = makeUser();

    $response = $this->actingAs($user)->get('/admin/custom-field-defs/create');

    expect($response->getStatusCode())->toBe(403);
});

it('blocks no-role user from CustomFieldDef edit URL', function () {
    $user = makeUser();
    $def = CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'test_field',
        'label'      => 'Test Field',
        'field_type' => 'text',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)->get("/admin/custom-field-defs/{$def->id}/edit");

    expect($response->getStatusCode())->toBe(403);
});

it('allows developer (manage_custom_fields holder) to reach CustomFieldDef create', function () {
    $user = makeUser('developer');

    $response = $this->actingAs($user)->get('/admin/custom-field-defs/create');

    $response->assertOk();
});

it('blocks no-role user from EmailTemplate edit URL', function () {
    $user = makeUser();
    $template = EmailTemplate::create([
        'handle'  => 'test_template',
        'subject' => 'Test',
        'body'    => 'Test',
    ]);

    $response = $this->actingAs($user)->get("/admin/email-templates/{$template->id}/edit");

    expect($response->getStatusCode())->toBe(403);
});

it('allows developer (manage_email_templates holder) to reach EmailTemplate edit', function () {
    $user = makeUser('developer');
    $template = EmailTemplate::create([
        'handle'  => 'test_template',
        'subject' => 'Test',
        'body'    => 'Test',
    ]);

    $response = $this->actingAs($user)->get("/admin/email-templates/{$template->id}/edit");

    $response->assertOk();
});

// ── Load-bearing cross-role probes ──────────────────────────────────────────
// Pinned to fail loudly if seeder grants or resource gates drift.

it('blocks cms_editor from DonationResource list (no donation perms)', function () {
    $user = makeUser('cms_editor');

    $response = $this->actingAs($user)->get('/admin/donations');

    expect($response->getStatusCode())->toBe(403);
});

it('allows treasurer to reach DonationResource list', function () {
    $user = makeUser('treasurer');

    $response = $this->actingAs($user)->get('/admin/donations');

    $response->assertOk();
});

it('hard-denies DonationResource create — route is not registered', function () {
    // DonationResource::getPages() registers only `index` + `view` — no create or
    // edit page. Donations are write-only via StripeWebhookController; admin UI
    // is intentionally view-only. The unregistered route returns 404 (Laravel
    // route-not-found), not 403, which is the same effective denial.
    //
    // Treasurer has full donation policy perms via the seeder (create/update/
    // delete), but the resource doesn't expose a UI surface for those actions.
    $user = makeUser('treasurer');

    $response = $this->actingAs($user)->get('/admin/donations/create');

    expect($response->getStatusCode())->toBeIn([403, 404]);
});

it('blocks treasurer from PageResource create (no page perms)', function () {
    $user = makeUser('treasurer');

    $response = $this->actingAs($user)->get('/admin/pages/create');

    expect($response->getStatusCode())->toBe(403);
});

it('blocks blogger from ContactResource list (no contact perms)', function () {
    $user = makeUser('blogger');

    $response = $this->actingAs($user)->get('/admin/contacts');

    expect($response->getStatusCode())->toBe(403);
});

// ── super_admin Gate::before bypass ─────────────────────────────────────────

it('super_admin bypasses every resource list URL via Gate::before', function () {
    $user = makeUser('super_admin');

    foreach ([
        '/admin/contacts',
        '/admin/organizations',
        '/admin/pages',
        '/admin/donations',
        '/admin/membership-tiers',
        '/admin/custom-field-defs',
        '/admin/email-templates',
        '/admin/users',
        '/admin/roles',
    ] as $url) {
        $response = $this->actingAs($user)->get($url);
        expect($response->getStatusCode())->toBe(200);
    }
});

// ── RoleResource: super-admin-only gate ────────────────────────────────────

it('blocks developer (non-super-admin) from RoleResource list', function () {
    $user = makeUser('developer');

    $response = $this->actingAs($user)->get('/admin/roles');

    expect($response->getStatusCode())->toBe(403);
});

it('canEdit returns false for the super_admin role even when called by super_admin', function () {
    // The super_admin role itself is intentionally non-editable to prevent the
    // role from being misconfigured (its permissions are governed by
    // Gate::before, not by the role's permission grants). ReadOnlyAwareEditRecord
    // allows the page to load in read-only mode (super_admin can `view` via
    // Gate::before bypass on the no-policy Role model), but the canEdit guard
    // returns false so the save action is hidden and the form is disabled.
    $superAdminRole = Role::where('name', 'super_admin')->first();
    $user = makeUser('super_admin');

    $this->actingAs($user);

    expect(\App\Filament\Resources\RoleResource::canEdit($superAdminRole))->toBeFalse();
});

// ── manage_account: seeded, granted to NO shipped role (session 367 / CB2) ──────
// The deliberate version of the "unassigned permission" shape session-280
// Finding 1 flagged as an accident. Documented as intentional in the matrix.

function putActiveBillingState(): void
{
    Storage::fake('local');
    Storage::disk('local')->put('fleet/billing-state.json', json_encode([
        'schema_version'        => 1,
        'as_of'                 => '2026-07-08T12:00:00+00:00',
        'status'                => 'active',
        'plan'                  => ['name' => 'Standard', 'amount' => 4900, 'currency' => 'usd', 'interval' => 'month'],
        'billing_contact_email' => 'billing@example.org',
        'portal_url'            => 'https://billing.stripe.com/p/session/test_123',
    ]));
}

it('seeds manage_account but grants it to no shipped role (super-admin-only by design)', function () {
    // The ability exists in the vocabulary...
    expect(Permission::where('name', 'manage_account')->where('guard_name', 'web')->exists())->toBeTrue();

    // ...but no shipped role holds it — super-admin-only via the Gate::before
    // bypass unless a client adds it to a custom role.
    $rolesWithIt = Role::whereHas('permissions', fn ($q) => $q->where('name', 'manage_account'))
        ->pluck('name')
        ->all();

    expect($rolesWithIt)->toBe([]);
});

it('allows super_admin to reach AccountPage when a billing-state document is present', function () {
    putActiveBillingState();
    $user = makeUser('super_admin');

    $this->actingAs($user)->get('/admin/account-page')->assertOk();
});

it('denies a role without manage_account (developer) from AccountPage even with a document present', function () {
    putActiveBillingState();
    $user = makeUser('developer');

    expect($this->actingAs($user)->get('/admin/account-page')->getStatusCode())->toBe(403);
});

it('self-hides AccountPage (403) even from super_admin when no billing-state document exists', function () {
    Storage::fake('local'); // no document pushed — internal / fresh install
    $user = makeUser('super_admin');

    expect($this->actingAs($user)->get('/admin/account-page')->getStatusCode())->toBe(403);
});
