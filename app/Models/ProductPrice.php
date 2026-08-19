<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// The Stripe price observer is registered by the Payments plugin's provider
// (nonprofitcrm/payments) — absent plugin means no observer, by design.
class ProductPrice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'label',
        'amount',
        'stripe_price_id',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
