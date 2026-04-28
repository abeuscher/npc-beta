<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('adds the source column to all four financial tables', function () {
    foreach (['donations', 'memberships', 'event_registrations', 'transactions'] as $table) {
        expect(Schema::hasColumn($table, 'source'))->toBeTrue("$table.source missing");
    }
});

it('declares source NOT NULL on all four financial tables', function () {
    foreach (['donations', 'memberships', 'event_registrations', 'transactions'] as $table) {
        $row = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = 'source'",
            [$table]
        );
        expect($row->is_nullable)->toBe('NO', "$table.source should be NOT NULL");
    }
});

it('indexes the source column on all four financial tables', function () {
    foreach (['donations', 'memberships', 'event_registrations', 'transactions'] as $table) {
        $rows = DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, "{$table}_source_index"]
        );
        expect($rows)->not->toBeEmpty("{$table}_source_index missing");
    }
});

it('defaults donations.source to stripe_webhook (Donation does not accept HUMAN)', function () {
    $row = DB::selectOne(
        "SELECT column_default FROM information_schema.columns WHERE table_name = 'donations' AND column_name = 'source'"
    );
    expect($row->column_default)->toContain('stripe_webhook');
});

it('defaults memberships/event_registrations/transactions source to human', function () {
    foreach (['memberships', 'event_registrations', 'transactions'] as $table) {
        $row = DB::selectOne(
            "SELECT column_default FROM information_schema.columns WHERE table_name = ? AND column_name = 'source'",
            [$table]
        );
        expect($row->column_default)->toContain('human');
    }
});
