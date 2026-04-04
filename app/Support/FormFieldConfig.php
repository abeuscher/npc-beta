<?php

namespace App\Support;

class FormFieldConfig
{
    protected static ?array $config = null;

    public static function load(): array
    {
        if (static::$config === null) {
            $path = config_path('form-fields.json');
            static::$config = file_exists($path)
                ? json_decode(file_get_contents($path), true) ?? []
                : [];
        }

        return static::$config;
    }

    /**
     * Resolve the column width for a field.
     *
     * Priority: explicit width > handle match > rule match > default.
     */
    public static function width(string $handle, ?string $label = null, ?int $explicit = null): int
    {
        if ($explicit !== null) {
            return $explicit;
        }

        $config = static::load();
        $default = $config['default_width'] ?? 12;

        // Handle-based lookup
        $widths = $config['widths'] ?? [];
        if (isset($widths[$handle])) {
            return $widths[$handle];
        }

        // Rule-based matching
        foreach ($config['rules'] ?? [] as $rule) {
            $match = $rule['match'] ?? [];
            $set = $rule['set'] ?? [];

            $matched = true;
            if (isset($match['handle']) && $match['handle'] !== $handle) {
                $matched = false;
            }
            if (isset($match['label']) && $label !== null && strcasecmp($match['label'], $label) !== 0) {
                $matched = false;
            }

            if ($matched && !empty($match) && isset($set['width'])) {
                return $set['width'];
            }
        }

        return $default;
    }

    /**
     * Clear cached config (useful for testing).
     */
    public static function flush(): void
    {
        static::$config = null;
    }
}
