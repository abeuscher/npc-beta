<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTier extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'price',
        'capacity',
        'sort_order',
        'is_complimentary',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'capacity'         => 'integer',
        'sort_order'       => 'integer',
        'is_complimentary' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function isAtCapacity(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        $registered = (int) $this->registrations()
            ->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])
            ->sum('quantity');

        return $registered >= $this->capacity;
    }
}
