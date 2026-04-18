<?php

use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use Database\Seeders\ImportSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('persists contacts_field_map, contacts_custom_field_map, contacts_match_key, and contacts_match_key_column on import sources', function () {
    $source = ImportSource::create([
        'name'                      => 'Salesforce Export',
        'contacts_field_map'        => ['first name' => 'first_name', 'email address' => 'email'],
        'contacts_custom_field_map' => ['member id' => ['handle' => 'member_id', 'label' => 'Member ID', 'field_type' => 'text']],
        'contacts_match_key'        => 'member_id',
        'contacts_match_key_column' => 'Member ID',
    ]);

    $source->refresh();

    expect($source->contacts_field_map)->toBe(['first name' => 'first_name', 'email address' => 'email'])
        ->and($source->contacts_custom_field_map)->toHaveKey('member id')
        ->and($source->contacts_custom_field_map['member id']['handle'])->toBe('member_id')
        ->and($source->contacts_match_key)->toBe('member_id')
        ->and($source->contacts_match_key_column)->toBe('Member ID');
});

it('casts contacts_field_map and contacts_custom_field_map to arrays and defaults to empty object in DB', function () {
    $source = ImportSource::create(['name' => 'Bare Source']);
    $source->refresh();

    expect($source->contacts_field_map)->toBe([])
        ->and($source->contacts_custom_field_map)->toBe([])
        ->and($source->contacts_match_key)->toBeNull()
        ->and($source->contacts_match_key_column)->toBeNull();
});

it('seeds three built-in sources with preset field maps and email match key', function () {
    (new ImportSourceSeeder())->run();

    foreach (['Generic CSV' => 'generic', 'Wild Apricot' => 'wild_apricot', 'Bloomerang' => 'bloomerang'] as $name => $preset) {
        $source   = ImportSource::where('name', $name)->first();
        $expected = FieldMapper::presetMap($preset);
        $actual   = $source->contacts_field_map;

        ksort($expected);
        ksort($actual);

        expect($source)->not->toBeNull()
            ->and($source->contacts_match_key)->toBe('email')
            ->and($source->contacts_match_key_column)->toBe('email')
            ->and($actual)->toBe($expected);
    }
});

it('seeder is idempotent — re-running does not duplicate built-in sources', function () {
    (new ImportSourceSeeder())->run();
    (new ImportSourceSeeder())->run();

    expect(ImportSource::where('name', 'Generic CSV')->count())->toBe(1)
        ->and(ImportSource::where('name', 'Wild Apricot')->count())->toBe(1)
        ->and(ImportSource::where('name', 'Bloomerang')->count())->toBe(1);
});
