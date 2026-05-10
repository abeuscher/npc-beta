<?php

use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Models\Affiliation;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);
});

it('persists two affiliations created via the repeater', function () {
    $contact = Contact::factory()->create();
    $org1    = Organization::factory()->create(['name' => 'Acme']);
    $org2    = Organization::factory()->create(['name' => 'Globex']);

    Livewire::test(EditContact::class, ['record' => $contact->id])
        ->fillForm([
            'affiliations' => [
                ['organization_id' => $org1->id, 'role' => 'Board member', 'is_primary' => true],
                ['organization_id' => $org2->id, 'role' => 'Donor liaison', 'is_primary' => false],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $rows = Affiliation::where('contact_id', $contact->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->where('organization_id', $org1->id)->first()->role)->toBe('Board member')
        ->and($rows->where('organization_id', $org1->id)->first()->is_primary)->toBeTrue()
        ->and($rows->where('organization_id', $org2->id)->first()->role)->toBe('Donor liaison')
        ->and($rows->where('organization_id', $org2->id)->first()->is_primary)->toBeFalse();
});

