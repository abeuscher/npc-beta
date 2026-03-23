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
    public function show(Request $request): mixed
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('portal.account');
        }

        $intended = $request->query('intended');
        if ($intended && str_starts_with($intended, url('/'))) {
            $request->session()->put('url.intended', $intended);
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

        $after = $request->input('redirect_after_logout');

        if ($after && str_starts_with($after, url('/'))) {
            return redirect($after);
        }

        return redirect()->route('portal.login');
    }
}
