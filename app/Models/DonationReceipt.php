<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationReceipt extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'contact_id',
        'tax_year',
        'sent_at',
        'total_amount',
        'breakdown',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'breakdown'    => 'array',
        'sent_at'      => 'datetime',
        'created_at'   => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
