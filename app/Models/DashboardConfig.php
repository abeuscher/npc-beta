<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Models\Role;

class DashboardConfig extends Model
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

    public function widgets(): MorphMany
    {
        return $this->morphMany(PageWidget::class, 'owner');
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
