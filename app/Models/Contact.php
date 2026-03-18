<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'household_id',
        'prefix',
        'first_name',
        'last_name',
        'preferred_name',
        'email',
        'email_secondary',
        'phone',
        'phone_secondary',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'notes',
        'custom_data',
        'custom_fields',
        'is_deceased',
        'do_not_contact',
        'mailing_list_opt_in',
        'source',
    ];

    protected $casts = [
        'custom_data'    => SchemalessAttributes::class,
        'custom_fields'  => 'array',
        'is_deceased'          => 'boolean',
        'do_not_contact'       => 'boolean',
        'mailing_list_opt_in'  => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    // EventRegistration relationship — model and migration added in session 012
    // public function registrations(): HasMany
    // {
    //     return $this->hasMany(\App\Models\EventRegistration::class);
    // }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function activeMembership(): ?Membership
    {
        return $this->memberships()->where('status', 'active')->latest('starts_on')->first();
    }

    // -------------------------------------------------------------------------
    // Scopes — role-based filtering
    // -------------------------------------------------------------------------

    public function scopeIsMember(Builder $query): Builder
    {
        return $query->whereHas('memberships', fn ($q) => $q->where('status', 'active'));
    }

    public function scopeIsDonor(Builder $query): Builder
    {
        return $query->whereHas('donations');
    }

    public function scopeIsPublicDonor(Builder $query): Builder
    {
        return $query->whereHas('donations', fn ($q) => $q->where('is_anonymous', false));
    }

    // -------------------------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------------------------

    public function isMember(): bool
    {
        if ($this->relationLoaded('memberships')) {
            return $this->memberships->where('status', 'active')->isNotEmpty();
        }

        return $this->memberships()->where('status', 'active')->exists();
    }

    public function isDonor(): bool
    {
        if ($this->relationLoaded('donations')) {
            return $this->donations->isNotEmpty();
        }

        return $this->donations()->exists();
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getDisplayNameAttribute(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->last_name]));
    }
}
