@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)

    @if (session('success'))
        <div role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section>
        <h2>Mailing Address</h2>

        @if (session('household_address_choice'))
            <p>You are part of the <strong>{{ $contact->householdName() }}</strong>. How would you like to apply this change?</p>

            <form method="POST" action="{{ route('portal.account.update-address') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="city" value="{{ old('city') }}">
                <input type="hidden" name="state" value="{{ old('state') }}">
                <input type="hidden" name="postal_code" value="{{ old('postal_code') }}">
                <input type="hidden" name="country" value="{{ old('country') }}">
                <input type="hidden" name="scope" value="mine">
                <button type="submit">Update just my address (I will leave the household)</button>
            </form>

            <form method="POST" action="{{ route('portal.account.update-address') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="city" value="{{ old('city') }}">
                <input type="hidden" name="state" value="{{ old('state') }}">
                <input type="hidden" name="postal_code" value="{{ old('postal_code') }}">
                <input type="hidden" name="country" value="{{ old('country') }}">
                <input type="hidden" name="scope" value="household">
                <button type="submit">Update the household address for everyone</button>
            </form>
        @else
            <form method="POST" action="{{ route('portal.account.update-address') }}">
                @csrf
                @method('PATCH')

                <label for="pce_city">City</label>
                <input type="text" id="pce_city" name="city" value="{{ old('city', $contact->city) }}" maxlength="255">

                <label for="pce_state">State / Province</label>
                <input type="text" id="pce_state" name="state" value="{{ old('state', $contact->state) }}" maxlength="255">

                <label for="pce_postal_code">Postal Code</label>
                <input type="text" id="pce_postal_code" name="postal_code" value="{{ old('postal_code', $contact->postal_code) }}" maxlength="20">

                <label for="pce_country">Country</label>
                <input type="text" id="pce_country" name="country" value="{{ old('country', $contact->country) }}" maxlength="255">

                <button type="submit">Save address</button>
            </form>
        @endif
    </section>

    <section>
        <h2>Email Address</h2>
        <p>Your current email address is <strong>{{ $portalUser->email }}</strong>.</p>
        <p>Enter a new email below. A confirmation link will be sent to the new address — your email will not change until you click it.</p>
        <form method="POST" action="{{ route('portal.account.request-email-change') }}">
            @csrf

            <label for="pce_email">New Email Address</label>
            <input type="email" id="pce_email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">

            <button type="submit">Send confirmation</button>
        </form>
    </section>

@endif
