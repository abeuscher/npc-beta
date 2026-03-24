<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (Contact $contact) {
            if (is_null($contact->household_id)) {
                Contact::where('id', $contact->id)->update(['household_id' => $contact->id]);
            }
        });

        static::addGlobalScope('hide_pending_imports', function (Builder $builder) {
            if (auth()->check() && auth()->user()->can('review_imports')) {
                return;
            }

            $builder->where(function (Builder $q) {
                $q->whereNull('contacts.import_session_id')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('import_sessions')
                            ->whereColumn('import_sessions.id', 'contacts.import_session_id')
                            ->where('import_sessions.status', 'approved');
                    });
            });
        });
    }

    protected $fillable = [
        'organization_id',
        'household_id',      // self-referential FK → contacts.id; equals id when solo/head
        'prefix',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'date_of_birth',
        'country',
        'custom_data',       // system-managed: SchemalessAttributes, written by importer
        'custom_fields',
        'do_not_contact',
        'mailing_list_opt_in',
        'source',            // system-managed: set at creation (manual/import/web_form/api)
        'import_session_id', // system-managed: set by importer
    ];

    protected $casts = [
        'date_of_birth'       => 'date',
        'custom_data'         => SchemalessAttributes::class,
        'custom_fields'       => 'array',
        'do_not_contact'      => 'boolean',
        'mailing_list_opt_in' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'household_id');
    }

    public function householdMembers(): HasMany
    {
        return $this->hasMany(Contact::class, 'household_id');
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

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function portalAccount(): HasOne
    {
        return $this->hasOne(PortalAccount::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function householdName(): string
    {
        if ($this->household_id === $this->id) {
            return "{$this->last_name} Household";
        }

        return "{$this->head->last_name} Household";
    }

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
