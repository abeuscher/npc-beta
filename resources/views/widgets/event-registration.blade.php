@isset($event)
    @php
        $isCancelled  = $event->status === 'cancelled';
        $isAtCapacity = $event->isAtCapacity();
        $regOpen      = $event->registration_open && ! $isCancelled && ! $isAtCapacity && $event->is_free;
    @endphp

    @if (session('registration_success'))
        <div role="status">
            <strong>You're registered!</strong>
            <p>We look forward to seeing you.</p>
        </div>

    @elseif ($isCancelled)
        <div role="alert">
            <strong>This event has been cancelled.</strong>
        </div>

    @elseif ($isAtCapacity)
        <p>This event is at capacity. Registration is closed.</p>

    @elseif (! $event->registration_open)
        <p>Registration is not open for this event.</p>

    @elseif ($regOpen)
        <h2>Register</h2>

        @if ($errors->has('register'))
            <div role="alert">{{ $errors->first('register') }}</div>
        @endif

        <form method="POST" action="{{ route('events.register', $event->slug) }}">
            @csrf

            {{-- Honeypot --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                <label for="_hp_name">Leave this empty</label>
                <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="_form_start" value="{{ time() }}">

            <div>
                <label for="reg_name">Full Name <span aria-hidden="true">*</span></label>
                <input type="text" id="reg_name" name="name" required
                       value="{{ old('name') }}" autocomplete="name">
                @error('name')<span role="alert">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="reg_email">Email Address <span aria-hidden="true">*</span></label>
                <input type="email" id="reg_email" name="email" required
                       value="{{ old('email') }}" autocomplete="email">
                @error('email')<span role="alert">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="reg_phone">Phone <span>(optional)</span></label>
                <input type="tel" id="reg_phone" name="phone"
                       value="{{ old('phone') }}" autocomplete="tel">
            </div>

            <div>
                <label for="reg_company">Organization <span>(optional)</span></label>
                <input type="text" id="reg_company" name="company"
                       value="{{ old('company') }}" autocomplete="organization">
            </div>

            @if ($event->is_in_person)
                <fieldset>
                    <legend>Mailing Address <span>(optional)</span></legend>
                    <div>
                        <label for="reg_addr1">Address</label>
                        <input type="text" id="reg_addr1" name="address_line_1"
                               value="{{ old('address_line_1') }}" autocomplete="address-line1">
                    </div>
                    <div>
                        <label for="reg_addr2">Address Line 2</label>
                        <input type="text" id="reg_addr2" name="address_line_2"
                               value="{{ old('address_line_2') }}" autocomplete="address-line2">
                    </div>
                    <div>
                        <label for="reg_city">City</label>
                        <input type="text" id="reg_city" name="city"
                               value="{{ old('city') }}" autocomplete="address-level2">
                    </div>
                    <div>
                        <label for="reg_state">State</label>
                        <input type="text" id="reg_state" name="state"
                               value="{{ old('state') }}" autocomplete="address-level1">
                    </div>
                    <div>
                        <label for="reg_zip">Zip Code</label>
                        <input type="text" id="reg_zip" name="zip"
                               value="{{ old('zip') }}" autocomplete="postal-code">
                    </div>
                </fieldset>
            @endif

            <button type="submit">Register for this event</button>
        </form>
    @endif
@endisset
