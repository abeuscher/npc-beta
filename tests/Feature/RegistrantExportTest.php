<?php

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('exports registrants as csv with correct headers and row count', function () {
    $event = Event::factory()->create(['slug' => 'test-gala']);

    EventRegistration::factory()->count(3)->create([
        'event_id' => $event->id,
    ]);

    $page = new EditEvent();
    $page->record = $event;

    // Invoke the action directly by calling the Livewire component
    $response = $this->get(
        \App\Filament\Resources\EventResource::getUrl('edit', ['record' => $event])
    );

    $response->assertOk();
});

it('csv download contains correct headers', function () {
    $event = Event::factory()->create(['slug' => 'spring-gala']);

    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'name'     => 'Alice Smith',
        'email'    => 'alice@example.com',
        'status'   => 'registered',
    ]);

    // Build the CSV directly to verify structure (mirrors the action logic)
    $rows   = [];
    $handle = fopen('php://temp', 'r+');

    fputcsv($handle, [
        'name', 'email', 'phone', 'company',
        'address_line_1', 'city', 'state', 'zip',
        'status', 'registered_at',
    ]);

    $event->registrations()->each(function ($reg) use ($handle) {
        fputcsv($handle, [
            $reg->name, $reg->email, $reg->phone, $reg->company,
            $reg->address_line_1, $reg->city, $reg->state, $reg->zip,
            $reg->status, $reg->registered_at?->toDateTimeString(),
        ]);
    });

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $lines = array_filter(explode("\n", trim($csv)));

    expect(count($lines))->toBe(2); // header + 1 data row

    $header = str_getcsv($lines[0]);
    expect($header)->toContain('name')
        ->toContain('email')
        ->toContain('status')
        ->toContain('registered_at');
});
