<?php

namespace App\Widgets\Contracts;

use Illuminate\Support\Str;
use RuntimeException;

abstract class WidgetDefinition
{
    abstract public function handle(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    abstract public function schema(): array;

    abstract public function defaults(): array;

    public function template(): string
    {
        $folder = Str::replaceLast('Definition', '', class_basename(static::class));

        return "@include('widgets::" . $folder . ".template')";
    }

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

    public function css(): ?string
    {
        return null;
    }

    public function js(): ?string
    {
        return null;
    }

    public function code(): ?string
    {
        return null;
    }

    public function variableName(): ?string
    {
        return null;
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function author(): string
    {
        return 'Nonprofit CRM';
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function screenshots(): array
    {
        return [];
    }

    public function keywords(): array
    {
        return [];
    }

    public function presets(): array
    {
        return [];
    }

    public function demoSeeder(): ?string
    {
        return null;
    }

    public function demoConfig(): array
    {
        return [];
    }

    public function demoAppearanceConfig(): array
    {
        return [];
    }

    /**
     * Declare sample-image-pool dependencies for demo mode. Each entry:
     *   - category: sample_images folder name (e.g. 'still-photos', 'portraits').
     *   - count:    max number of images requested. The pool returns
     *               min(count, available); an empty pool returns zero.
     *   - target:   where to inject the URL(s). One of:
     *                 'appearance.background_image' — writes a URL into
     *                   appearance_config.background.image_url (first image only).
     *                 'config.<key>' — writes a URL into config.<key>
     *                   (first image only if count === 1, otherwise an array).
     *
     * Returning an empty array means this widget does not request any pool
     * images. WidgetDemoController reads this at thumbnail-capture time.
     *
     * @return array<int, array{category: string, count: int, target: string}>
     */
    public function demoImages(): array
    {
        return [];
    }

    /**
     * Concrete default appearance_config shape. Every leaf is a concrete value
     * (no nulls, no empty strings, no missing keys). Overridden per widget to
     * ship with its own defaults; new instances are seeded from this shape.
     */
    public function defaultAppearanceConfig(): array
    {
        return [
            'background' => [
                'color'                    => '#ffffff',
                'use_current_page_header'  => false,
            ],
            'text'   => ['color' => '#000000'],
            'layout' => [
                'full_width' => false,
                'padding'    => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                'margin'     => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
            ],
        ];
    }

    public function manifest(): array
    {
        return [
            'handle'      => $this->handle(),
            'label'       => $this->label(),
            'description' => $this->description(),
            'category'    => $this->category(),
            'version'     => $this->version(),
            'author'      => $this->author(),
            'license'     => $this->license(),
            'screenshots' => $this->screenshots(),
            'keywords'    => $this->keywords(),
            'presets'     => $this->presets(),
        ];
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
            'css'                => $this->css(),
            'js'                 => $this->js(),
            'code'               => $this->code(),
            'variable_name'      => $this->variableName(),
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
