<?php

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Contact observer fires on CRUD ───────────────────────────────────────────

it('writes a created event when a contact is created', function () {
    $contact = Contact::factory()->create();

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('created');
});

it('writes an updated event when a contact is updated', function () {
    $contact = Contact::factory()->create(['first_name' => 'OldName']);

    // Clear the created event log
    ActivityLog::where('subject_id', $contact->id)->delete();

    $contact->update(['first_name' => 'NewName']);

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('updated');
});

it('writes a deleted event when a contact is deleted', function () {
    $contact = Contact::factory()->create();
    $id = $contact->id;

    $contact->delete();

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $id)
        ->where('event', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('deleted');
});

// ── Actor types ──────────────────────────────────────────────────────────────

it('records admin actor type for admin-authenticated actions', function () {
    $admin = User::factory()->create();
    Auth::guard('web')->login($admin);

    $contact = Contact::factory()->create();

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('event', 'created')
        ->first();

    expect($log->actor_type)->toBe('admin')
        ->and($log->actor_id)->toBe($admin->id);

    Auth::guard('web')->logout();
});

it('records portal actor type for portal-authenticated actions', function () {
    $portalContact = Contact::factory()->create(['email' => 'portal@example.com']);
    $account = PortalAccount::factory()->create([
        'contact_id' => $portalContact->id,
        'email'      => 'portal@example.com',
    ]);

    Auth::guard('portal')->login($account);

    $subject = Contact::factory()->create();

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $subject->id)
        ->where('event', 'created')
        ->first();

    expect($log->actor_type)->toBe('portal')
        ->and($log->actor_id)->toBe($account->id);

    Auth::guard('portal')->logout();
});

it('records system actor type when no user is authenticated', function () {
    Auth::guard('web')->logout();
    Auth::guard('portal')->logout();

    $contact = Contact::factory()->create();

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('event', 'created')
        ->first();

    expect($log->actor_type)->toBe('system')
        ->and($log->actor_id)->toBeNull();
});

// ── ActivityLogger direct usage ──────────────────────────────────────────────

it('logs custom events with description and meta', function () {
    $contact = Contact::factory()->create();

    ActivityLogger::log($contact, 'receipt_sent', 'Tax receipt emailed', [
        'tax_year' => 2025,
        'total'    => 500.00,
    ]);

    $log = ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('event', 'receipt_sent')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Tax receipt emailed')
        ->and($log->meta['tax_year'])->toBe(2025)
        ->and((float) $log->meta['total'])->toBe(500.0);
});

it('silently fails without breaking the primary operation', function () {
    // ActivityLogger catches all exceptions silently
    // Simulate by passing an unsaved model (no key)
    $contact = new Contact();
    $contact->first_name = 'Test';

    // This should not throw even though the model has no key
    ActivityLogger::log($contact, 'created');

    // If we got here, the silent-fail worked
    expect(true)->toBeTrue();
});
