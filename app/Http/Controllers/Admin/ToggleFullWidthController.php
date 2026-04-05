<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ToggleFullWidthController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $current = $request->session()->get('admin_full_width', false);
        $request->session()->put('admin_full_width', ! $current);

        return redirect()->back();
    }
}
