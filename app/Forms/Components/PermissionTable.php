<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class PermissionTable extends Field
{
    protected string $view = 'forms.components.permission-table';

    protected array $resources = [];

    // Maps the three logical columns to the underlying Spatie permission actions.
    public static array $groups = [
        'read'   => ['view_any', 'view'],
        'write'  => ['create', 'update'],
        'delete' => ['delete'],
    ];

    public function resources(array $resources): static
    {
        $this->resources = $resources;
        return $this;
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
    }
}
