<?php

namespace App\Models;

use App\Jobs\SyncTransactionToQuickBooks;
use App\WidgetPrimitive\EnforcesScrubInheritance;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use EnforcesScrubInheritance, HasFactory, HasSourcePolicy, HasUuids;

    public const ACCEPTED_SOURCES = [
        Source::HUMAN,
        Source::IMPORT,
        Source::STRIPE_WEBHOOK,
        Source::SCRUB_DATA,
    ];

    public static function scrubInheritsFrom(): array
    {
        return [
            ['type' => 'subject_type', 'id' => 'subject_id'],
            'contact_id' => Contact::class,
        ];
    }

    protected $fillable = [
        'subject_type',
        'subject_id',
        'contact_id',
        'organization_id',
        'type',
        'amount',
        'direction',
        'status',
        'source',
        'stripe_id',
        'quickbooks_id',
        'qb_sync_error',
        'qb_synced_at',
        'occurred_at',
        'import_source_id',
        'import_session_id',
        'external_id',
        'payment_method',
        'payment_channel',
        'invoice_number',
        'line_items',
        'custom_fields',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'occurred_at'   => 'datetime',
        'qb_synced_at'  => 'datetime',
        'line_items'    => 'array',
        'custom_fields' => 'array',
    ];

    /**
     * Create a transaction originating from Stripe and optionally sync to QuickBooks.
     *
     * Amount should already be converted to dollars (not cents).
     * Failed transactions are recorded but not dispatched to QuickBooks.
     */
    public static function recordStripe(array $attributes): self
    {
        $transaction = static::create(array_merge([
            'type'        => 'payment',
            'direction'   => 'in',
            'status'      => 'completed',
            'source'      => Source::STRIPE_WEBHOOK,
            'occurred_at' => now(),
        ], $attributes));

        if ($transaction->status !== 'failed') {
            SyncTransactionToQuickBooks::dispatch($transaction);
        }

        return $transaction;
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

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
}
