<?php

namespace App\Models;

use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Services\Media\ImageSizeProfile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy(EventObserver::class)]
class Event extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip',
        'map_url',
        'map_label',
        'meeting_url',
        'meeting_label',
        'meeting_details',
        'price',
        'capacity',
        'registration_mode',
        'external_registration_url',
        'auto_create_contacts',
        'mailing_list_opt_in_enabled',
        'author_id', // required; set to auth user on creation
        'landing_page_id', // system-managed: set by EventObserver when event is created
        'registrants_deleted_at',
        'custom_fields',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'custom_fields'               => 'array',
        'capacity'                    => 'integer',
        'price'                       => 'decimal:2',
        'auto_create_contacts'        => 'boolean',
        'mailing_list_opt_in_enabled' => 'boolean',
        'registrants_deleted_at'      => 'datetime',
        'starts_at'                   => 'datetime',
        'ends_at'                     => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────
    // Computed accessors (derived from field presence / price)
    // ──────────────────────────────────────────────────────────

    public function getIsInPersonAttribute(): bool
    {
        return ! empty($this->attributes['address_line_1']);
    }

    public function getIsVirtualAttribute(): bool
    {
        return ! empty($this->attributes['meeting_url']);
    }

    public function getIsFreeAttribute(): bool
    {
        return ($this->attributes['price'] ?? 0) == 0;
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now());
    }

    public function scopeOpenForRegistration(Builder $query): void
    {
        $query->published()->upcoming()->where('registration_mode', 'open');
    }

    // ──────────────────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('event_thumbnail')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && str_contains($media->mime_type, 'svg')) {
            return;
        }

        $profile = ImageSizeProfile::photo();

        $this->addMediaConversion('webp')
            ->width($profile->maxWidth)
            ->height($profile->maxHeight)
            ->format('webp');

        foreach ($profile->breakpoints as $width) {
            $this->addMediaConversion("responsive-{$width}")
                ->width($width)
                ->format('webp');
        }
    }

    public function isAtCapacity(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        $registered = $this->registrations()
            ->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])
            ->count();

        return $registered >= $this->capacity;
    }

}

