<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AccountController extends Controller
{
    // ── Update address ────────────────────────────────────────────────────────

    public function updateAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city'        => ['nullable', 'string', 'max:255'],
            'state'       => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'max:255'],
        ]);

        Auth::guard('portal')->user()->contact->update($validated);

        return back()->with('success', 'Your address has been updated.');
    }

    // ── Change password ───────────────────────────────────────────────────────

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $account = Auth::guard('portal')->user();

        if (! Hash::check($request->input('current_password'), $account->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $account->update(['password' => $request->input('password')]);

        Auth::guard('portal')->logoutOtherDevices($request->input('password'));

        return back()->with('success', 'Your password has been updated.');
    }

    // ── Change email ──────────────────────────────────────────────────────────

    public function requestEmailChange(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $newEmail = $request->input('email');
        $account  = Auth::guard('portal')->user();

        if (strtolower($newEmail) === strtolower($account->email)) {
            return back()->withErrors(['email' => 'That is already your current email address.']);
        }

        $taken = \App\Models\PortalAccount::where('email', $newEmail)
            ->where('id', '!=', $account->id)
            ->exists();

        if ($taken) {
            return back()->withErrors(['email' => 'That email address is already in use.']);
        }

        $confirmUrl = URL::temporarySignedRoute(
            'portal.account.confirm-email',
            now()->addMinutes(60),
            ['id' => $account->id, 'email' => $newEmail]
        );

        Mail::to($newEmail)->send(new \App\Mail\PortalEmailChange($account, $newEmail, $confirmUrl));

        return back()->with('success', 'A confirmation link has been sent to ' . $newEmail . '. Click it within 60 minutes to confirm the change.');
    }

    public function confirmEmailChange(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired confirmation link.');
        }

        $account  = Auth::guard('portal')->user();
        $newEmail = $request->query('email');
        $id       = $request->query('id');

        if ((string) $account->id !== (string) $id) {
            abort(403);
        }

        $taken = \App\Models\PortalAccount::where('email', $newEmail)
            ->where('id', '!=', $account->id)
            ->exists();

        if ($taken) {
            return redirect()->route('portal.account')->with('success', 'That email address is no longer available.');
        }

        $account->update([
            'email'             => $newEmail,
            'email_verified_at' => now(),
        ]);

        if ($account->contact) {
            $account->contact->update(['email' => $newEmail]);
        }

        return redirect()->route('portal.account')->with('success', 'Your email address has been updated.');
    }
}
