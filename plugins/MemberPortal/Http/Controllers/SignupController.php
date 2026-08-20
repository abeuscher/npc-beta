<?php

namespace Plugins\MemberPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PageController;
use App\Mail\PortalEmailVerification;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\PortalAccount;
use App\Models\SiteSetting;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

class SignupController extends Controller
{
    public function show(): mixed
    {
        $prefix = SiteSetting::get('system_prefix', 'system');
        $slug   = $prefix ? $prefix . '/signup' : 'signup';

        return app(PageController::class)->show($slug);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot — silently discard bot submissions
        if ($request->filled('_hp_name')) {
            return redirect()->route('portal.verification.notice');
        }

        $formStart = (int) $request->input('_form_start', 0);
        if ($formStart > 0 && (time() - $formStart) < 3) {
            return redirect()->route('portal.verification.notice');
        }

        // Composition safety (session 393): membership_tiers is plugin-owned
        // schema — on a memberships-absent composition the exists rule and
        // the complimentary-tier create below must not run. Route presence is
        // the established plugin-presence signal; with the rule dropped,
        // tier_id never reaches $validated and the tier block self-skips.
        $membershipsActive = Route::has('membership.checkout');

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255'],
            'password'   => ['required', 'string', 'min:12', 'confirmed'],
        ];

        if ($membershipsActive) {
            $rules['tier_id'] = ['nullable', 'uuid', 'exists:membership_tiers,id'];
        }

        $validated = $request->validate($rules);

        // If a portal account already exists for this email, give no signal — silent redirect.
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

        // Create membership for complimentary tier if selected
        if (! empty($validated['tier_id'])) {
            $tier = MembershipTier::find($validated['tier_id']);
            if ($tier && $tier->is_active && (! $tier->default_price || $tier->default_price <= 0)) {
                Membership::create([
                    'contact_id' => $contact->id,
                    'tier_id'    => $tier->id,
                    'status'     => 'active',
                    'source'     => Source::HUMAN,
                    'starts_on'  => now()->toDateString(),
                    'expires_on' => match ($tier->billing_interval) {
                        'monthly'  => now()->addMonth()->toDateString(),
                        'annual'   => now()->addYear()->toDateString(),
                        default    => null, // lifetime / one_time
                    },
                ]);
            }
        }

        return redirect()->route('portal.verification.notice');
    }
}
