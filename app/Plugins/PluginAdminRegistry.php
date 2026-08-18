<?php

namespace App\Plugins;

/**
 * Core-owned registry of plugin admin-shell contributions (session 379, arc
 * P3). Bound as a singleton by PluginServiceProvider before any plugin
 * provider registers, and read by AdminPanelProvider when the panel is built
 * — bootstrap/providers.php orders PluginServiceProvider ahead of the panel
 * provider, so every enabled plugin's contribution is present by then.
 * Removing a plugin's line from config/plugins.php removes its contribution
 * entirely (the surface-1 remove-the-line guarantee).
 */
class PluginAdminRegistry
{
    /** @var array<int, AdminContribution> */
    protected array $contributions = [];

    public function register(AdminContribution $contribution): void
    {
        $this->contributions[] = $contribution;
    }

    /** @return array<int, AdminContribution> */
    public function all(): array
    {
        return $this->contributions;
    }

    /** @return array<int, string> every declared permission name, deduplicated */
    public function permissions(): array
    {
        return array_values(array_unique(array_merge(
            [],
            ...array_map(fn (AdminContribution $c) => $c->permissions, $this->contributions),
        )));
    }
}
