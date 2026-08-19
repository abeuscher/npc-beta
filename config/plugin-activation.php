<?php

/*
 * Per-install plugin activation (session 384, Plugin Architecture arc P8 —
 * docs/plugin-contract.md surface 1, the two-layer model). config/plugins.php
 * stays the INSTALLED superset and sole ordering authority; this file carries
 * the per-install DISABLED set — kebab-case plugin handles derived from each
 * provider's namespace (LogoGarden → logo-garden). Enabled = installed minus
 * disabled, subtracted by core's PluginServiceProvider at registration — the
 * same seam the strip-the-line removal fixtures prove, so runtime-disabled
 * reproduces those semantics by construction.
 *
 * Env-backed by design: provider registration happens at bootstrap, before
 * the database is guaranteed to exist (fresh containers, migrate itself,
 * workers), and .env is exactly what Fleet Manager's config-push channel
 * delivers per install. Unset/empty = every installed plugin runs. An
 * unknown handle fails the bootstrap loudly (security-over-usability — a
 * silently ignored typo would leave a plugin running that the operator
 * meant to disable).
 */

return [
    'disabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLUGINS_DISABLED', ''))
    ))),
];
