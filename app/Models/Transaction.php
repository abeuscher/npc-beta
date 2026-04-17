<?php

namespace App\Models;

use App\Jobs\SyncTransactionToQuickBooks;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'contact_id',
        'type',
        'amount',
        'direction',
        'status',
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
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'occurred_at'  => 'datetime',
        'qb_synced_at' => 'datetime',
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

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }
}
