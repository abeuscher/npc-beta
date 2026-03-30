@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)

    @if (session('success'))
        <div role="status" class="rounded border border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/30 p-4 mb-4 text-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4">
            <ul class="list-disc pl-5 text-sm text-red-800 dark:text-red-200 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-8">
        <h2 class="text-xl font-heading font-bold mb-4 text-gray-900 dark:text-gray-100">Mailing Address</h2>

        @if (session('household_address_choice'))
            <p class="text-gray-600 dark:text-gray-400 mb-4">You are part of the <strong>{{ $contact->householdName() }}</strong>. How would you like to apply this change?</p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <form method="POST" action="{{ route('portal.account.update-address') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="city" value="{{ old('city') }}">
                    <input type="hidden" name="state" value="{{ old('state') }}">
                    <input type="hidden" name="postal_code" value="{{ old('postal_code') }}">
                    <input type="hidden" name="country" value="{{ old('country') }}">
                    <input type="hidden" name="scope" value="mine">
                    <button type="submit" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-primary hover:text-primary cursor-pointer">Update just my address (I will leave the household)</button>
                </form>

                <form method="POST" action="{{ route('portal.account.update-address') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="city" value="{{ old('city') }}">
                    <input type="hidden" name="state" value="{{ old('state') }}">
                    <input type="hidden" name="postal_code" value="{{ old('postal_code') }}">
                    <input type="hidden" name="country" value="{{ old('country') }}">
                    <input type="hidden" name="scope" value="household">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded text-sm font-medium hover:opacity-80 cursor-pointer">Update the household address for everyone</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('portal.account.update-address') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="pce_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                    <input type="text" id="pce_city" name="city" value="{{ old('city', $contact->city) }}" maxlength="255"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="pce_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State / Province</label>
                    <input type="text" id="pce_state" name="state" value="{{ old('state', $contact->state) }}" maxlength="255"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="pce_postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Postal Code</label>
                    <input type="text" id="pce_postal_code" name="postal_code" value="{{ old('postal_code', $contact->postal_code) }}" maxlength="20"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="pce_country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                    <input type="text" id="pce_country" name="country" value="{{ old('country', $contact->country) }}" maxlength="255"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Save address</button>
            </form>
        @endif
    </section>

    <section>
        <h2 class="text-xl font-heading font-bold mb-4 text-gray-900 dark:text-gray-100">Email Address</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-2">Your current email address is <strong class="text-gray-900 dark:text-gray-100">{{ $portalUser->email }}</strong>.</p>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Enter a new email below. A confirmation link will be sent to the new address — your email will not change until you click it.</p>
        <form method="POST" action="{{ route('portal.account.request-email-change') }}" class="space-y-4">
            @csrf

            <div>
                <label for="pce_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Email Address</label>
                <input type="email" id="pce_email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"
                       class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
            </div>

            <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Send confirmation</button>
        </form>
    </section>

@endif
