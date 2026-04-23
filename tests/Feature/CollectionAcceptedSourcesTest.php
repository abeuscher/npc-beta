<?php

use App\Filament\Resources\CollectionResource\Pages\CreateCollection;
use App\Filament\Resources\CollectionResource\Pages\EditCollection;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\User;
use App\WidgetPrimitive\DataSink;
use App\WidgetPrimitive\Exceptions\SourceRejectedException;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

it('defaults new Collection rows to ["human"] in accepted_sources', function () {
    $c = Collection::create([
        'name'        => 'Staff Photos',
        'handle'      => 'staff_photos',
        'source_type' => 'custom',
        'is_public'   => false,
        'is_active'   => true,
        'fields'      => [],
    ]);

    $c->refresh();

    expect($c->accepted_sources)->toBe(['human']);
});

it('backfills existing rows (pre-migration) with ["human"] via column default', function () {
    // Simulate an insert that does not specify the column — DB default fires.
    $id = \Illuminate\Support\Str::uuid()->toString();
    \DB::table('collections')->insert([
        'id'          => $id,
        'name'        => 'Legacy',
        'handle'      => 'legacy',
        'source_type' => 'custom',
        'is_public'   => false,
        'is_active'   => true,
        'fields'      => json_encode([]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $row = Collection::findOrFail($id);

    expect($row->accepted_sources)->toBe(['human']);
});

it('Collection::acceptsSource reads the per-row accepted_sources column', function () {
    $c = Collection::create([
        'name'             => 'Demo Collection',
        'handle'           => 'demo_collection',
        'source_type'      => 'custom',
        'is_public'        => true,
        'is_active'        => true,
        'fields'           => [],
        'accepted_sources' => [Source::HUMAN, Source::DEMO],
    ]);

    expect($c->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($c->acceptsSource(Source::DEMO))->toBeTrue()
        ->and($c->acceptsSource(Source::IMPORT))->toBeFalse()
        ->and($c->acceptsSource('nonsense'))->toBeFalse();
});

it('renders the accepted_sources CheckboxList without Source::HUMAN as an option', function () {
    Livewire::test(CreateCollection::class)
        ->assertFormFieldExists('accepted_sources')
        ->assertFormFieldIsVisible('accepted_sources')
        ->assertDontSee('"Human"');
});

it('creates a Collection with ["human"] when the admin checks no extra sources', function () {
    Livewire::test(CreateCollection::class)
        ->fillForm([
            'name'             => 'No-Extra',
            'handle'           => 'no_extra',
            'is_public'        => false,
            'is_active'        => true,
            'accepted_sources' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = Collection::where('handle', 'no_extra')->first();

    expect($row)->not->toBeNull()
        ->and($row->accepted_sources)->toBe(['human']);
});

it('creates a Collection with ["human","demo"] when the admin checks Demo', function () {
    Livewire::test(CreateCollection::class)
        ->fillForm([
            'name'             => 'Demo-Allowed',
            'handle'           => 'demo_allowed',
            'is_public'        => false,
            'is_active'        => true,
            'accepted_sources' => [Source::DEMO],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = Collection::where('handle', 'demo_allowed')->first();

    expect($row->accepted_sources)->toContain(Source::HUMAN)
        ->and($row->accepted_sources)->toContain(Source::DEMO);
});

it('rejects a CollectionItem write when the parent collection does not accept the source', function () {
    $collection = Collection::create([
        'name'             => 'Human-Only',
        'handle'           => 'human_only',
        'source_type'      => 'custom',
        'is_public'        => false,
        'is_active'        => true,
        'fields'           => [],
        'accepted_sources' => [Source::HUMAN],
    ]);

    app(DataSink::class)->write(CollectionItem::class, Source::DEMO, [
        'collection_id' => $collection->id,
        'data'          => ['name' => 'illegal'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);
})->throws(SourceRejectedException::class);

it('accepts a CollectionItem write when the parent collection accepts the source', function () {
    $collection = Collection::create([
        'name'             => 'Demo-Friendly',
        'handle'           => 'demo_friendly',
        'source_type'      => 'custom',
        'is_public'        => false,
        'is_active'        => true,
        'fields'           => [],
        'accepted_sources' => [Source::HUMAN, Source::DEMO],
    ]);

    $item = app(DataSink::class)->write(CollectionItem::class, Source::DEMO, [
        'collection_id' => $collection->id,
        'data'          => ['name' => 'ok'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    expect($item->exists)->toBeTrue()
        ->and($item->collection_id)->toBe($collection->id);
});

it('lets CollectionItem writes pass for Source::HUMAN universally', function () {
    $collection = Collection::create([
        'name'             => 'Whatever',
        'handle'           => 'whatever',
        'source_type'      => 'custom',
        'is_public'        => false,
        'is_active'        => true,
        'fields'           => [],
        'accepted_sources' => [Source::HUMAN],
    ]);

    $item = app(DataSink::class)->write(CollectionItem::class, Source::HUMAN, [
        'collection_id' => $collection->id,
        'data'          => ['name' => 'ok'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    expect($item->exists)->toBeTrue();
});

it('hydrates the accepted_sources CheckboxList on edit without showing Source::HUMAN', function () {
    $collection = Collection::create([
        'name'             => 'Editable',
        'handle'           => 'editable',
        'source_type'      => 'custom',
        'is_public'        => false,
        'is_active'        => true,
        'fields'           => [],
        'accepted_sources' => [Source::HUMAN, Source::DEMO],
    ]);

    Livewire::test(EditCollection::class, ['record' => $collection->getKey()])
        ->assertFormSet(['accepted_sources' => [Source::DEMO]]);
});
