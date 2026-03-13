<?php

namespace App\Widgets;

abstract class Widget
{
    /**
     * Short handle used to register and reference this widget type.
     */
    abstract public static function handle(): string;

    /**
     * Human-readable name shown in the admin type selector.
     */
    abstract public static function label(): string;

    /**
     * Filament form schema for the config fields specific to this widget type.
     * Fields should be keyed under the config array (e.g. TextInput::make('config.heading')).
     */
    abstract public static function configSchema(): array;

    /**
     * Resolves and returns the data array this widget will pass to its view.
     */
    abstract public function resolveData(array $config): array;

    /**
     * Blade view name for rendering on the public frontend.
     */
    abstract public function view(): string;
}
