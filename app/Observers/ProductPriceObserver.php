<?php

namespace App\Observers;

use App\Models\ProductPrice;
use Illuminate\Support\Facades\Log;

class ProductPriceObserver
{
    public function saved(ProductPrice $productPrice): void
    {
        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return;
        }

        $stripe        = new \Stripe\StripeClient($secret);
        $newAmount     = (float) $productPrice->amount;
        $oldStripeId   = $productPrice->getOriginal('stripe_price_id');
        $amountChanged = $productPrice->wasChanged('amount');

        // Archive old Stripe price when the amount changes and one existed.
        if ($amountChanged && $oldStripeId) {
            try {
                $stripe->prices->update($oldStripeId, ['active' => false]);
            } catch (\Throwable $e) {
                Log::warning('ProductPriceObserver: archive failed', [
                    'stripe_price_id' => $oldStripeId,
                    'error'           => $e->getMessage(),
                ]);
            }

            $productPrice->updateQuietly(['stripe_price_id' => null]);
            $productPrice->stripe_price_id = null;
        }

        // Create a new Stripe price when amount > 0 and no ID is recorded.
        if ($newAmount > 0 && empty($productPrice->stripe_price_id)) {
            try {
                $productPrice->loadMissing('product');

                $stripePrice = $stripe->prices->create([
                    'unit_amount'  => (int) round($newAmount * 100),
                    'currency'     => 'usd',
                    'product_data' => [
                        'name' => $productPrice->product->name . ' — ' . $productPrice->label,
                    ],
                ]);

                $productPrice->updateQuietly(['stripe_price_id' => $stripePrice->id]);
            } catch (\Throwable $e) {
                Log::error('ProductPriceObserver: price creation failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
