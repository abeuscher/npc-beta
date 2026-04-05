<?php

namespace App\Models;

use App\Services\Media\ImageSizeProfile;
use App\Traits\Archivable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use Archivable, HasFactory, HasUuids, InteractsWithMedia;

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
