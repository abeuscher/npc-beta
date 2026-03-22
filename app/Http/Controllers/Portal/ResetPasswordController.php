<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function show(string $token): View
    {
        return view('portal.reset-password', ['token' => $token]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'min:12', 'confirmed'],
        ]);

        $status = Password::broker('portal_accounts')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($account, $password) {
                $account->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $account->save();

                event(new PasswordReset($account));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('portal.login')
                ->with('status', 'Your password has been reset. Please log in.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
