<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoLoginController extends Controller
{
    public function __invoke()
    {
        abort_unless(isDemoMode(), 404);

        $user = User::firstOrCreate(
            ['email' => 'demo@demo.local'],
            [
                'name'      => 'Demo User',
                'password'  => Hash::make(Str::random(40)),
                'is_active' => true,
            ]
        );

        if (! $user->is_active) {
            $user->update(['is_active' => true]);
        }

        $user->syncRoles(['demo']);

        Auth::login($user);

        return redirect(filament()->getPanel('admin')->getUrl());
    }
}
