<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDate extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'status',
        'location_override',
        'meeting_url_override',
        'notes',
    ];

    protected $casts = [
        'starts_at'         => 'datetime',
        'ends_at'           => 'datetime',
        'location_override' => 'array',
    ];

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now());
    }

    public function scopePublished(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('status', 'published')
                ->orWhere(function (Builder $q) {
                    $q->where('status', 'inherited')
                        ->whereHas('event', fn (Builder $q) => $q->where('status', 'published'));
                });
        });
    }

    // ──────────────────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────────────────

    public function effectiveStatus(): string
    {
        if ($this->status !== 'inherited') {
            return $this->status;
        }

        return $this->event?->status ?? 'draft';
    }

    /**
     * Merge event location fields with any per-occurrence overrides.
     * Override keys win. Only keys present in location_override are overridden.
     */
    public function effectiveLocation(): array
    {
        $base = [
            'is_in_person'  => $this->event?->is_in_person,
            'address_line_1' => $this->event?->address_line_1,
            'address_line_2' => $this->event?->address_line_2,
            'city'          => $this->event?->city,
            'state'         => $this->event?->state,
            'zip'           => $this->event?->zip,
            'map_url'       => $this->event?->map_url,
            'map_label'     => $this->event?->map_label,
            'is_virtual'    => $this->event?->is_virtual,
        ];

        return array_merge($base, array_filter($this->location_override ?? [], fn ($v) => $v !== null));
    }

    public function effectiveMeetingUrl(): ?string
    {
        return $this->meeting_url_override ?? $this->event?->meeting_url;
    }

    public function registrationCount(): int
    {
        return $this->registrations()->whereIn('status', ['registered', 'waitlisted', 'attended'])->count();
    }

    public function isAtCapacity(): bool
    {
        $capacity = $this->event?->capacity;

        if ($capacity === null) {
            return false;
        }

        return $this->registrationCount() >= $capacity;
    }
}
