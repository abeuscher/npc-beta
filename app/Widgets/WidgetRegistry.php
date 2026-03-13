<?php

namespace App\Widgets;

class WidgetRegistry
{
    /** @var array<string, class-string<Widget>> */
    protected static array $registry = [];

    /**
     * Register one or more widget type classes.
     *
     * @param array<class-string<Widget>> $classes
     */
    public static function register(array $classes): void
    {
        foreach ($classes as $class) {
            static::$registry[$class::handle()] = $class;
        }
    }

    /**
     * Return a widget class by handle, or null if not registered.
     *
     * @return class-string<Widget>|null
     */
    public static function getClass(string $handle): ?string
    {
        return static::$registry[$handle] ?? null;
    }

    /**
     * Return a new widget instance by handle, or null if not registered.
     */
    public static function get(string $handle): ?Widget
    {
        $class = static::getClass($handle);

        return $class ? new $class() : null;
    }

    /**
     * Return options array for Filament Select: ['handle' => 'Label', ...].
     */
    public static function options(): array
    {
        return collect(static::$registry)
            ->mapWithKeys(fn ($class, $handle) => [$handle => $class::label()])
            ->all();
    }

    /**
     * Return all registered handles.
     */
    public static function handles(): array
    {
        return array_keys(static::$registry);
    }
}
