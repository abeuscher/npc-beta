<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// The PurchaseObserver is registered by the Products plugin's provider —
// an #[ObservedBy] attribute here would be a core→plugin reach (the
// observer inversion, session 398; the FormSubmission precedent).
class Purchase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'product_price_id',
        'contact_id',
        'stripe_session_id',
        'amount_paid',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
