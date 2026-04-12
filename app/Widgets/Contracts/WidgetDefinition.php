<?php

namespace App\Widgets\Contracts;

use RuntimeException;

abstract class WidgetDefinition
{
    abstract public function handle(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    abstract public function schema(): array;

    abstract public function defaults(): array;

    abstract public function template(): string;

    public function category(): array
    {
        return ['content'];
    }

    public function collections(): array
    {
        return [];
    }

    public function assets(): array
    {
        return [];
    }

    public function fullWidth(): bool
    {
        return false;
    }

    public function defaultOpen(): bool
    {
        return false;
    }

    public function allowedPageTypes(): ?array
    {
        return null;
    }

    public function renderMode(): string
    {
        return 'server';
    }

    public function requiredConfig(): ?array
    {
        return null;
    }

    public function toRow(): array
    {
        return [
            'label'              => $this->label(),
            'description'        => $this->description(),
            'category'           => $this->category(),
            'allowed_page_types' => $this->allowedPageTypes(),
            'render_mode'        => $this->renderMode(),
            'collections'        => $this->collections(),
            'assets'             => $this->assets(),
            'default_open'       => $this->defaultOpen(),
            'full_width'         => $this->fullWidth(),
            'config_schema'      => $this->schema(),
            'template'           => $this->template(),
            'required_config'    => $this->requiredConfig(),
        ];
    }

    public function validate(): void
    {
        $defaults = $this->defaults();
        $missing = [];

        foreach ($this->schema() as $field) {
            if (empty($field['key'])) {
                continue;
            }
            $key = $field['key'];
            if (! array_key_exists($key, $defaults)) {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            throw new RuntimeException(sprintf(
                'Widget [%s] defaults() is missing keys declared in schema(): %s',
                $this->handle(),
                implode(', ', $missing)
            ));
        }
    }
}
