<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Donation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'contact_id',
        'type',
        'amount',
        'currency',
        'frequency',
        'status',
        'stripe_subscription_id',
        'stripe_customer_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'subject');
    }
}
