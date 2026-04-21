<?php

namespace App\Services\Media;

use App\Models\SiteSetting;

class ImageSizeProfile
{
    public const DEFAULT_BREAKPOINTS = [576, 768, 1024, 1280, 1536];

    public function __construct(
        public readonly string $name,
        public readonly int $maxWidth,
        public readonly int $maxHeight,
        public readonly array $breakpoints = [],
    ) {}

    public static function configuredBreakpoints(): array
    {
        $stored = SiteSetting::get('image_breakpoints');

        if (is_array($stored) && count($stored) > 0) {
            return array_map('intval', $stored);
        }

        return self::DEFAULT_BREAKPOINTS;
    }

    public static function photo(): self
    {
        $bp = self::configuredBreakpoints();
        return new self('photo', max($bp), 1080, $bp);
    }

    public static function thumbnail(): self
    {
        $bp = self::configuredBreakpoints();
        $thumbBp = array_values(array_filter($bp, fn ($w) => $w <= 480));
        if (empty($thumbBp)) {
            $thumbBp = [min($bp)];
        }
        return new self('thumbnail', 480, 480, $thumbBp);
    }

    public static function icon(): self
    {
        return new self('icon', 128, 128);
    }

    public static function fromName(string $name): self
    {
        return match ($name) {
            'photo'     => self::photo(),
            'thumbnail' => self::thumbnail(),
            'icon'      => self::icon(),
            default     => throw new \InvalidArgumentException("Unknown image size profile: {$name}"),
        };
    }

    public static function all(): array
    {
        return [
            self::photo(),
            self::thumbnail(),
            self::icon(),
        ];
    }
}
