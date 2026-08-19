<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DonationCredit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'donation_id',
        'attributable_type',
        'attributable_id',
        'credit_pct',
        'credit_role',
    ];

    protected $casts = [
        'credit_pct' => 'decimal:2',
    ];

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }
}
