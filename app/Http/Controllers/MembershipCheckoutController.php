<?php

namespace App\Http\Controllers;

use App\Mail\PortalEmailVerification;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\PortalAccount;
use App\Models\SiteSetting;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MembershipCheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $portalUser = auth('portal')->user();

        $rules = [
            'tier_id' => ['required', 'uuid', 'exists:membership_tiers,id'],
        ];

        // If not logged in, require account fields
        if (! $portalUser) {
            $rules += [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name'  => ['required', 'string', 'max:100'],
                'email'      => ['required', 'email', 'max:255'],
                'password'   => ['required', 'string', 'min:12', 'confirmed'],
            ];
        }

        $validated = $request->validate($rules);

        $tier = MembershipTier::findOrFail($validated['tier_id']);

        if (! $tier->is_active) {
            return back()->withErrors(['tier_id' => 'This membership tier is not currently available.']);
        }

        if (! $tier->default_price || $tier->default_price <= 0) {
            return back()->withErrors(['tier_id' => 'This tier does not require payment.']);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['checkout' => 'Payment processing is not configured.']);
        }

        // ── Resolve or create portal account + contact ──────────────────
        if ($portalUser) {
            $contact = $portalUser->contact;
            if (! $contact) {
                abort(403);
            }
        } else {
            // Honeypot checks
            if ($request->filled('_hp_name')) {
                return redirect()->route('portal.verification.notice');
            }
            $formStart = (int) $request->input('_form_start', 0);
            if ($formStart > 0 && (time() - $formStart) < 3) {
                return redirect()->route('portal.verification.notice');
            }

            // Silent redirect if portal account already exists
            if (PortalAccount::where('email', $validated['email'])->exists()) {
                return redirect()->route('portal.verification.notice');
            }

            $contact = Contact::withoutGlobalScopes()->where('email', $validated['email'])->first();

            if ($contact) {
                $contact->first_name = $validated['first_name'];
                $contact->last_name  = $validated['last_name'];
                $contact->save();
            } else {
                $contact = Contact::create([
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                    'email'      => $validated['email'],
                    'source'     => 'member_signup',
                ]);
            }

            $account = PortalAccount::create([
                'contact_id'        => $contact->id,
                'email'             => $validated['email'],
                'password'          => $validated['password'],
                'email_verified_at' => null,
            ]);

            Mail::to($account->email)->send(new PortalEmailVerification($account));
            Auth::guard('portal')->login($account);
        }

        // ── Create pending membership ───────────────────────────────────
        $membership = Membership::create([
            'contact_id'  => $contact->id,
            'tier_id'     => $tier->id,
            'status'      => 'pending',
            'amount_paid' => $tier->default_price,
        ]);

        // ── Build Stripe Checkout Session ───────────────────────────────
        $amountCents = (int) round((float) $tier->default_price * 100);

        $portalPrefix = SiteSetting::get('system_prefix', 'system');
        $accountUrl   = url(($portalPrefix ? '/' . $portalPrefix : '') . '/account');
        $successUrl   = $accountUrl . '?membership=success';
        $cancelUrl    = $accountUrl . '?membership=cancelled';

        $isSubscription = in_array($tier->billing_interval, ['monthly', 'annual']);

        try {
            $checkout = new StripeCheckoutService();

            if ($isSubscription) {
                $interval = $tier->billing_interval === 'annual' ? 'year' : 'month';

                $session = $checkout->createSession(
                    lineItems: [[
                        'price_data' => [
                            'currency'     => 'usd',
                            'unit_amount'  => $amountCents,
                            'product_data' => ['name' => $tier->name . ' Membership'],
                            'recurring'    => ['interval' => $interval],
                        ],
                        'quantity' => 1,
                    ]],
                    metadata: ['membership_id' => $membership->id],
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                    mode: 'subscription',
                    extra: ['customer_creation' => 'always'],
                );
            } else {
                $session = $checkout->createSession(
                    lineItems: [[
                        'price_data' => [
                            'currency'     => 'usd',
                            'unit_amount'  => $amountCents,
                            'product_data' => ['name' => $tier->name . ' Membership'],
                        ],
                        'quantity' => 1,
                    ]],
                    metadata: ['membership_id' => $membership->id],
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                );
            }
        } catch (\Throwable $e) {
            $membership->delete();
            return back()->withErrors(['checkout' => 'Could not initiate checkout. Please try again.']);
        }

        $membership->update([
            'stripe_session_id' => $session->id,
        ]);

        return redirect()->away($session->url);
    }
}
