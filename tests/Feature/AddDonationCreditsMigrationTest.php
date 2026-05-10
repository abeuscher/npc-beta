<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates the donation_credits table with the expected columns', function () {
    expect(Schema::hasTable('donation_credits'))->toBeTrue();

    foreach (['id', 'donation_id', 'attributable_type', 'attributable_id', 'credit_pct', 'credit_role', 'created_at', 'updated_at'] as $col) {
        expect(Schema::hasColumn('donation_credits', $col))->toBeTrue("missing column: {$col}");
    }
});

it('enforces ON DELETE CASCADE on the donation_id FK', function () {
    $row = DB::selectOne(
        "SELECT confdeltype FROM pg_constraint
         WHERE conname = 'donation_credits_donation_id_foreign'"
    );

    expect($row?->confdeltype)->toBe('c');
});

it('has the donation_id index and the morph composite index', function () {
    $names = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'donation_credits'"))
        ->pluck('indexname')
        ->all();

    expect($names)->toContain('donation_credits_donation_id_index')
        ->and($names)->toContain('donation_credits_attributable_type_attributable_id_index');
});
