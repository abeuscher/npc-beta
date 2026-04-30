<?php

use App\Filament\Resources\CustomFieldDefResource;
use App\Models\CustomFieldDef;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

it('can list custom field defs', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'legacy_id',
        'label'      => 'Legacy ID',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CustomFieldDefResource\Pages\ListCustomFieldDefs::class)
        ->assertCanSeeTableRecords(CustomFieldDef::all());
});

it('can create a custom field def', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CustomFieldDefResource\Pages\CreateCustomFieldDef::class)
        ->fillForm([
            'model_type' => 'contact',
            'label'      => 'External ID',
            'handle'     => 'external_id',
            'field_type' => 'text',
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CustomFieldDef::where('handle', 'external_id')->exists())->toBeTrue();
});

it('can create a rich_text custom field def', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CustomFieldDefResource\Pages\CreateCustomFieldDef::class)
        ->fillForm([
            'model_type' => 'contact',
            'label'      => 'Bio',
            'handle'     => 'bio',
            'field_type' => 'rich_text',
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CustomFieldDef::where('handle', 'bio')->where('field_type', 'rich_text')->exists())->toBeTrue();
});

it('lists rich_text in the field-type table column', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'bio',
        'label'      => 'Bio',
        'field_type' => 'rich_text',
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CustomFieldDefResource\Pages\ListCustomFieldDefs::class)
        ->assertCanSeeTableRecords(CustomFieldDef::all())
        ->assertSee('Rich Text');
});

it('can delete a custom field def', function () {
    $def = CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'to_delete',
        'label'      => 'To Delete',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CustomFieldDefResource\Pages\ListCustomFieldDefs::class)
        ->callTableAction('delete', $def);

    expect(CustomFieldDef::find($def->id))->toBeNull();
});
