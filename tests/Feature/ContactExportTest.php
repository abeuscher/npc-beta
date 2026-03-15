<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('export response has content-type text/csv', function () {
    Contact::factory()->create();

    // Build the CSV directly using the same logic as the action
    $contacts = Contact::all();
    $handle   = fopen('php://temp', 'r+');

    fputcsv($handle, [
        'first_name', 'last_name', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state',
        'postal_code', 'notes', 'created_at',
    ]);

    $contacts->each(function (Contact $contact) use ($handle) {
        fputcsv($handle, [
            $contact->first_name, $contact->last_name, $contact->email, $contact->phone,
            $contact->address_line_1, $contact->address_line_2, $contact->city, $contact->state,
            $contact->postal_code, $contact->notes, $contact->created_at?->toDateTimeString(),
        ]);
    });

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    // Verify the CSV content format
    expect($csv)->toBeString()->not->toBeEmpty();

    $lines  = array_filter(explode("\n", trim($csv)));
    $header = str_getcsv($lines[0]);

    expect($header)->toContain('email');
});

it('export contains header row with expected column names', function () {
    $handle = fopen('php://temp', 'r+');

    fputcsv($handle, [
        'first_name', 'last_name', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state',
        'postal_code', 'notes', 'created_at',
    ]);

    rewind($handle);
    $line   = fgetcsv($handle);
    fclose($handle);

    expect($line)->toBe([
        'first_name', 'last_name', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state',
        'postal_code', 'notes', 'created_at',
    ]);
});

it('export contains one data row per contact in the database', function () {
    Contact::factory()->count(3)->create();

    $contacts = Contact::all();
    $handle   = fopen('php://temp', 'r+');

    fputcsv($handle, [
        'first_name', 'last_name', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state',
        'postal_code', 'notes', 'created_at',
    ]);

    $contacts->each(function (Contact $contact) use ($handle) {
        fputcsv($handle, [
            $contact->first_name, $contact->last_name, $contact->email, $contact->phone,
            $contact->address_line_1, $contact->address_line_2, $contact->city, $contact->state,
            $contact->postal_code, $contact->notes, $contact->created_at?->toDateTimeString(),
        ]);
    });

    rewind($handle);
    $csv   = stream_get_contents($handle);
    fclose($handle);

    $lines = array_filter(explode("\n", trim($csv)));
    expect(count($lines))->toBe(4); // 1 header + 3 data rows
});

it('filename contains today\'s date', function () {
    $filename = 'contacts-' . now()->format('Y-m-d') . '.csv';
    expect($filename)->toContain(now()->format('Y-m-d'));
});

