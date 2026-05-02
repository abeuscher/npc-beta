<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

dataset('newOrgColumns', [
    ['source', 'character varying', 'NO'],
    ['custom_fields', 'jsonb', 'YES'],
    ['import_source_id', 'uuid', 'YES'],
    ['import_session_id', 'uuid', 'YES'],
    ['external_id', 'character varying', 'YES'],
]);

it('adds {0} to organizations as {1}', function (string $column, string $expectedType, string $expectedNullable) {
    expect(Schema::hasColumn('organizations', $column))->toBeTrue("organizations.{$column} missing");

    $row = DB::selectOne(
        "SELECT data_type, is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
        ['organizations', $column]
    );

    expect($row->data_type)->toBe($expectedType);
    expect($row->is_nullable)->toBe($expectedNullable);
})->with('newOrgColumns');

it('defaults organizations.source to human', function () {
    $row = DB::selectOne(
        "SELECT column_default FROM information_schema.columns WHERE table_name = 'organizations' AND column_name = 'source'"
    );

    expect($row->column_default)->toContain('human');
});

it('indexes organizations.source', function () {
    $rows = DB::select(
        "SELECT indexname FROM pg_indexes WHERE tablename = 'organizations' AND indexname = ?",
        ['organizations_source_index']
    );

    expect($rows)->not->toBeEmpty('organizations_source_index missing');
});

it('builds the composite organizations_import_external_idx', function () {
    $rows = DB::select(
        "SELECT indexname FROM pg_indexes WHERE tablename = 'organizations' AND indexname = ?",
        ['organizations_import_external_idx']
    );

    expect($rows)->not->toBeEmpty('organizations_import_external_idx missing');
});

it('declares ON DELETE SET NULL on organizations.import_source_id and organizations.import_session_id', function () {
    foreach (['import_source_id', 'import_session_id'] as $column) {
        $rows = DB::select(
            "SELECT confdeltype FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
             WHERE t.relname = 'organizations' AND a.attname = ? AND c.contype = 'f'",
            [$column]
        );

        expect($rows)->not->toBeEmpty("FK on organizations.{$column} missing");
        expect($rows[0]->confdeltype)->toBe('n', "organizations.{$column} FK should be SET NULL on delete");
    }
});

dataset('newImportSourceColumns', [
    ['organizations_field_map', 'jsonb', 'YES'],
    ['organizations_custom_field_map', 'jsonb', 'YES'],
    ['organizations_match_key', 'character varying', 'YES'],
]);

it('adds {0} to import_sources as {1}', function (string $column, string $expectedType, string $expectedNullable) {
    expect(Schema::hasColumn('import_sources', $column))->toBeTrue("import_sources.{$column} missing");

    $row = DB::selectOne(
        "SELECT data_type, is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
        ['import_sources', $column]
    );

    expect($row->data_type)->toBe($expectedType);
    expect($row->is_nullable)->toBe($expectedNullable);
})->with('newImportSourceColumns');
