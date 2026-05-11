<?php

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Event;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('creates tiers on an event via the EditEvent form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $event = Event::factory()->create();

    Livewire::test(EditEvent::class, ['record' => $event->id])
        ->fillForm([
            'ticketTiers' => [
                ['name' => 'General', 'price' => 25.00, 'capacity' => 100],
                ['name' => 'VIP',     'price' => 100.00, 'capacity' => null],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->ticketTiers)->toHaveCount(2);

    $tiers = $event->fresh()->ticketTiers;
    expect($tiers->pluck('name')->all())->toEqual(['General', 'VIP'])
        ->and((float) $tiers[0]->price)->toBe(25.00)
        ->and($tiers[0]->capacity)->toBe(100)
        ->and((float) $tiers[1]->price)->toBe(100.00)
        ->and($tiers[1]->capacity)->toBeNull();
});

it('requires a tier name when adding a tier', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $event = Event::factory()->create();

    Livewire::test(EditEvent::class, ['record' => $event->id])
        ->fillForm([
            'ticketTiers' => [
                ['name' => '', 'price' => 10, 'capacity' => null],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($event->fresh()->ticketTiers)->toHaveCount(0);
});

it('rejects negative tier prices', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $event = Event::factory()->create();

    Livewire::test(EditEvent::class, ['record' => $event->id])
        ->fillForm([
            'ticketTiers' => [
                ['name' => 'General', 'price' => -1, 'capacity' => null],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($event->fresh()->ticketTiers)->toHaveCount(0);
});

it('rejects duplicate tier names within an event', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $event = Event::factory()->create();

    Livewire::test(EditEvent::class, ['record' => $event->id])
        ->fillForm([
            'ticketTiers' => [
                ['name' => 'General', 'price' => 10, 'capacity' => null],
                ['name' => 'general', 'price' => 20, 'capacity' => null],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($event->fresh()->ticketTiers)->toHaveCount(0);
});
