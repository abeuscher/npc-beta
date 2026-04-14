<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SampleImage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
    ];

    public const CATEGORY_PORTRAITS      = 'portraits';
    public const CATEGORY_STILL_PHOTOS   = 'still-photos';
    public const CATEGORY_LOGOS          = 'logos';
    public const CATEGORY_PRODUCT_PHOTOS = 'product-photos';

    public const CATEGORIES = [
        self::CATEGORY_PORTRAITS,
        self::CATEGORY_STILL_PHOTOS,
        self::CATEGORY_LOGOS,
        self::CATEGORY_PRODUCT_PHOTOS,
    ];

    protected $fillable = ['category'];

    public function registerMediaCollections(): void
    {
        foreach (self::CATEGORIES as $category) {
            $this->addMediaCollection($category);
        }
    }

    public static function forCategory(string $category): self
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException("Unknown sample image category: {$category}");
        }

        return static::firstOrCreate(['category' => $category]);
    }
}
