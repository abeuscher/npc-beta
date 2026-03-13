<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'donation_id',
        'type',
        'amount',
        'direction',
        'status',
        'stripe_id',
        'quickbooks_id',
        'occurred_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
