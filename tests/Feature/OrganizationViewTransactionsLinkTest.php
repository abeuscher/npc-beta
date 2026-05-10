<?php

use App\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Resources\TransactionResource;
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

it('renders a View transactions header action that deep-links to the org-filtered Transactions list', function () {
    $org = Organization::factory()->create();

    $component = Livewire::test(EditOrganization::class, ['record' => $org->id])
        ->assertActionExists('view_transactions')
        ->assertActionVisible('view_transactions');

    $expectedUrl = TransactionResource::getUrl('index')
        . '?tableFilters[organization_id][value]=' . $org->id;

    expect($component->instance()->getAction('view_transactions')->getUrl())->toBe($expectedUrl);
});
