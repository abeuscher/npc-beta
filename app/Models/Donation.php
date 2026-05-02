<?php

namespace App\Models;

use App\WidgetPrimitive\EnforcesScrubInheritance;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Donation extends Model
{
    use EnforcesScrubInheritance, HasFactory, HasSourcePolicy, HasUuids;

    public const ACCEPTED_SOURCES = [
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
        'organization_id',
        'fund_id',
        'type',
        'amount',
        'currency',
        'frequency',
        'status',
        'source',
        'stripe_subscription_id',
        'stripe_customer_id',
        'started_at',
        'ended_at',
        'import_source_id',
        'import_session_id',
        'external_id',
        'custom_fields',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'started_at'    => 'datetime',
        'ended_at'      => 'datetime',
        'custom_fields' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'subject');
    }
}
