<?php

namespace App\Http\Controllers;

use App\Models\ProductPrice;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductCheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_price_id' => ['required', 'uuid', 'exists:product_prices,id'],
        ]);

        $price   = ProductPrice::with('product')->findOrFail($validated['product_price_id']);
        $product = $price->product;

        if ($product->isAtCapacity()) {
            return back()->withErrors(['checkout' => 'This product is no longer available — it has reached capacity.']);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['checkout' => 'Payment processing is not configured.']);
        }

        $referer    = strtok($request->header('Referer', url('/')), '?');
        $successUrl = $referer . '?checkout=success';
        $cancelUrl  = $referer . '?checkout=cancelled';

        $lineItem = $price->stripe_price_id
            ? ['price' => $price->stripe_price_id, 'quantity' => 1]
            : [
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => 0,
                    'product_data' => [
                        'name' => $product->name . ' — ' . $price->label,
                    ],
                ],
                'quantity' => 1,
            ];

        try {
            $configuredMethods = SiteSetting::get('stripe_payment_method_types') ?? ['card'];
            if (empty($configuredMethods)) {
                $configuredMethods = ['card'];
            }

            $stripe  = new \Stripe\StripeClient($secret);
            $session = $stripe->checkout->sessions->create([
                'mode'                 => 'payment',
                'payment_method_types' => array_values($configuredMethods),
                'line_items'           => [$lineItem],
                'metadata'             => ['product_price_id' => $price->id],
                'success_url'          => $successUrl,
                'cancel_url'           => $cancelUrl,
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['checkout' => 'Could not initiate checkout. Please try again.']);
        }

        return redirect()->away($session->url);
    }
}
