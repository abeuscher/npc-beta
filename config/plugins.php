<?php

/*
 * GENERATED FILE — do not edit by hand.
 *
 * Generated from distribution.json (the distribution manifest, the
 * plugin-set + ordering authority) by `php artisan plugins:manifest-sync`.
 * Edit the manifest and re-run the command; DistributionManifestGuardTest
 * fails the suite while this file is stale. Entry order here is the
 * manifest's entry order — the provider load order. Removing a manifest
 * entry and regenerating removes the provider line: the remove-the-line
 * guarantee, one level up. See docs/plugin-contract.md surface 1.
 */

return [
    Plugins\LogoGarden\LogoGardenServiceProvider::class,
    Plugins\Payments\PaymentsServiceProvider::class,
    Plugins\Events\EventsServiceProvider::class,
    Plugins\Donations\DonationsServiceProvider::class,
    Plugins\Memberships\MembershipsServiceProvider::class,
    Plugins\MemberPortal\MemberPortalServiceProvider::class,
];
