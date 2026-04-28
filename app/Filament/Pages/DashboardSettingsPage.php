<?php

namespace App\Filament\Pages;

use App\WidgetPrimitive\Views\DashboardView;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

class DashboardSettingsPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Dashboard View';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.dashboard-settings-page';

    protected static ?string $title = 'Dashboard View';

    protected static ?string $slug = 'dashboard-view';

    #[Url]
    public ?string $roleId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_dashboard_config') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            'Tools',
            'Dashboard View',
        ];
    }

    public function mount(): void
    {
        if ($this->roleId === null) {
            $userRole = auth()->user()?->roles()->orderBy('id')->first();
            $this->roleId = $userRole ? (string) $userRole->id : null;
        }
    }

    public function switchRole(string $roleId): void
    {
        $this->roleId = $roleId;
    }

    public function createConfigForSelectedRole(): void
    {
        abort_unless(auth()->user()?->can('manage_dashboard_config'), 403);

        if ($this->roleId === null) {
            return;
        }

        $role = Role::find($this->roleId);
        if (! $role) {
            return;
        }

        DashboardView::firstOrCreate(['role_id' => $role->id]);
    }

    public function getRoleOptions(): array
    {
        return Role::orderBy('name')->get()->mapWithKeys(fn ($r) => [
            (string) $r->id => $r->label ?? $r->name,
        ])->toArray();
    }

    public function getSelectedConfig(): ?DashboardView
    {
        if ($this->roleId === null) {
            return null;
        }

        return DashboardView::where('role_id', (int) $this->roleId)->first();
    }

    public function getSelectedRoleLabel(): ?string
    {
        if ($this->roleId === null) {
            return null;
        }
        $role = Role::find($this->roleId);
        return $role?->label ?? $role?->name;
    }
}
