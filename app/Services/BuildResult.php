<?php

namespace App\Services;

class BuildResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $cssFilename = null,
        public readonly ?string $jsFilename = null,
        public readonly int $cssSize = 0,
        public readonly int $jsSize = 0,
        public readonly int $buildTimeMs = 0,
    ) {}

    public static function success(
        string $cssFilename,
        string $jsFilename,
        int $cssSize,
        int $jsSize,
        int $buildTimeMs,
    ): self {
        return new self(
            success: true,
            message: 'Build complete.',
            cssFilename: $cssFilename,
            jsFilename: $jsFilename,
            cssSize: $cssSize,
            jsSize: $jsSize,
            buildTimeMs: $buildTimeMs,
        );
    }

    public static function fail(string $message): self
    {
        return new self(success: false, message: $message);
    }
}
