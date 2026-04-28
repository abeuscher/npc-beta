<?php

use App\Models\Contact;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('flips completed donation statuses to active and leaves other statuses untouched', function () {
    $contact = Contact::factory()->create();

    $completed = Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'active',
    ]);
    $pending = Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'pending',
    ]);

    DB::table('donations')->where('id', $completed->id)->update(['status' => 'completed']);

    $migration = require database_path('migrations/2026_04_28_185924_backfill_completed_donation_statuses_to_active.php');
    $migration->up();

    expect(DB::table('donations')->where('id', $completed->id)->value('status'))->toBe('active')
        ->and(DB::table('donations')->where('id', $pending->id)->value('status'))->toBe('pending');
});

it('is idempotent on repeated runs', function () {
    $contact = Contact::factory()->create();

    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'active',
    ]);

    DB::table('donations')->where('id', $donation->id)->update(['status' => 'completed']);

    $migration = require database_path('migrations/2026_04_28_185924_backfill_completed_donation_statuses_to_active.php');
    $migration->up();
    $migration->up();

    expect(DB::table('donations')->where('id', $donation->id)->value('status'))->toBe('active');
});

it('has a no-op down method by design (cannot recover the original input vocabulary)', function () {
    $contact = Contact::factory()->create();

    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'active',
    ]);

    $migration = require database_path('migrations/2026_04_28_185924_backfill_completed_donation_statuses_to_active.php');
    $migration->down();

    expect(DB::table('donations')->where('id', $donation->id)->value('status'))->toBe('active');
});
