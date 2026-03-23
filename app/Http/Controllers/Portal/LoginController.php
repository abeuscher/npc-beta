<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PageController;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): mixed
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('portal.account');
        }

        $prefix = SiteSetting::get('system_prefix', 'system');
        $slug   = $prefix ? $prefix . '/login' : 'login';

        return app(PageController::class)->show($slug);
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

            $user = Auth::guard('portal')->user();

            if (! $user->is_active) {
                Auth::guard('portal')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withInput(['email' => $request->input('email')])->withErrors([
                    'email' => 'Invalid email or password.',
                ]);
            }

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
