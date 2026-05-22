<?php

namespace App\Models;

use App\Observers\ProductObserver;
use App\Services\Media\ImageSizeProfile;
use App\Traits\Archivable;
use App\WidgetPrimitive\EnforcesScrubInheritance;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy(ProductObserver::class)]
class Product extends Model implements HasMedia
{
    use Archivable, EnforcesScrubInheritance, HasFactory, HasSourcePolicy, HasUuids, InteractsWithMedia;

    public const ACCEPTED_SOURCES = [
        Source::HUMAN,
        Source::IMPORT,
        Source::SCRUB_DATA,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'capacity',
        'status',
        'source',
        'sort_order',
        'is_archived',
    ];

    protected $casts = [
        'is_archived'  => 'boolean',
        'published_at' => 'datetime',
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

    public function duplicate(): self
    {
        $copy = $this->replicate(['slug', 'status', 'published_at', 'source', 'is_archived']);

        $base = $this->slug . '-copy';
        $slug = $base;
        $i    = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $copy->name         = 'Copy of ' . $this->name;
        $copy->slug         = $slug;
        $copy->status       = 'draft';
        $copy->published_at = null;
        $copy->source       = Source::HUMAN;
        $copy->is_archived  = false;
        $copy->save();

        foreach ($this->prices as $price) {
            $copy->prices()->create([
                'label'      => $price->label,
                'amount'     => $price->amount,
                'sort_order' => $price->sort_order,
            ]);
        }

        foreach ($this->media as $media) {
            $media->copy($copy, $media->collection_name, $media->disk);
        }

        return $copy;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_image')->singleFile();
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
