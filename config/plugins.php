<?php

/*
 * Registered plugin service providers, in load order. Each entry is the
 * single wiring point for one in-repo plugin under plugins/ — remove the
 * line and the plugin is gone (no registration, no views, no synced
 * widget_types row on the next widgets:sync). This list is the in-repo
 * miniature of the future distribution-manifest / activation layer; see
 * docs/plugin-contract.md.
 */

return [
    Plugins\LogoGarden\LogoGardenServiceProvider::class,
    Plugins\Payments\PaymentsServiceProvider::class,
];
