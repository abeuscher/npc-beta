<?php

namespace App\Models;

use App\WidgetPrimitive\EnforcesScrubInheritance;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use EnforcesScrubInheritance, HasFactory, HasSourcePolicy, HasUuids, SoftDeletes;

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
        'contact_id',
        'tier_id',
        'status',
        'source',
        'starts_on',
        'expires_on',
        'amount_paid',
        'stripe_session_id',
        'stripe_subscription_id',
        'notes',
        'import_source_id',
        'import_session_id',
        'external_id',
        'custom_fields',
    ];

    protected $casts = [
        'starts_on'     => 'date',
        'expires_on'    => 'date',
        'amount_paid'   => 'decimal:2',
        'custom_fields' => 'array',
    ];

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'tier_id');
    }
}
