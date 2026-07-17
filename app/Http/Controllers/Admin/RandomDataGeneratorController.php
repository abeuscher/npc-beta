<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RandomDataGenerator;
use App\Support\StripeMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RandomDataGeneratorController extends Controller
{
    public function store(Request $request, RandomDataGenerator $generator): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        // Live-Stripe guard (session 370, Security S1): refuse to generate
        // synthetic data — including fake donations/transactions — on an install
        // configured against a live Stripe key. The widget hides the form in this
        // state; this backstops a direct POST. Wipe stays available.
        if (StripeMode::isLive()) {
            return back()->withErrors([
                'rdg' => 'Data generation is disabled on a live-Stripe install (sk_live_…). Switch to a test key to generate synthetic data.',
            ]);
        }

        $validated = $request->validate([
            'counts'               => ['required', 'array'],
            'counts.contacts'      => ['integer', 'min:0', 'max:1000'],
            'counts.organizations' => ['integer', 'min:0', 'max:1000'],
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
            'Generated %d contacts, %d organizations, %d events, %d registrations, %d donations, %d memberships, %d posts, %d products, %d transactions.',
            $summary['contacts'],
            $summary['organizations'],
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
            'Wiped scrub data: %d contacts, %d organizations, %d events, %d registrations, %d donations, %d memberships, %d posts, %d products, %d transactions.',
            $deleted['contacts'],
            $deleted['organizations'],
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
