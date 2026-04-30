<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RandomDataGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RandomDataGeneratorController extends Controller
{
    public function store(Request $request, RandomDataGenerator $generator): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'counts'               => ['required', 'array'],
            'counts.contacts'      => ['integer', 'min:0', 'max:1000'],
            'counts.events'        => ['integer', 'min:0', 'max:1000'],
            'counts.registrations' => ['integer', 'min:0', 'max:1000'],
            'counts.donations'     => ['integer', 'min:0', 'max:1000'],
            'counts.memberships'   => ['integer', 'min:0', 'max:1000'],
            'counts.posts'         => ['integer', 'min:0', 'max:1000'],
            'counts.products'      => ['integer', 'min:0', 'max:1000'],
        ]);

        try {
            $summary = $generator->generate($validated['counts']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['rdg' => $e->getMessage()]);
        }

        return back()->with('rdg_status', sprintf(
            'Generated %d contacts, %d events, %d registrations, %d donations, %d memberships, %d posts, %d products, %d transactions.',
            $summary['contacts'],
            $summary['events'],
            $summary['registrations'],
            $summary['donations'],
            $summary['memberships'],
            $summary['posts'],
            $summary['products'],
            $summary['transactions'],
        ));
    }

    public function wipe(Request $request, RandomDataGenerator $generator): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $deleted = $generator->wipe();

        return back()->with('rdg_status', sprintf(
            'Wiped scrub data: %d contacts, %d events, %d registrations, %d donations, %d memberships, %d posts, %d products, %d transactions.',
            $deleted['contacts'],
            $deleted['events'],
            $deleted['registrations'],
            $deleted['donations'],
            $deleted['memberships'],
            $deleted['posts'],
            $deleted['products'],
            $deleted['transactions'],
        ));
    }

    public function seedCollections(Request $request, RandomDataGenerator $generator): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $labels = $generator->seedWidgetCollections();

        return back()->with('rdg_status', $labels === []
            ? 'No widgets declared a demo seeder.'
            : 'Widget demo collections seeded: ' . implode(', ', $labels) . '.');
    }
}
