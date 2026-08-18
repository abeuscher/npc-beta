<?php

namespace App\Plugins;

/**
 * One plugin's declared contribution to the admin shell (session 379, arc P3
 * — docs/plugin-contract.md surfaces 3 and 4). A plugin provider constructs
 * one of these in register() and hands it to the PluginAdminRegistry; the
 * admin panel provider consumes it when Filament builds the panel.
 *
 * A null path/file means "this plugin contributes nothing on that axis" —
 * genuine absence, not a default. Nav grouping and ordering are NOT declared
 * here: plugin pages use Filament-native $navigationGroup / $navigationSort,
 * constrained to core's group list by the contract.
 */
class AdminContribution
{
    public function __construct(
        public readonly string $plugin,
        public readonly ?string $resourcesPath = null,
        public readonly ?string $resourcesNamespace = null,
        public readonly ?string $pagesPath = null,
        public readonly ?string $pagesNamespace = null,
        public readonly ?string $routesFile = null,
        public readonly array $permissions = [],
    ) {
    }
}
