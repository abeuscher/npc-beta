<?php

use App\Plugins\CapabilityRegistry;
use Tests\TestCase;

uses(TestCase::class);

// Session 380 (Plugin Architecture arc P4): capability-detection semantics
// (docs/plugin-contract.md surface 13). present() = a provider registered the
// capability; enabled() = present AND the provider's lazy resolver currently
// says usable. The Payments-specific consumers are covered by the
// PaymentsPluginSession380Test twin pair; this file pins the API itself.

it('reports an unknown capability as neither present nor enabled', function () {
    $registry = new CapabilityRegistry();

    expect($registry->present('teleportation'))->toBeFalse()
        ->and($registry->enabled('teleportation'))->toBeFalse();
});

it('reports a provided capability as present even when its resolver says disabled', function () {
    $registry = new CapabilityRegistry();
    $registry->provide('payments', fn (): bool => false);

    expect($registry->present('payments'))->toBeTrue()
        ->and($registry->enabled('payments'))->toBeFalse();
});

it('reports enabled only when present and the resolver returns true', function () {
    $registry = new CapabilityRegistry();
    $registry->provide('payments', fn (): bool => true);

    expect($registry->enabled('payments'))->toBeTrue();
});

it('evaluates the resolver lazily on every enabled() call', function () {
    $registry = new CapabilityRegistry();
    $configured = false;
    $registry->provide('payments', function () use (&$configured): bool {
        return $configured;
    });

    expect($registry->enabled('payments'))->toBeFalse();

    $configured = true;

    expect($registry->enabled('payments'))->toBeTrue()
        ->and($registry->present('payments'))->toBeTrue();
});

it('is bound as a core singleton', function () {
    expect(app(CapabilityRegistry::class))->toBe(app(CapabilityRegistry::class));
});
