<?php

namespace App\WidgetPrimitive\Views;

use App\Models\PageWidget;
use App\Models\User;
use App\WidgetPrimitive\IsView;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Models\Role;

class DashboardView extends Model implements IsView
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'role_id',
        'label',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function pageWidgets(): MorphMany
    {
        return $this->morphMany(PageWidget::class, 'owner');
    }

    public function handle(): string
    {
        return str($this->role?->name ?? 'unknown')->slug()->toString();
    }

    public function slotHandle(): string
    {
        return 'dashboard_grid';
    }

    /**
     * @return array<int, PageWidget>
     */
    public function widgets(): array
    {
        return $this->pageWidgets()
            ->where('is_active', true)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutConfig(): array
    {
        return [];
    }

    public static function forUser(?User $user): ?self
    {
        if (! $user) {
            return null;
        }

        $role = $user->roles()->orderBy('id')->first();
        if (! $role) {
            return null;
        }

        return static::where('role_id', $role->id)->first();
    }
}
