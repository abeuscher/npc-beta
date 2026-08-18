<?php

namespace App\Plugins;

/**
 * Core-owned capability detection (session 380, arc P4 —
 * docs/plugin-contract.md surface 13). A foundation plugin declares a named
 * capability from its provider's register() with a lazy enabled-resolver;
 * consumers ask two questions:
 *
 *   present('payments') — is a plugin providing this capability registered?
 *   enabled('payments') — present AND the provider's resolver says it is
 *                         currently usable (for payments: a Stripe secret is
 *                         configured).
 *
 * Bound as a singleton by PluginServiceProvider before any plugin provider
 * registers. Resolvers are evaluated on every enabled() call, never cached —
 * they read runtime config, which tests and settings changes mutate freely.
 * Removing a plugin's config/plugins.php line removes its capability entirely
 * (the surface-1 remove-the-line guarantee): present() and enabled() are then
 * false, and every consumer degrades to its not-configured path.
 */
class CapabilityRegistry
{
    /** @var array<string, \Closure(): bool> */
    protected array $capabilities = [];

    public function provide(string $name, \Closure $enabled): void
    {
        $this->capabilities[$name] = $enabled;
    }

    public function present(string $name): bool
    {
        return array_key_exists($name, $this->capabilities);
    }

    public function enabled(string $name): bool
    {
        return $this->present($name) && (bool) ($this->capabilities[$name])();
    }
}
