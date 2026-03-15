<?php

namespace App\Models;

use App\Observers\EventRegistrationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(EventRegistrationObserver::class)]
class EventRegistration extends Model
{
    use HasFactory;
    use HasUuids;

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
        'registered_at',
        'stripe_payment_intent_id',
        'notes',
        'mailing_list_opt_in',
    ];

    protected $casts = [
        'registered_at'      => 'datetime',
        'mailing_list_opt_in' => 'boolean',
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
}
