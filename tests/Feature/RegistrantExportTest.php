<?php

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// These tests invoke the REAL `exportRegistrants` Filament action on
// EditEvent — the production path the UI triggers — not a re-implemented
// copy of the CSV. The previous versions asserted only that the edit page
// returned 200 and then rebuilt the CSV inline ("mirrors the action logic"),
// so a broken export still passed. Mirrors the ContactExportTest rewrite.

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('export action streams a date-stamped CSV with the real header row and one row per registrant', function () {
    $event = Event::factory()->create(['slug' => 'test-gala']);

    EventRegistration::factory()->create([
        'event_id'      => $event->id,
        'name'          => 'Alice First',
        'registered_at' => now()->subDays(3),
    ]);
    EventRegistration::factory()->create([
        'event_id'      => $event->id,
        'name'          => 'Bob Second',
        'registered_at' => now()->subDays(2),
    ]);
    EventRegistration::factory()->create([
        'event_id'      => $event->id,
        'name'          => 'Cara Third',
        'registered_at' => now()->subDay(),
    ]);

    $test = Livewire::actingAs($this->admin)
        ->test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->callAction('exportRegistrants')
        ->assertFileDownloaded('registrants-test-gala-' . now()->format('Y-m-d') . '.csv', null, 'text/csv');

    $body = base64_decode(data_get($test->effects, 'download.content'));
    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($body)));

    expect($rows[0])->toBe([
        'name', 'email', 'phone', 'company',
        'address_line_1', 'city', 'state', 'zip',
        'status', 'registered_at',
    ]);

    expect($rows)->toHaveCount(4); // header + 3 registrants

    // Rows stream in registered_at order.
    expect(array_column(array_slice($rows, 1), 0))
        ->toBe(['Alice First', 'Bob Second', 'Cara Third']);
});

it('export action is hidden when the event has no registrations', function () {
    $event = Event::factory()->create(['slug' => 'empty-gala']);

    Livewire::actingAs($this->admin)
        ->test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->assertActionHidden('exportRegistrants');
});
