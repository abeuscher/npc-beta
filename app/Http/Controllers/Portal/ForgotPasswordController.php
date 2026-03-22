<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('portal.account');
        }

        return view('portal.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $account = PortalAccount::where('email', $request->input('email'))->first();

        if ($account) {
            Password::broker('portal_accounts')->sendResetLink(
                ['email' => $request->input('email')]
            );
        }

        return back()->with('status', "If an account with that email exists, we've sent a reset link.");
    }
}
