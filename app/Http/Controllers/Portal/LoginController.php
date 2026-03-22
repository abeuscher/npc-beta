<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('portal.account');
        }

        return view('portal.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->input('remember');

        if (Auth::guard('portal')->attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('portal.account'));
        }

        return back()->withInput(['email' => $request->input('email')])->withErrors([
            'email' => 'Invalid email or password.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
