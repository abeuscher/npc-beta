<?php

namespace App\Services;

use App\Models\SampleImage;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SampleImageLibrary
{
    public const PLACEHOLDER_URL = '/images/sample-placeholder.png';

    /**
     * @return Collection<int, Media>
     */
    public function random(string $category, int $count = 1): Collection
    {
        $host = SampleImage::where('category', $category)->first();
        if (! $host) {
            return collect();
        }

        return $host->getMedia($category)->shuffle()->take($count)->values();
    }

    public function randomUrl(string $category): ?string
    {
        $media = $this->random($category, 1)->first();

        return $media?->getUrl();
    }

    public function urlOrPlaceholder(string $category): string
    {
        return $this->randomUrl($category) ?? self::PLACEHOLDER_URL;
    }
}
