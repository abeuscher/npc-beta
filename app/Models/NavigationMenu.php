<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenu extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'label',
        'handle',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }
}
