<?php

use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// These tests invoke the REAL `exportContacts` Filament action — the
// production path the UI triggers (ListContacts → ListExportService →
// ContactResource::exportColumnSpec) — not a re-implemented copy of the CSV.
// The previous versions rebuilt the CSV inline and never called the exporter
// (so a broken export still passed, and they even asserted a fictional
// `notes` column the real spec does not emit); the filename test
// interpolated the date and asserted the string contained it — a tautology
// that could not fail.

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('export action streams a CSV with the date-stamped filename and text/csv type', function () {
    Contact::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListContacts::class)
        ->callAction('exportContacts')
        ->assertFileDownloaded('contacts-' . now()->format('Y-m-d') . '.csv', null, 'text/csv');
});

it('export action emits the real exportColumnSpec header row', function () {
    Contact::factory()->create();

    $test = Livewire::actingAs($this->admin)
        ->test(ListContacts::class)
        ->callAction('exportContacts');

    $body = base64_decode(data_get($test->effects, 'download.content'));
    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($body)));

    // Leading standard columns from ContactResource::exportColumnSpec()
    // (any user CustomFieldDef columns append after these).
    expect(array_slice($rows[0], 0, 11))->toBe([
        'first_name', 'last_name', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state',
        'postal_code', 'date_of_birth', 'created_at',
    ]);
});

it('export action emits one data row per contact in the query', function () {
    Contact::factory()->count(3)->create();

    $test = Livewire::actingAs($this->admin)
        ->test(ListContacts::class)
        ->callAction('exportContacts');

    $body  = base64_decode(data_get($test->effects, 'download.content'));
    $lines = array_filter(preg_split("/\r\n|\n|\r/", trim($body)));

    expect(count($lines))->toBe(4); // 1 header + 3 data rows
});
