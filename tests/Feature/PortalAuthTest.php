<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\PortalAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

// ── Login ─────────────────────────────────────────────────────────────────────

it('logs in with valid credentials via the portal guard', function () {
    $contact = Contact::factory()->create(['email' => 'member@example.com']);
    $account = PortalAccount::factory()->create([
        'contact_id' => $contact->id,
        'email'      => 'member@example.com',
        'password'   => 'securepassword1',
        'is_active'  => true,
    ]);

    $response = $this->post(route('portal.login.post'), [
        'email'    => 'member@example.com',
        'password' => 'securepassword1',
    ]);

    $response->assertRedirect(route('portal.account'));
    $this->assertAuthenticatedAs($account, 'portal');
});

it('rejects login for inactive accounts', function () {
    $contact = Contact::factory()->create(['email' => 'inactive@example.com']);
    PortalAccount::factory()->create([
        'contact_id' => $contact->id,
        'email'      => 'inactive@example.com',
        'password'   => 'securepassword1',
        'is_active'  => false,
    ]);

    $response = $this->post(route('portal.login.post'), [
        'email'    => 'inactive@example.com',
        'password' => 'securepassword1',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $this->assertGuest('portal');
});

it('rejects login with invalid credentials', function () {
    Contact::factory()->create(['email' => 'user@example.com']);
    PortalAccount::factory()->create([
        'email'    => 'user@example.com',
        'password' => 'correctpassword1',
    ]);

    $response = $this->post(route('portal.login.post'), [
        'email'    => 'user@example.com',
        'password' => 'wrongpassword123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $this->assertGuest('portal');
});

// ── Signup ────────────────────────────────────────────────────────────────────

it('creates a portal account and links to a new contact on signup', function () {
    $response = $this->post(route('portal.signup.post'), [
        'first_name'            => 'Alice',
        'last_name'             => 'Smith',
        'email'                 => 'alice@example.com',
        'password'              => 'longsecurepass1',
        'password_confirmation' => 'longsecurepass1',
        '_form_start'           => time() - 10,
    ]);

    $response->assertRedirect(route('portal.verification.notice'));

    $account = PortalAccount::where('email', 'alice@example.com')->first();
    expect($account)->not->toBeNull();

    $contact = Contact::where('email', 'alice@example.com')->first();
    expect($contact)->not->toBeNull()
        ->and($account->contact_id)->toBe($contact->id)
        ->and($contact->first_name)->toBe('Alice')
        ->and($contact->last_name)->toBe('Smith');
});

it('links signup to existing contact with matching email', function () {
    $existing = Contact::factory()->create([
        'first_name' => 'Bob',
        'last_name'  => 'Jones',
        'email'      => 'bob@example.com',
    ]);

    $this->post(route('portal.signup.post'), [
        'first_name'            => 'Robert',
        'last_name'             => 'Jones',
        'email'                 => 'bob@example.com',
        'password'              => 'longsecurepass1',
        'password_confirmation' => 'longsecurepass1',
        '_form_start'           => time() - 10,
    ]);

    $account = PortalAccount::where('email', 'bob@example.com')->first();
    expect($account)->not->toBeNull()
        ->and($account->contact_id)->toBe($existing->id);

    $existing->refresh();
    expect($existing->first_name)->toBe('Robert');
});

// ── Password reset ────────────────────────────────────────────────────────────

it('sends a password reset link for an existing portal account', function () {
    $contact = Contact::factory()->create(['email' => 'reset@example.com']);
    PortalAccount::factory()->create([
        'contact_id' => $contact->id,
        'email'      => 'reset@example.com',
    ]);

    $response = $this->post(route('portal.password.email'), [
        'email' => 'reset@example.com',
    ]);

    $response->assertRedirect(route('portal.password.sent'));
});

it('consumes a password reset token and updates the password', function () {
    $contact = Contact::factory()->create(['email' => 'reset2@example.com']);
    $account = PortalAccount::factory()->create([
        'contact_id' => $contact->id,
        'email'      => 'reset2@example.com',
    ]);

    $token = Password::broker('portal_accounts')->createToken($account);

    $response = $this->post(route('portal.password.update'), [
        'token'                 => $token,
        'email'                 => 'reset2@example.com',
        'password'              => 'newlongsecurepass',
        'password_confirmation' => 'newlongsecurepass',
    ]);

    $response->assertRedirect(route('portal.login'));

    $account->refresh();
    expect(Hash::check('newlongsecurepass', $account->password))->toBeTrue();
});

// ── Route protection ──────────────────────────────────────────────────────────

it('redirects unauthenticated users away from portal account page', function () {
    $response = $this->get(route('portal.account'));

    $response->assertRedirect(route('portal.login'));
});

it('does not redirect authenticated portal users to login', function () {
    $contact = Contact::factory()->create(['email' => 'auth@example.com']);
    $account = PortalAccount::factory()->create([
        'contact_id' => $contact->id,
        'email'      => 'auth@example.com',
    ]);

    $response = $this->actingAs($account, 'portal')
        ->get(route('portal.account'));

    // The page may 404 in test (no Page record seeded), but it must NOT redirect to login
    if ($response->status() === 302) {
        expect($response->headers->get('Location'))->not->toContain('login');
    } else {
        expect($response->status())->not->toBe(302);
    }
});

// ── Contact scoping (no cross-contact data leakage) ──────────────────────────

it('scopes portal account update to the authenticated user contact only', function () {
    $contact1 = Contact::factory()->create([
        'email' => 'user1@example.com',
        'city'  => 'OldCity',
    ]);
    $account1 = PortalAccount::factory()->create([
        'contact_id' => $contact1->id,
        'email'      => 'user1@example.com',
    ]);

    $contact2 = Contact::factory()->create([
        'email' => 'user2@example.com',
        'city'  => 'OtherCity',
    ]);

    $this->actingAs($account1, 'portal')
        ->patch(route('portal.account.update-address'), [
            'city' => 'NewCity',
        ]);

    $contact1->refresh();
    $contact2->refresh();

    expect($contact1->city)->toBe('NewCity')
        ->and($contact2->city)->toBe('OtherCity');
});

it('scopes password change to the authenticated portal user only', function () {
    $contact = Contact::factory()->create(['email' => 'pwd@example.com']);
    $account = PortalAccount::factory()->create([
        'contact_id' => $contact->id,
        'email'      => 'pwd@example.com',
        'password'   => 'oldpassword12345',
    ]);

    $otherContact = Contact::factory()->create(['email' => 'other@example.com']);
    $otherAccount = PortalAccount::factory()->create([
        'contact_id' => $otherContact->id,
        'email'      => 'other@example.com',
        'password'   => 'otherpassword123',
    ]);

    $this->actingAs($account, 'portal')
        ->patch(route('portal.account.update-password'), [
            'current_password'      => 'oldpassword12345',
            'password'              => 'newpassword12345',
            'password_confirmation' => 'newpassword12345',
        ]);

    $account->refresh();
    $otherAccount->refresh();

    expect(Hash::check('newpassword12345', $account->password))->toBeTrue()
        ->and(Hash::check('otherpassword123', $otherAccount->password))->toBeTrue();
});
