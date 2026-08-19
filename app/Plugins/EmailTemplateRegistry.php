<?php

namespace App\Plugins;

/**
 * Core-owned registry of plugin email-template defaults (session 389, arc D3
 * — docs/plugin-contract.md surface 7). A plugin contributes handle →
 * defaults (subject / body / footer_reason) from its provider's register(),
 * mirroring the ImporterRegistry pattern; EmailTemplate::defaults() falls
 * back to this registry for handles core's own array does not carry, and the
 * base seeder seeds one row per contribution. Bound as a singleton by
 * PluginServiceProvider before any plugin provider registers. Core-owned
 * handles stay in the model's array; a plugin vertical's handles move here
 * when it carves. Removing a plugin's manifest entry removes its handles
 * entirely (the surface-1 remove-the-line guarantee): the seeder then seeds
 * no row and forHandle() falls back to the empty-template default.
 */
class EmailTemplateRegistry
{
    /** @var array<string, array<string, string>> keyed by handle */
    protected array $contributions = [];

    /** @param array<string, string> $defaults subject / body / footer_reason */
    public function contribute(string $handle, array $defaults): void
    {
        $this->contributions[$handle] = $defaults;
    }

    /** @return array<string, string>|null */
    public function defaultsFor(string $handle): ?array
    {
        return $this->contributions[$handle] ?? null;
    }

    /** @return array<string, array<string, string>> keyed by handle */
    public function all(): array
    {
        return $this->contributions;
    }
}
