<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Product;
use App\Models\WaitlistEntry;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductWaitlistController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'email'      => ['required', 'email', 'max:255'],
            'name'       => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (! $product->isAtCapacity()) {
            return back()->withErrors(['waitlist' => 'This product is still available. Please purchase directly.']);
        }

        $email = $validated['email'];
        $name  = $validated['name'] ?? null;

        $contact = Contact::where('email', $email)->first();

        if (! $contact) {
            $nameParts = explode(' ', trim($name ?? ''), 2);
            $contact   = Contact::create([
                'first_name' => $nameParts[0] ?? '',
                'last_name'  => $nameParts[1] ?? '',
                'email'      => $email,
                'source'     => 'web_form',
            ]);
        }

        $alreadyListed = WaitlistEntry::where('product_id', $product->id)
            ->where('contact_id', $contact->id)
            ->exists();

        if (! $alreadyListed) {
            WaitlistEntry::create([
                'product_id' => $product->id,
                'contact_id' => $contact->id,
                'status'     => 'waiting',
            ]);

            ActivityLogger::log($contact, 'waitlisted', $product->name);
        }

        return back()->with('waitlist_success', true);
    }
}
