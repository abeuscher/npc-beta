<?php

namespace App\Http\Controllers;

use App\Models\ProductPrice;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductCheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_price_id' => ['required', 'uuid', 'exists:product_prices,id'],
            'success_page'     => ['nullable', 'string', 'exists:pages,slug'],
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
        $successUrl = isset($validated['success_page'])
            ? url($validated['success_page']) . '?checkout=success'
            : $referer . '?checkout=success';
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
            $session = (new StripeCheckoutService())->createSession(
                lineItems: [$lineItem],
                metadata: ['product_price_id' => $price->id],
                successUrl: $successUrl,
                cancelUrl: $cancelUrl,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['checkout' => 'Could not initiate checkout. Please try again.']);
        }

        return redirect()->away($session->url);
    }
}
