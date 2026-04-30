<?php

namespace App\WidgetPrimitive;

final class Source
{
    public const HUMAN = 'human';

    public const DEMO = 'demo';

    public const IMPORT = 'import';

    public const GOOGLE_DOCS = 'google_docs';

    public const LLM_SYNTHESIS = 'llm_synthesis';

    public const STRIPE_WEBHOOK = 'stripe_webhook';

    public const SCRUB_DATA = 'scrub_data';

    public const KNOWN = [
        self::HUMAN,
        self::DEMO,
        self::IMPORT,
        self::GOOGLE_DOCS,
        self::LLM_SYNTHESIS,
        self::STRIPE_WEBHOOK,
        self::SCRUB_DATA,
    ];

    private function __construct() {}

    public static function isKnown(string $source): bool
    {
        return in_array($source, self::KNOWN, true);
    }
}
