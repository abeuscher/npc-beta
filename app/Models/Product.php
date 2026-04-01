<?php

namespace App\Models;

use App\Traits\Archivable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Archivable, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'capacity',
        'status',
        'sort_order',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderBy('sort_order');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function activePurchasesCount(): int
    {
        return $this->purchases()->where('status', 'active')->count();
    }

    public function isAtCapacity(): bool
    {
        return $this->activePurchasesCount() >= $this->capacity;
    }
}
