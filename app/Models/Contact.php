<?php

namespace App\Models;

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
        'type',
        'prefix',
        'first_name',
        'last_name',
        'organization_name',
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
        'is_deceased',
        'do_not_contact',
        'source',
    ];

    protected $casts = [
        'custom_data' => SchemalessAttributes::class,
        'is_deceased' => 'boolean',
        'do_not_contact' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership(): ?Membership
    {
        return $this->memberships()->where('status', 'active')->latest('starts_on')->first();
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

    public function getDisplayNameAttribute(): string
    {
        if ($this->type === 'organization') {
            return $this->organization_name ?? '';
        }

        $parts = array_filter([$this->first_name, $this->last_name]);

        return implode(' ', $parts);
    }
}
