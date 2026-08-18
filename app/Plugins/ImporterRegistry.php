<?php

namespace App\Plugins;

/**
 * Core-owned registry of plugin importer contributions (session 381, arc P5
 * — docs/plugin-contract.md surface 8). Bound as a singleton by
 * PluginServiceProvider before any plugin provider registers. Core-owned
 * importers (contacts, donations, memberships, invoices, notes,
 * organizations) stay hard-wired in their consumers; they can join this
 * registry when their domains extract. Removing a plugin's line from
 * config/plugins.php removes its importer entirely (the surface-1
 * remove-the-line guarantee).
 */
class ImporterRegistry
{
    /** @var array<string, ImporterContribution> keyed by slug */
    protected array $contributions = [];

    public function register(ImporterContribution $contribution): void
    {
        $this->contributions[$contribution->slug] = $contribution;
    }

    /** @return array<string, ImporterContribution> keyed by slug */
    public function all(): array
    {
        return $this->contributions;
    }

    public function find(string $slug): ?ImporterContribution
    {
        return $this->contributions[$slug] ?? null;
    }
}
