<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates the affiliations table with the expected columns', function () {
    expect(Schema::hasTable('affiliations'))->toBeTrue()
        ->and(Schema::hasColumns('affiliations', [
            'id', 'contact_id', 'organization_id', 'role',
            'is_primary', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('drops contacts.organization_id', function () {
    expect(Schema::hasColumn('contacts', 'organization_id'))->toBeFalse();
});

it('adds industry and ein columns to organizations', function () {
    expect(Schema::hasColumn('organizations', 'industry'))->toBeTrue()
        ->and(Schema::hasColumn('organizations', 'ein'))->toBeTrue();
});

it('declares affiliations FK with cascade-on-delete to contacts', function () {
    $row = DB::selectOne(
        "SELECT confdeltype FROM pg_constraint WHERE conname = ?",
        ['affiliations_contact_id_foreign']
    );

    // 'c' = ON DELETE CASCADE (per PostgreSQL pg_constraint.confdeltype)
    expect($row->confdeltype)->toBe('c');
});

it('declares affiliations FK with cascade-on-delete to organizations', function () {
    $row = DB::selectOne(
        "SELECT confdeltype FROM pg_constraint WHERE conname = ?",
        ['affiliations_organization_id_foreign']
    );

    expect($row->confdeltype)->toBe('c');
});

it('creates the partial unique index enforcing one primary per contact', function () {
    $row = DB::selectOne(
        "SELECT indexdef FROM pg_indexes WHERE indexname = ?",
        ['affiliations_one_primary_per_contact']
    );

    expect($row)->not->toBeNull()
        ->and($row->indexdef)->toContain('UNIQUE')
        ->and($row->indexdef)->toContain('contact_id')
        ->and($row->indexdef)->toContain('is_primary');
});

it('creates indexes on contact_id and organization_id', function () {
    $rows = DB::select(
        "SELECT indexname FROM pg_indexes WHERE tablename = 'affiliations'"
    );

    $names = array_column($rows, 'indexname');

    expect($names)->toContain('affiliations_contact_id_index')
        ->and($names)->toContain('affiliations_organization_id_index');
});
