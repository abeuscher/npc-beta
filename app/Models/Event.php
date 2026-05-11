<?php

namespace App\Models;

use App\Models\Concerns\SanitisesRichTextCustomFields;
use App\Observers\EventObserver;
use App\Support\HtmlSanitizer;
use App\WidgetPrimitive\EnforcesScrubInheritance;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
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
    use EnforcesScrubInheritance;
    use HasFactory;
    use HasSourcePolicy;
    use HasUuids;
    use InteractsWithMedia;
    use SanitisesRichTextCustomFields;

    public const ACCEPTED_SOURCES = [
        Source::HUMAN,
        Source::IMPORT,
        Source::SCRUB_DATA,
    ];

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
        'registration_mode',
        'external_registration_url',
        'auto_create_contacts',
        'mailing_list_opt_in_enabled',
        'author_id', // required; set to auth user on creation
        'sponsor_organization_id',
        'landing_page_id', // system-managed: set by EventObserver when event is created
        'registrants_deleted_at',
        'custom_fields',
        'starts_at',
        'ends_at',
        'source',
        'import_session_id', // system-managed: set by events importer
    ];

    protected $casts = [
        'custom_fields'               => 'array',
        'auto_create_contacts'        => 'boolean',
        'mailing_list_opt_in_enabled' => 'boolean',
        'registrants_deleted_at'      => 'datetime',
        'starts_at'                   => 'datetime',
        'ends_at'                     => 'datetime',
        'published_at'                => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────
    // Computed accessors (derived from field presence / price)
    // ──────────────────────────────────────────────────────────

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = $value === null ? null : HtmlSanitizer::sanitize($value);
    }

    public function setMeetingDetailsAttribute(?string $value): void
    {
        $this->attributes['meeting_details'] = $value === null ? null : HtmlSanitizer::sanitize($value);
    }

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
        if ($this->relationLoaded('ticketTiers')) {
            return $this->ticketTiers->every(fn ($tier) => (float) $tier->price <= 0.0);
        }

        return $this->ticketTiers()->where('price', '>', 0)->doesntExist();
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function sponsorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'sponsor_organization_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function ticketTiers(): HasMany
    {
        return $this->hasMany(TicketTier::class)->orderBy('sort_order');
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
        $this->addMediaCollection('event_header')->singleFile();
        $this->addMediaCollection('event_og_image')->singleFile();
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
        $tiers = $this->ticketTiers;

        if ($tiers->isEmpty()) {
            return false;
        }

        foreach ($tiers as $tier) {
            if (! $tier->isAtCapacity()) {
                return false;
            }
        }

        return true;
    }

}

