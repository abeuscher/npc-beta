<?php

namespace App\Models;

use App\Observers\EventRegistrationObserver;
use App\WidgetPrimitive\EnforcesScrubInheritance;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(EventRegistrationObserver::class)]
class EventRegistration extends Model
{
    use EnforcesScrubInheritance;
    use HasFactory;
    use HasSourcePolicy;
    use HasUuids;

    public const ACCEPTED_SOURCES = [
        Source::HUMAN,
        Source::IMPORT,
        Source::STRIPE_WEBHOOK,
        Source::SCRUB_DATA,
    ];

    public static function scrubInheritsFrom(): array
    {
        return ['contact_id' => Contact::class];
    }

    protected $fillable = [
        'event_id',
        'contact_id',
        'name',
        'email',
        'phone',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip',
        'status',
        'source',
        'registered_at',
        'stripe_payment_intent_id',
        'stripe_session_id',
        'notes',
        'mailing_list_opt_in',
        'ticket_type',
        'ticket_fee',
        'payment_state',
        'transaction_id',
        'import_session_id',
        'custom_fields',
    ];

    protected $casts = [
        'registered_at'       => 'datetime',
        'mailing_list_opt_in' => 'boolean',
        'ticket_fee'          => 'decimal:2',
        'custom_fields'       => 'array',
    ];

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }
}
