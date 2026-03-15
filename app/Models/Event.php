<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Event extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'is_in_person',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip',
        'map_url',
        'map_label',
        'is_virtual',
        'meeting_url',
        'is_free',
        'capacity',
        'registration_open',
        'is_recurring',
        'recurrence_type',
        'recurrence_rule',
        'landing_page_id',
    ];

    protected $casts = [
        'is_in_person'     => 'boolean',
        'is_virtual'       => 'boolean',
        'is_free'          => 'boolean',
        'is_recurring'     => 'boolean',
        'registration_open' => 'boolean',
        'capacity'         => 'integer',
        'recurrence_rule'  => 'array',
    ];

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function eventDates(): HasMany
    {
        return $this->hasMany(EventDate::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->whereHas('eventDates', fn (Builder $q) => $q->where('starts_at', '>=', now()));
    }

    public function scopeOpenForRegistration(Builder $query): void
    {
        $query->published()->upcoming()->where('registration_open', true);
    }

    // ──────────────────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────────────────

    public function nextDate(): ?EventDate
    {
        return $this->eventDates()
            ->where('starts_at', '>=', now())
            ->where(fn (Builder $q) => $q
                ->where('status', 'published')
                ->orWhere('status', 'inherited'))
            ->orderBy('starts_at')
            ->first();
    }

    public function isAtCapacity(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        $registered = $this->registrations()
            ->whereIn('status', ['registered', 'waitlisted', 'attended'])
            ->count();

        return $registered >= $this->capacity;
    }

    /**
     * Generate date pairs from the recurrence_rule array.
     * Returns a Collection of ['starts_at' => Carbon, 'ends_at' => Carbon|null] maps.
     * Does NOT persist anything.
     */
    public function generateDatesFromRule(array $rule, int $maxOccurrences = 52): Collection
    {
        $freq      = $rule['freq'] ?? 'daily';
        $interval  = max(1, (int) ($rule['interval'] ?? 1));
        $startTime = $rule['start_time'] ?? '09:00';
        $endTime   = $rule['end_time'] ?? null;
        $until     = isset($rule['until']) ? Carbon::parse($rule['until'])->endOfDay() : null;
        $count     = isset($rule['count']) ? (int) $rule['count'] : $maxOccurrences;
        $limit     = $until ? $maxOccurrences : $count;

        $dates    = collect();
        $cursor   = Carbon::today();

        while ($dates->count() < $limit) {
            if ($until && $cursor->greaterThan($until)) {
                break;
            }

            $candidate = null;

            switch ($freq) {
                case 'daily':
                    $candidate = $cursor->copy()->setTimeFromTimeString($startTime);
                    $cursor->addDays($interval);
                    break;

                case 'business_days':
                    // Advance to the next business day, then step by interval
                    while ($cursor->isWeekend()) {
                        $cursor->addDay();
                    }
                    $candidate = $cursor->copy()->setTimeFromTimeString($startTime);
                    $steps = 0;
                    while ($steps < $interval) {
                        $cursor->addDay();
                        if (! $cursor->isWeekend()) {
                            $steps++;
                        }
                    }
                    break;

                case 'weekly':
                    $daysOfWeek = $rule['days_of_week'] ?? ['monday'];
                    foreach ($daysOfWeek as $dayName) {
                        $day = $cursor->copy()->next($dayName)->setTimeFromTimeString($startTime);
                        if (! $until || $day->lessThanOrEqualTo($until)) {
                            $dates->push([
                                'starts_at' => $day->copy(),
                                'ends_at'   => $endTime ? $day->copy()->setTimeFromTimeString($endTime) : null,
                            ]);
                        }
                        if ($dates->count() >= $limit) {
                            break;
                        }
                    }
                    $cursor->addWeeks($interval);
                    continue 2;

                case 'monthly_day':
                    // e.g. first Monday of the month
                    $nth     = (int) ($rule['nth'] ?? 1);
                    $weekday = $rule['weekday'] ?? 'monday';
                    $day     = $cursor->copy()->firstOfMonth()->next($weekday);
                    for ($i = 1; $i < $nth; $i++) {
                        $day->next($weekday);
                    }
                    $day->setTimeFromTimeString($startTime);
                    $candidate = $day;
                    $cursor->addMonthsNoOverflow($interval);
                    break;

                case 'monthly_date':
                    $dayOfMonth = (int) ($rule['day_of_month'] ?? 1);
                    $day        = $cursor->copy()->setDay(min($dayOfMonth, $cursor->daysInMonth))
                        ->setTimeFromTimeString($startTime);
                    $candidate  = $day;
                    $cursor->addMonthsNoOverflow($interval);
                    break;

                default:
                    // Unknown freq — stop
                    break 2;
            }

            if ($candidate) {
                if ($until && $candidate->greaterThan($until)) {
                    break;
                }
                $dates->push([
                    'starts_at' => $candidate->copy(),
                    'ends_at'   => $endTime ? $candidate->copy()->setTimeFromTimeString($endTime) : null,
                ]);
            }
        }

        return $dates;
    }
}
