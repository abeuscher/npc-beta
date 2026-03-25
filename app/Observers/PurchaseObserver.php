<?php

namespace App\Observers;

use App\Models\Purchase;
use App\Services\ActivityLogger;

class PurchaseObserver
{
    public function created(Purchase $purchase): void
    {
        if (! $purchase->contact_id) {
            return;
        }

        $purchase->loadMissing('contact', 'product');

        ActivityLogger::log(
            $purchase->contact,
            'purchased',
            $purchase->product?->name,
            $purchase->stripe_session_id ? ['stripe_session_id' => $purchase->stripe_session_id] : [],
        );
    }

    public function updated(Purchase $purchase): void
    {
        if (! $purchase->wasChanged('status') || $purchase->status !== 'cancelled') {
            return;
        }

        if (! $purchase->contact_id) {
            return;
        }

        $purchase->loadMissing('contact', 'product');

        ActivityLogger::log(
            $purchase->contact,
            'cancelled',
            $purchase->product?->name,
        );
    }
}
