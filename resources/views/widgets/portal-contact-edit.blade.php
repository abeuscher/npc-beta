@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)

    @if (session('success'))
        <div role="status" class="alert alert--success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="alert alert--error">
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="portal-section">
        <h2>Mailing Address</h2>

        @if (session('household_address_choice'))
            <p class="text-muted portal-description">You are part of the <strong>{{ $contact->householdName() }}</strong>. How would you like to apply this change?</p>

            <div class="portal-household-choice">
                <form method="POST" action="{{ route('portal.account.update-address') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="city" value="{{ old('city') }}">
                    <input type="hidden" name="state" value="{{ old('state') }}">
                    <input type="hidden" name="postal_code" value="{{ old('postal_code') }}">
                    <input type="hidden" name="country" value="{{ old('country') }}">
                    <input type="hidden" name="scope" value="mine">
                    <button type="submit" class="btn btn--secondary text-sm">Update just my address (I will leave the household)</button>
                </form>

                <form method="POST" action="{{ route('portal.account.update-address') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="city" value="{{ old('city') }}">
                    <input type="hidden" name="state" value="{{ old('state') }}">
                    <input type="hidden" name="postal_code" value="{{ old('postal_code') }}">
                    <input type="hidden" name="country" value="{{ old('country') }}">
                    <input type="hidden" name="scope" value="household">
                    <button type="submit" class="btn btn--primary text-sm">Update the household address for everyone</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('portal.account.update-address') }}" class="form-grid">
                @csrf
                @method('PATCH')

                <div class="col-{{ \App\Support\FormFieldConfig::width('city') }}">
                    <label for="pce_city" class="form-label">City</label>
                    <input type="text" id="pce_city" name="city" value="{{ old('city', $contact->city) }}" maxlength="255">
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('state') }}">
                    <label for="pce_state" class="form-label">State / Province</label>
                    <input type="text" id="pce_state" name="state" value="{{ old('state', $contact->state) }}" maxlength="255">
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('postal_code') }}">
                    <label for="pce_postal_code" class="form-label">Postal Code</label>
                    <input type="text" id="pce_postal_code" name="postal_code" value="{{ old('postal_code', $contact->postal_code) }}" maxlength="20">
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('country') }}">
                    <label for="pce_country" class="form-label">Country</label>
                    <input type="text" id="pce_country" name="country" value="{{ old('country', $contact->country) }}" maxlength="255">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn--primary">Save address</button>
                </div>
            </form>
        @endif
    </section>

    <section class="portal-section">
        <h2>Email Address</h2>
        <p class="text-muted portal-description--tight">Your current email address is <strong>{{ $portalUser->email }}</strong>.</p>
        <p class="text-muted portal-description">Enter a new email below. A confirmation link will be sent to the new address — your email will not change until you click it.</p>
        <form method="POST" action="{{ route('portal.account.request-email-change') }}" class="form-stack">
            @csrf

            <div>
                <label for="pce_email" class="form-label">New Email Address</label>
                <input type="email" id="pce_email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
            </div>

            <button type="submit" class="btn btn--primary">Send confirmation</button>
        </form>
    </section>

@endif
