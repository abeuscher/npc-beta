<?php

use App\Models\Form;
use App\Models\Fund;
use App\Models\MembershipTier;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Product archive ──────────────────────────────────────────────────────────

it('excludes archived products from withoutArchived scope', function () {
    $active = Product::factory()->create(['is_archived' => false]);
    $archived = Product::factory()->create(['is_archived' => true]);

    $results = Product::withoutArchived()->pluck('id');

    expect($results)->toContain($active->id)
        ->not->toContain($archived->id);
});

it('returns only archived products with onlyArchived scope', function () {
    $active = Product::factory()->create(['is_archived' => false]);
    $archived = Product::factory()->create(['is_archived' => true]);

    $results = Product::onlyArchived()->pluck('id');

    expect($results)->toContain($archived->id)
        ->not->toContain($active->id);
});

it('can toggle product archive state', function () {
    $product = Product::factory()->create(['is_archived' => false]);

    $product->update(['is_archived' => true]);
    expect($product->fresh()->is_archived)->toBeTrue();

    $product->update(['is_archived' => false]);
    expect($product->fresh()->is_archived)->toBeFalse();
});

// ── MembershipTier archive ───────────────────────────────────────────────────

it('excludes archived membership tiers from withoutArchived scope', function () {
    $active = MembershipTier::factory()->create(['is_archived' => false]);
    $archived = MembershipTier::factory()->create(['is_archived' => true]);

    $results = MembershipTier::withoutArchived()->pluck('id');

    expect($results)->toContain($active->id)
        ->not->toContain($archived->id);
});

it('can toggle membership tier archive state', function () {
    $tier = MembershipTier::factory()->create(['is_archived' => false]);

    $tier->update(['is_archived' => true]);
    expect($tier->fresh()->is_archived)->toBeTrue();

    $tier->update(['is_archived' => false]);
    expect($tier->fresh()->is_archived)->toBeFalse();
});

// ── Fund archive ─────────────────────────────────────────────────────────────

it('excludes archived funds from withoutArchived scope', function () {
    $active = Fund::factory()->create(['is_archived' => false]);
    $archived = Fund::factory()->create(['is_archived' => true]);

    $results = Fund::withoutArchived()->pluck('id');

    expect($results)->toContain($active->id)
        ->not->toContain($archived->id);
});

it('can toggle fund archive state', function () {
    $fund = Fund::factory()->create(['is_archived' => false]);

    $fund->update(['is_archived' => true]);
    expect($fund->fresh()->is_archived)->toBeTrue();

    $fund->update(['is_archived' => false]);
    expect($fund->fresh()->is_archived)->toBeFalse();
});

// ── Form archive ─────────────────────────────────────────────────────────────

it('excludes archived forms from withoutArchived scope', function () {
    $active = Form::factory()->create(['is_archived' => false]);
    $archived = Form::factory()->create(['is_archived' => true]);

    $results = Form::withoutArchived()->pluck('id');

    expect($results)->toContain($active->id)
        ->not->toContain($archived->id);
});

it('can toggle form archive state', function () {
    $form = Form::factory()->create(['is_archived' => false]);

    $form->update(['is_archived' => true]);
    expect($form->fresh()->is_archived)->toBeTrue();

    $form->update(['is_archived' => false]);
    expect($form->fresh()->is_archived)->toBeFalse();
});

it('returns 404 for archived form submissions', function () {
    $form = Form::factory()->create([
        'is_active'   => true,
        'is_archived' => true,
    ]);

    $response = $this->post("/forms/{$form->handle}", []);

    $response->assertStatus(404);
});

it('defaults is_archived to false for new records', function () {
    $product = Product::factory()->create();
    $tier = MembershipTier::factory()->create();
    $fund = Fund::factory()->create();
    $form = Form::factory()->create();

    expect($product->fresh()->is_archived)->toBeFalse();
    expect($tier->fresh()->is_archived)->toBeFalse();
    expect($fund->fresh()->is_archived)->toBeFalse();
    expect($form->fresh()->is_archived)->toBeFalse();
});
