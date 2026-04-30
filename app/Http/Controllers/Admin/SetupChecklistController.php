<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Setup\SetupChecklist;
use Illuminate\Http\RedirectResponse;

class SetupChecklistController extends Controller
{
    public function markComplete(SetupChecklist $checklist): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $checklist->markComplete();

        return back()->with('setup_checklist_status', 'Setup marked complete. The widget is now in health-check mode.');
    }

    public function reset(SetupChecklist $checklist): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $checklist->resetInstallState();

        return back()->with('setup_checklist_status', 'Install state reset. The full checklist is visible again.');
    }
}
