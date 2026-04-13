<?php

namespace App\Services;

use App\Models\WidgetType;
use App\Widgets\Contracts\WidgetDefinition;

class WidgetRegistry
{
    /** @var array<string, WidgetDefinition> */
    protected array $definitions = [];

    public function register(WidgetDefinition $def): void
    {
        $this->definitions[$def->handle()] = $def;
    }

    /** @return array<string, WidgetDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function find(string $handle): ?WidgetDefinition
    {
        return $this->definitions[$handle] ?? null;
    }

    /** @return array<string, array> handle → manifest */
    public function manifests(): array
    {
        return array_map(
            fn (WidgetDefinition $def) => $def->manifest(),
            $this->definitions
        );
    }

    public function sync(): void
    {
        foreach ($this->definitions as $def) {
            $def->validate();
            WidgetType::updateOrCreate(
                ['handle' => $def->handle()],
                $def->toRow()
            );
        }
    }
}
