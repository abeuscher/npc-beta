<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        $account = Auth::guard('portal')->user();

        if ($account->hasVerifiedEmail()) {
            return redirect()->route('portal.account');
        }

        return view('portal.verify-email');
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $account = PortalAccount::findOrFail($id);

        if (! hash_equals(sha1($account->email), $hash)) {
            abort(403);
        }

        if (! $account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();
        }

        return redirect()->route('portal.account')->with('verified', true);
    }
}
