<?php

namespace App\Jobs;

use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Per-media conversion regeneration after an ID-preserving seed
 * (media-portability draft decision #4 — the zip ships originals only;
 * conversions are regenerated on the queue, never shipped).
 */
class RegenerateMediaConversionsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = Media::find($this->mediaId);
        if (! $media) {
            return;
        }

        app(FileManipulator::class)->createDerivedFiles(
            $media,
            withResponsiveImages: ! empty($media->responsive_images),
        );
    }
}
