<?php

namespace App\Providers;

use App\Plugins\CapabilityRegistry;
use App\Plugins\EmailTemplateRegistry;
use App\Plugins\ImporterRegistry;
use App\Plugins\PluginAdminRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound before the plugin loop so plugin providers can declare admin
        // contributions, capabilities, importers, and email-template handles
        // into them from their own register() methods.
        $this->app->singleton(PluginAdminRegistry::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(ImporterRegistry::class);
        $this->app->singleton(EmailTemplateRegistry::class);

        $providers = self::enabledProviders(
            config('plugins', []),
            config('plugin-activation.disabled', []),
        );

        foreach ($providers as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * The two-layer activation model (session 384, arc P8 — contract
     * surface 1): $installed is config/plugins.php, the superset + ordering
     * authority; $disabled is the per-install handle set from
     * config/plugin-activation.php. Enabled = installed minus disabled —
     * subtraction only, never reordering, the same strip-the-line seam the
     * RemovedTestCase fixtures prove. An unknown handle throws: a silently
     * ignored typo would leave a plugin running that the operator meant to
     * disable, so misconfiguration fails the bootstrap where the healthcheck
     * and deploy pipeline catch it.
     */
    public static function enabledProviders(array $installed, array $disabled): array
    {
        $byHandle = [];
        foreach ($installed as $provider) {
            $byHandle[self::handleFor($provider)] = $provider;
        }

        $unknown = array_diff(array_unique($disabled), array_keys($byHandle));
        if ($unknown !== []) {
            throw new RuntimeException(sprintf(
                'Unknown plugin handle(s) in PLUGINS_DISABLED: %s. Installed plugin handles: %s.',
                implode(', ', $unknown),
                implode(', ', array_keys($byHandle)),
            ));
        }

        return array_values(array_diff_key($byHandle, array_flip($disabled)));
    }

    /**
     * A plugin's handle is the kebab-case of its PascalName namespace segment:
     * Plugins\PascalName\PascalNameServiceProvider → pascal-name. Derived from
     * the FQCN's second-to-last segment — no registry, deterministic, and it
     * matches the composer package names.
     *
     * Stated with a placeholder rather than a real plugin on purpose: core
     * names no plugin, not even in prose, so the conformance kit's
     * zero-allowlist namespace scan stays honest.
     */
    public static function handleFor(string $provider): string
    {
        $segments = explode('\\', $provider);

        return Str::kebab($segments[count($segments) - 2]);
    }
}
