@php $item = $widgetData['item'] ?? null; @endphp
@if ($item)
    @php
        $isCancelled  = $item['status'] === 'cancelled';
        $isAtCapacity = (bool) $item['is_at_capacity'];
        $mode         = $item['registration_mode'] ?? 'open';
        $isPaid       = ! $item['is_free'];
        $regOpen      = $mode === 'open' && ! $isCancelled && ! $isAtCapacity;
        $portalUser   = auth('portal')->user();
        $portalContact = $portalUser?->contact;
    @endphp

    @if (session('registration_success') || request()->query('registration') === 'success')
        <div role="status" class="alert alert--success">
            <strong>You're registered!</strong>
            <p>We look forward to seeing you.</p>
        </div>

    @elseif (request()->query('registration') === 'cancelled')
        <div role="status" class="alert alert--warning">
            <p>Registration was cancelled. No payment was taken.</p>
        </div>

    @elseif ($isCancelled)
        <div role="alert" class="alert alert--error">
            <strong>This event has been cancelled.</strong>
        </div>

    @elseif ($isAtCapacity)
        <p class="text-muted">This event is at capacity. Registration is closed.</p>

    @elseif ($mode === 'external')
        @if (filled($item['external_registration_url']))
            <a href="{{ $item['external_registration_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn--primary">Register for this event &rarr;</a>
        @endif

    @elseif ($mode === 'closed')
        <p class="text-muted">Registration for this event is currently closed.</p>

    @elseif ($mode === 'none')
        <p class="text-muted">No registration required &mdash; just show up!</p>

    @elseif ($regOpen)
        <h2>Register</h2>

        @if ($errors->has('register'))
            <div role="alert" class="alert alert--error">{{ $errors->first('register') }}</div>
        @endif

        @if ($isPaid)
            <p class="text-muted" style="margin-bottom: 1rem;">Registration fee: <strong>${{ number_format((float) $item['price'], 2) }}</strong></p>
        @endif

        @if ($portalUser)
            <form method="POST" action="{{ $isPaid ? route('portal.events.checkout', $item['slug']) : route('portal.events.register', $item['slug']) }}" style="margin-bottom: 0.5rem;">
                @csrf
                <button type="submit" class="btn btn--primary">
                    {{ $isPaid ? 'Register & pay as member' : 'Register as member' }}
                </button>
            </form>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <input type="hidden" name="redirect_after_logout" value="{{ url()->current() }}">
                <button type="submit" class="btn btn--link text-sm text-muted">Log out</button>
            </form>
        @else
            <a href="{{ route('portal.login', ['intended' => url()->current()]) }}" class="btn btn--primary" style="margin-bottom: 1rem;">Register as member</a>

            <h3 style="margin-top: 1.5rem; margin-bottom: 0.75rem;">Or register as a guest</h3>

            <form method="POST" action="{{ $isPaid ? route('events.checkout', $item['slug']) : route('events.register', $item['slug']) }}" class="form-grid">
                @csrf

                {{-- Honeypot --}}
                <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                    <label for="_hp_name">Leave this empty</label>
                    <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="_form_start" value="{{ time() }}">

                <div class="col-{{ \App\Support\FormFieldConfig::width('name') }}">
                    <label for="reg_name" class="form-label">Full Name <span aria-hidden="true" class="required-star">*</span></label>
                    <input type="text" id="reg_name" name="name" required
                           value="{{ old('name') }}" autocomplete="name">
                    @error('name')<span role="alert" class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('email') }}">
                    <label for="reg_email" class="form-label">Email Address <span aria-hidden="true" class="required-star">*</span></label>
                    <input type="email" id="reg_email" name="email" required
                           value="{{ old('email') }}" autocomplete="email">
                    @error('email')<span role="alert" class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('phone') }}">
                    <label for="reg_phone" class="form-label">Phone <span class="text-muted-light" style="font-weight: normal;">(optional)</span></label>
                    <input type="tel" id="reg_phone" name="phone"
                           value="{{ old('phone') }}" autocomplete="tel">
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('company') }}">
                    <label for="reg_company" class="form-label">Organization <span class="text-muted-light" style="font-weight: normal;">(optional)</span></label>
                    <input type="text" id="reg_company" name="company"
                           value="{{ old('company') }}" autocomplete="organization">
                </div>

                @if ($item['is_in_person'])
                    <fieldset class="form-fieldset col-12">
                        <legend class="form-label">Mailing Address <span class="text-muted-light" style="font-weight: normal;">(optional)</span></legend>
                        <div class="form-grid">
                            <div class="col-{{ \App\Support\FormFieldConfig::width('address_line_1') }}">
                                <label for="reg_addr1" class="form-label">Address</label>
                                <input type="text" id="reg_addr1" name="address_line_1"
                                       value="{{ old('address_line_1') }}" autocomplete="address-line1">
                            </div>
                            <div class="col-{{ \App\Support\FormFieldConfig::width('address_line_2') }}">
                                <label for="reg_addr2" class="form-label">Address Line 2</label>
                                <input type="text" id="reg_addr2" name="address_line_2"
                                       value="{{ old('address_line_2') }}" autocomplete="address-line2">
                            </div>
                            <div class="col-{{ \App\Support\FormFieldConfig::width('city') }}">
                                <label for="reg_city" class="form-label">City</label>
                                <input type="text" id="reg_city" name="city"
                                       value="{{ old('city') }}" autocomplete="address-level2">
                            </div>
                            <div class="col-{{ \App\Support\FormFieldConfig::width('state') }}">
                                <label for="reg_state" class="form-label">State</label>
                                <x-state-select
                                    name="state"
                                    id="reg_state"
                                    :value="old('state')"
                                />
                            </div>
                            <div class="col-{{ \App\Support\FormFieldConfig::width('zip') }}">
                                <label for="reg_zip" class="form-label">Zip Code</label>
                                <input type="text" id="reg_zip" name="zip"
                                       value="{{ old('zip') }}" autocomplete="postal-code">
                            </div>
                        </div>
                    </fieldset>
                @endif

                @if ($item['mailing_list_opt_in_enabled'])
                    <div class="col-12">
                        <label class="form-check-label">
                            <input type="checkbox" name="mailing_list_opt_in" value="1"
                                   {{ old('mailing_list_opt_in') ? 'checked' : '' }}>
                            Keep me informed about future events and updates
                        </label>
                    </div>
                @endif

                <div class="col-12">
                    <button type="submit" class="btn btn--primary">
                        {{ $isPaid ? 'Register & pay' : 'Register for this event' }}
                    </button>
                </div>
            </form>
        @endif
    @endif
@endif
