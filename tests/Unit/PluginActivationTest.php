<?php

use App\Providers\PluginServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

// Session 384 (Plugin Architecture arc P8): unit coverage for the two pure
// pieces of the per-install activation layer — handle derivation from the
// provider FQCN, and the enabled = installed − disabled subtraction with its
// fail-loud unknown-handle posture. The filter's behavior in a booted app is
// covered by the flag fixtures' feature tests.

it('derives kebab-case handles from the provider namespace segment', function () {
    expect(PluginServiceProvider::handleFor(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBe('logo-garden')
        ->and(PluginServiceProvider::handleFor(\Plugins\Payments\PaymentsServiceProvider::class))->toBe('payments')
        ->and(PluginServiceProvider::handleFor(\Plugins\Events\EventsServiceProvider::class))->toBe('events');
});

it('returns the installed list unchanged when the disabled set is empty', function () {
    $installed = config('plugins');

    expect(PluginServiceProvider::enabledProviders($installed, []))->toBe($installed);
});

it('subtracts disabled handles without reordering the survivors', function () {
    $installed = [
        \Plugins\LogoGarden\LogoGardenServiceProvider::class,
        \Plugins\Payments\PaymentsServiceProvider::class,
        \Plugins\Events\EventsServiceProvider::class,
    ];

    expect(PluginServiceProvider::enabledProviders($installed, ['payments']))->toBe([
        \Plugins\LogoGarden\LogoGardenServiceProvider::class,
        \Plugins\Events\EventsServiceProvider::class,
    ]);

    expect(PluginServiceProvider::enabledProviders($installed, ['events', 'logo-garden']))->toBe([
        \Plugins\Payments\PaymentsServiceProvider::class,
    ]);
});

it('treats a duplicated disabled handle as a set — idempotent by construction', function () {
    $installed = config('plugins');

    expect(PluginServiceProvider::enabledProviders($installed, ['events', 'events']))
        ->toBe(PluginServiceProvider::enabledProviders($installed, ['events']));
});

it('can disable every installed plugin', function () {
    expect(PluginServiceProvider::enabledProviders(config('plugins'), ['logo-garden', 'payments', 'events', 'donations', 'memberships', 'member-portal', 'blog', 'forms']))
        ->toBe([]);
});

it('throws on an unknown handle, naming the bad handle and the valid set', function () {
    PluginServiceProvider::enabledProviders(config('plugins'), ['evnets']);
})->throws(RuntimeException::class, 'Unknown plugin handle(s) in PLUGINS_DISABLED: evnets. Installed plugin handles: logo-garden, payments, events, donations, memberships, member-portal, blog, forms.');

it('throws even when the unknown handle rides along with valid ones', function () {
    PluginServiceProvider::enabledProviders(config('plugins'), ['events', 'not-a-plugin']);
})->throws(RuntimeException::class, 'not-a-plugin');
