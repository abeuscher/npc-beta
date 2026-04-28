<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Services\StripeCheckoutService;
use App\WidgetPrimitive\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationCheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'       => ['required', 'numeric', 'min:1', 'max:10000'],
            'type'         => ['required', 'in:one_off,recurring'],
            'frequency'    => ['required_if:type,recurring', 'nullable', 'in:monthly,annual'],
            'success_page' => ['nullable', 'string', 'exists:pages,slug'],
            'fund_id'      => ['nullable', 'uuid', 'exists:funds,id'],
        ]);

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return response()->json(['error' => 'Payment processing is not configured.'], 422);
        }

        $donation = Donation::create([
            'fund_id'   => $validated['fund_id'] ?? null,
            'type'      => $validated['type'],
            'amount'    => $validated['amount'],
            'currency'  => 'usd',
            'frequency' => $validated['frequency'] ?? null,
            'status'    => 'pending',
            'source'    => Source::STRIPE_WEBHOOK,
        ]);

        $referer    = strtok($request->header('Referer', url('/')), '?');
        $successUrl = isset($validated['success_page'])
            ? url($validated['success_page']) . '?donation=success'
            : $referer . '?donation=success';
        $cancelUrl  = $referer . '?donation=cancelled';

        $amountCents = (int) round($validated['amount'] * 100);

        try {
            $checkout = new StripeCheckoutService();

            if ($validated['type'] === 'one_off') {
                $session = $checkout->createSession(
                    lineItems: [[
                        'price_data' => [
                            'currency'     => 'usd',
                            'unit_amount'  => $amountCents,
                            'product_data' => ['name' => 'Donation'],
                        ],
                        'quantity' => 1,
                    ]],
                    metadata: ['donation_id' => $donation->id],
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                );
            } else {
                $interval = $validated['frequency'] === 'annual' ? 'year' : 'month';

                $session = $checkout->createSession(
                    lineItems: [[
                        'price_data' => [
                            'currency'     => 'usd',
                            'unit_amount'  => $amountCents,
                            'product_data' => ['name' => 'Recurring Donation'],
                            'recurring'    => ['interval' => $interval],
                        ],
                        'quantity' => 1,
                    ]],
                    metadata: ['donation_id' => $donation->id],
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                    mode: 'subscription',
                    extra: ['customer_creation' => 'always'],
                );
            }
        } catch (\Throwable $e) {
            $donation->delete();
            return response()->json(['error' => 'Could not initiate checkout. Please try again.'], 422);
        }

        return response()->json(['url' => $session->url]);
    }
}
