<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvitationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $record = InvitationToken::findByPlainToken($token);

        if (! $record || ! $record->isPending()) {
            return view('admin.invitation-invalid');
        }

        return view('admin.invitation-set-password', ['token' => $token]);
    }

    public function store(Request $request, string $token)
    {
        $record = InvitationToken::findByPlainToken($token);

        if (! $record || ! $record->isPending()) {
            return view('admin.invitation-invalid');
        }

        $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $record->user;

        $user->update([
            'password'  => Hash::make($request->password),
            'is_active' => true,
        ]);

        $record->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect(filament()->getPanel('admin')->getUrl());
    }
}
