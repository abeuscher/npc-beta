<?php

namespace App\Models;

use App\Models\User;
use App\Observers\PageObserver;
use App\Services\Media\ImageSizeProfile;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy(PageObserver::class)]
class Page extends Model implements HasMedia
{
    use HasFactory, HasSlug, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'author_id', // required; set to auth user on creation
        'meta_title',
        'meta_description',
        'noindex',
        'head_snippet',
        'body_snippet',
        'custom_fields',
        'status',
        'published_at',
        'template_id',
    ];

    protected $attributes = [
        'type'   => 'default',
        'status' => 'draft',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'noindex'       => 'boolean',
        'published_at'  => 'datetime',
    ];

    public function scopePublished($query): void
    {
        $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function widgets(): MorphMany
    {
        return $this->morphMany(PageWidget::class, 'owner');
    }

    public function layouts(): MorphMany
    {
        return $this->morphMany(PageLayout::class, 'owner');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function event(): HasOne
    {
        return $this->hasOne(Event::class, 'landing_page_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function bareSlug(): string
    {
        $prefix = match ($this->type) {
            'system' => SiteSetting::get('system_prefix', 'system'),
            'member' => SiteSetting::get('portal_prefix', 'members'),
            default  => '',
        };

        if ($prefix !== '' && str_starts_with($this->slug, $prefix . '/')) {
            return substr($this->slug, strlen($prefix) + 1);
        }

        return $this->slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('post_thumbnail')->singleFile();
        $this->addMediaCollection('post_header')->singleFile();
        $this->addMediaCollection('og_image')->singleFile();
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
}
