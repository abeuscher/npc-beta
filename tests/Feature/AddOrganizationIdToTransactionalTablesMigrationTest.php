<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

dataset('orgFkTables', [
    ['donations', 'organization_id'],
    ['memberships', 'organization_id'],
    ['event_registrations', 'organization_id'],
    ['events', 'sponsor_organization_id'],
    ['transactions', 'organization_id'],
]);

it('adds the organization FK column to {0}', function (string $table, string $column) {
    expect(Schema::hasColumn($table, $column))->toBeTrue("{$table}.{$column} missing");
})->with('orgFkTables');

it('declares the organization FK nullable on {0}', function (string $table, string $column) {
    $row = DB::selectOne(
        "SELECT is_nullable, data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
        [$table, $column]
    );
    expect($row->is_nullable)->toBe('YES', "{$table}.{$column} should be nullable");
    expect($row->data_type)->toBe('uuid', "{$table}.{$column} should be uuid");
})->with('orgFkTables');

it('indexes the organization FK on {0}', function (string $table, string $column) {
    $expected = "{$table}_{$column}_index";
    $rows = DB::select(
        "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
        [$table, $expected]
    );
    expect($rows)->not->toBeEmpty("{$expected} missing");
})->with('orgFkTables');

it('declares the organization FK with ON DELETE SET NULL on {0}', function (string $table, string $column) {
    $rows = DB::select(
        "SELECT confdeltype FROM pg_constraint c
         JOIN pg_class t ON t.oid = c.conrelid
         JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
         WHERE t.relname = ? AND a.attname = ? AND c.contype = 'f'",
        [$table, $column]
    );
    expect($rows)->not->toBeEmpty("FK on {$table}.{$column} missing");
    expect($rows[0]->confdeltype)->toBe('n', "{$table}.{$column} FK should be SET NULL on delete (got {$rows[0]->confdeltype})");
})->with('orgFkTables');
