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
}
