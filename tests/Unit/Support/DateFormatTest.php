<?php

use App\Support\DateFormat;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->date = Carbon::parse('2026-04-25 17:00:00');
});

it('formats LONG_DATE', function () {
    expect(DateFormat::format($this->date, DateFormat::LONG_DATE))->toBe('April 25, 2026');
});

it('formats MEDIUM_DATE', function () {
    expect(DateFormat::format($this->date, DateFormat::MEDIUM_DATE))->toBe('Apr 25, 2026');
});

it('formats LONG_DATETIME', function () {
    expect(DateFormat::format($this->date, DateFormat::LONG_DATETIME))->toBe('April 25, 2026 at 5:00 pm');
});

it('formats MEDIUM_DATETIME', function () {
    expect(DateFormat::format($this->date, DateFormat::MEDIUM_DATETIME))->toBe('Apr 25, 2026 5:00 pm');
});

it('formats EVENT_FULL', function () {
    expect(DateFormat::format($this->date, DateFormat::EVENT_FULL))->toBe('Sat, April 25, 2026 at 5:00 pm');
});

it('formats EVENT_COMPACT', function () {
    expect(DateFormat::format($this->date, DateFormat::EVENT_COMPACT))->toBe('Sat Apr 25 · 5:00 pm');
});

it('formats TIME_OF_DAY', function () {
    expect(DateFormat::format($this->date, DateFormat::TIME_OF_DAY))->toBe('5:00 pm');
});

it('formats EVENT_LIST_DATE', function () {
    expect(DateFormat::format($this->date, DateFormat::EVENT_LIST_DATE))->toBe('April 25th');
});

it('formats EVENT_TILE_DATE', function () {
    expect(DateFormat::format($this->date, DateFormat::EVENT_TILE_DATE))->toBe('Sat, Apr 25');
});

it('formats TIME_SMART without minutes when minute is zero', function () {
    $date = Carbon::parse('2026-04-25 17:00:00');
    expect(DateFormat::format($date, DateFormat::TIME_SMART))->toBe('5pm');
});

it('formats TIME_SMART with minutes when minute is non-zero', function () {
    $date = Carbon::parse('2026-04-25 17:30:00');
    expect(DateFormat::format($date, DateFormat::TIME_SMART))->toBe('5:30pm');
});

it('returns empty string when date is null', function () {
    expect(DateFormat::format(null, DateFormat::LONG_DATE))->toBe('');
});

it('returns empty string when date is null for TIME_SMART', function () {
    expect(DateFormat::format(null, DateFormat::TIME_SMART))->toBe('');
});

it('exposes the curated event date option set', function () {
    expect(DateFormat::eventDateOptions())->toBe([
        DateFormat::EVENT_TILE_DATE => 'Compact (Sat, May 2)',
        DateFormat::EVENT_FULL      => 'Full (Sat, May 2, 2026 at 5:00 pm)',
        DateFormat::EVENT_LIST_DATE => 'Ordinal (May 2nd)',
        DateFormat::LONG_DATE       => 'Long (May 2, 2026)',
        DateFormat::MEDIUM_DATE     => 'Medium (May 2, 2026)',
    ]);
});

it('exposes the curated post date option set', function () {
    expect(DateFormat::postDateOptions())->toBe([
        DateFormat::LONG_DATE   => 'Long (May 2, 2026)',
        DateFormat::MEDIUM_DATE => 'Medium (May 2, 2026)',
    ]);
});
