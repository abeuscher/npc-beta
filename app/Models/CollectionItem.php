<?php

namespace App\Models;

use App\Services\Media\ImageSizeProfile;
use App\Support\HtmlSanitizer;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CollectionItem extends Model implements HasMedia
{
    use HasFactory, HasSourcePolicy, HasUuids, InteractsWithMedia, SoftDeletes;

    public function acceptsSource(string $source): bool
    {
        if ($source === Source::HUMAN) {
            return true;
        }

        if (! Source::isKnown($source)) {
            return false;
        }

        return $this->collection?->acceptsSource($source) === true;
    }

    protected $fillable = [
        'collection_id',
        'data',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'data'         => 'array',
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CollectionItem $item) {
            $collection = $item->collection;
            if (! $collection) {
                return;
            }

            $data = $item->data ?? [];
            if (! is_array($data) || $data === []) {
                return;
            }

            foreach ($collection->fields ?? [] as $field) {
                if (($field['type'] ?? '') !== 'rich_text') {
                    continue;
                }
                $key = $field['key'] ?? null;
                if ($key !== null && isset($data[$key]) && is_string($data[$key])) {
                    $data[$key] = HtmlSanitizer::sanitize($data[$key]);
                }
            }

            $item->data = $data;
        });
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && str_contains($media->mime_type, 'svg')) {
            return;
        }

        $profile = ImageSizeProfile::thumbnail();

        $this->addMediaConversion('webp')
            ->width($profile->maxWidth)
            ->height($profile->maxHeight)
            ->format('webp');

        foreach ($profile->breakpoints as $width) {
            $this->addMediaConversion("responsive-{$width}")
                ->width($width)
                ->format('webp');
        }

        // Logo-sized conversion for logo garden collections
        $this->addMediaConversion('logo')
            ->width(200)
            ->height(200)
            ->format('webp')
            ->performOnCollections('logo');
    }
}
