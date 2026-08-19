<?php

namespace App\Plugins;

use RuntimeException;

/**
 * The distribution manifest (session 386, arc P10 — docs/plugin-contract.md
 * surface 1). distribution.json at the repo root is the authority for which
 * plugins a build composes and in what order; this class is its single
 * reader. config/plugins.php is generated from it (`plugins:manifest-sync`)
 * so the runtime loader keeps reading a plain PHP config list — no
 * bootstrap-time JSON parsing — while the manifest owns the set.
 *
 * Source kinds mirror how composer actually resolves each plugin:
 *
 *   folder — a plain in-repo folder on the root "Plugins\" PSR-4 mapping,
 *            not a composer package (no package/constraint fields — the
 *            manifest models the real state, it does not pretend).
 *   path   — an in-repo composer package resolved through a root path
 *            repository (package + constraint + the folder path).
 *   vcs    — an external repository consumed through a root vcs repository
 *            entry and a tagged release (package + constraint + the URL).
 *
 * Malformed manifests throw with a message naming the problem — the same
 * fail-loud posture as the unknown-handle rule (surface 1).
 */
class DistributionManifest
{
    /** @param array<int, array<string, mixed>> $plugins */
    private function __construct(private readonly array $plugins)
    {
    }

    public static function path(): string
    {
        return base_path('distribution.json');
    }

    public static function load(): self
    {
        $path = self::path();

        if (! is_file($path)) {
            throw new RuntimeException("Distribution manifest missing at {$path}.");
        }

        return self::fromJson((string) file_get_contents($path));
    }

    public static function fromJson(string $raw): self
    {
        $json = json_decode($raw, true);

        if (! is_array($json)) {
            throw new RuntimeException('distribution.json is not valid JSON: '.json_last_error_msg());
        }

        $plugins = $json['plugins'] ?? null;

        if (! is_array($plugins) || $plugins === []) {
            throw new RuntimeException('distribution.json must declare a non-empty "plugins" array.');
        }

        foreach ($plugins as $i => $entry) {
            self::validateEntry($i, $entry);
        }

        self::rejectDuplicates($plugins, 'provider');
        self::rejectDuplicates($plugins, 'package');

        return new self(array_values($plugins));
    }

    private static function validateEntry(int $i, mixed $entry): void
    {
        if (! is_array($entry)) {
            throw new RuntimeException("distribution.json plugins[{$i}] is not an object.");
        }

        $provider = $entry['provider'] ?? null;
        if (! is_string($provider) || $provider === '') {
            throw new RuntimeException("distribution.json plugins[{$i}] must declare a provider FQCN.");
        }

        $type = $entry['source']['type'] ?? null;

        match ($type) {
            'folder' => self::requireFields($i, $provider, $entry, source: ['path'], forbidden: ['package', 'constraint']),
            'path'   => self::requireFields($i, $provider, $entry, source: ['path'], required: ['package', 'constraint']),
            'vcs'    => self::requireFields($i, $provider, $entry, source: ['url'], required: ['package', 'constraint']),
            default  => throw new RuntimeException(
                "distribution.json plugins[{$i}] ({$provider}) has source.type '".($type ?? 'missing')."'; valid kinds: folder, path, vcs."
            ),
        };
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<int, string>   $source    keys required under source
     * @param array<int, string>   $required  top-level keys required
     * @param array<int, string>   $forbidden top-level keys that must be absent
     */
    private static function requireFields(int $i, string $provider, array $entry, array $source = [], array $required = [], array $forbidden = []): void
    {
        foreach ($source as $key) {
            if (! is_string($entry['source'][$key] ?? null) || ($entry['source'][$key] ?? '') === '') {
                throw new RuntimeException("distribution.json plugins[{$i}] ({$provider}) requires source.{$key} for source.type '{$entry['source']['type']}'.");
            }
        }

        foreach ($required as $key) {
            if (! is_string($entry[$key] ?? null) || ($entry[$key] ?? '') === '') {
                throw new RuntimeException("distribution.json plugins[{$i}] ({$provider}) requires '{$key}' for source.type '{$entry['source']['type']}'.");
            }
        }

        foreach ($forbidden as $key) {
            if (array_key_exists($key, $entry)) {
                throw new RuntimeException("distribution.json plugins[{$i}] ({$provider}) must not declare '{$key}' for source.type '{$entry['source']['type']}' — a plain folder is not a composer package.");
            }
        }
    }

    /** @param array<int, array<string, mixed>> $plugins */
    private static function rejectDuplicates(array $plugins, string $key): void
    {
        $values = array_filter(array_column($plugins, $key));
        $dupes = array_diff_assoc($values, array_unique($values));

        if ($dupes !== []) {
            throw new RuntimeException("distribution.json declares duplicate {$key} entries: ".implode(', ', array_unique($dupes)).'.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function entries(): array
    {
        return $this->plugins;
    }

    /** @return array<int, string> ordered provider FQCNs — the load order */
    public function providers(): array
    {
        return array_column($this->plugins, 'provider');
    }

    /** @return array<int, array<string, mixed>> entries of one source kind */
    public function entriesOfKind(string $kind): array
    {
        return array_values(array_filter(
            $this->plugins,
            fn (array $entry) => $entry['source']['type'] === $kind,
        ));
    }

    /** @return array<int, string> composer package names of one source kind */
    public function packageNamesOfKind(string $kind): array
    {
        return array_column($this->entriesOfKind($kind), 'package');
    }

    /** @return array<int, string> every composer package name in the manifest */
    public function packageNames(): array
    {
        return array_filter(array_column($this->plugins, 'package'));
    }

    /**
     * The exact content of config/plugins.php as generated from this
     * manifest. Deterministic byte-for-byte: the guard asserts the committed
     * file equals this string, so a hand edit or a stale regeneration fails
     * the suite loudly.
     */
    public function generatedConfig(): string
    {
        $lines = array_map(
            fn (string $provider) => '    '.$provider.'::class,',
            $this->providers(),
        );

        return <<<'HEADER'
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

HEADER . implode("\n", $lines)."\n];\n";
    }
}
