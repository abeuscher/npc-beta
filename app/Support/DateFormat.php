<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class DateFormat
{
    public const LONG_DATE = 'F j, Y';

    public const MEDIUM_DATE = 'M j, Y';

    public const LONG_DATETIME = 'F j, Y \a\t g:i a';

    public const MEDIUM_DATETIME = 'M j, Y g:i a';

    public const EVENT_FULL = 'D, F j, Y \a\t g:i a';

    public const EVENT_COMPACT = 'D M j · g:i a';

    public const TIME_OF_DAY = 'g:i a';

    public const EVENT_LIST_DATE = 'F jS';

    public const EVENT_TILE_DATE = 'D, M j';

    public const TIME_SMART = '__time_smart__';

    public static function format(?Carbon $date, string $format): string
    {
        if ($date === null) {
            return '';
        }

        if ($format === self::TIME_SMART) {
            return $date->format($date->minute === 0 ? 'ga' : 'g:ia');
        }

        return $date->format($format);
    }

    /**
     * @return array<string, string>
     */
    public static function eventDateOptions(): array
    {
        return [
            self::EVENT_TILE_DATE => 'Compact (Sat, May 2)',
            self::EVENT_FULL      => 'Full (Sat, May 2, 2026 at 5:00 pm)',
            self::EVENT_LIST_DATE => 'Ordinal (May 2nd)',
            self::LONG_DATE       => 'Long (May 2, 2026)',
            self::MEDIUM_DATE     => 'Medium (May 2, 2026)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function postDateOptions(): array
    {
        return [
            self::LONG_DATE   => 'Long (May 2, 2026)',
            self::MEDIUM_DATE => 'Medium (May 2, 2026)',
        ];
    }
}
