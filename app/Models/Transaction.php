<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'occurred_at'  => 'datetime',
        'qb_synced_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
