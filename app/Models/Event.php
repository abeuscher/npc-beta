<?php

namespace App\Models;

use App\Observers\EventObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip',
        'map_url',
        'map_label',
        'meeting_url',
        'meeting_label',
        'meeting_details',
        'price',
        'capacity',
        'registration_mode',
        'external_registration_url',
        'auto_create_contacts',
        'mailing_list_opt_in_enabled',
        'landing_page_id',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields'            => 'array',
        'capacity'                 => 'integer',
        'price'                    => 'decimal:2',
        'auto_create_contacts'     => 'boolean',
        'mailing_list_opt_in_enabled' => 'boolean',
    ];

    // ──────────────────────────────────────────────────────────
    // Computed accessors (derived from field presence / price)
    // ──────────────────────────────────────────────────────────

    public function getIsInPersonAttribute(): bool
    {
        return ! empty($this->attributes['address_line_1']);
    }

    public function getIsVirtualAttribute(): bool
    {
        return ! empty($this->attributes['meeting_url']);
    }

    public function getIsFreeAttribute(): bool
    {
        return ($this->attributes['price'] ?? 0) == 0;
    }

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

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
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
        $query->published()->upcoming()->where('registration_mode', 'open');
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
