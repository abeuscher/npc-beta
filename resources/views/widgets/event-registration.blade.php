@php $event = $pageContext->event($config['event_slug'] ?? null); @endphp
@isset($event)
    @php
        $isCancelled  = $event->status === 'cancelled';
        $isAtCapacity = $event->isAtCapacity();
        $mode         = $event->registration_mode ?? 'open';
        $regOpen      = $mode === 'open' && ! $isCancelled && ! $isAtCapacity && $event->is_free;
        $portalUser   = auth('portal')->user();
        $portalContact = $portalUser?->contact;
    @endphp

    @if (session('registration_success'))
        <div role="status" class="rounded border border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/30 p-4 mb-4">
            <strong class="text-green-800 dark:text-green-200">You're registered!</strong>
            <p class="text-green-700 dark:text-green-300 mt-1">We look forward to seeing you.</p>
        </div>

    @elseif ($isCancelled)
        <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4">
            <strong class="text-red-800 dark:text-red-200">This event has been cancelled.</strong>
        </div>

    @elseif ($isAtCapacity)
        <p class="text-gray-600 dark:text-gray-400">This event is at capacity. Registration is closed.</p>

    @elseif ($mode === 'external')
        @if (filled($event->external_registration_url))
            <a href="{{ $event->external_registration_url }}" target="_blank" rel="noopener noreferrer" class="inline-block px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 no-underline">Register for this event &rarr;</a>
        @endif

    @elseif ($mode === 'closed')
        <p class="text-gray-600 dark:text-gray-400">Registration for this event is currently closed.</p>

    @elseif ($mode === 'none')
        <p class="text-gray-600 dark:text-gray-400">No registration required &mdash; just show up!</p>

    @elseif ($regOpen)
        <h2 class="text-2xl font-heading font-bold mb-4 text-gray-900 dark:text-gray-100">Register</h2>

        @if ($errors->has('register'))
            <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4 text-red-800 dark:text-red-200">{{ $errors->first('register') }}</div>
        @endif

        @if ($portalUser)
            <form method="POST" action="{{ route('portal.events.register', $event->slug) }}" class="mb-2">
                @csrf
                <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Register as member</button>
            </form>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <input type="hidden" name="redirect_after_logout" value="{{ url()->current() }}">
                <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 underline hover:opacity-80 cursor-pointer">Log out</button>
            </form>
        @else
            <a href="{{ route('portal.login', ['intended' => url()->current()]) }}" class="inline-block px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 no-underline mb-4">Register as member</a>

            <h3 class="text-lg font-heading font-semibold mt-6 mb-3 text-gray-900 dark:text-gray-100">Or register as a guest</h3>

            <form method="POST" action="{{ route('events.register', $event->slug) }}" class="space-y-4">
                @csrf

                {{-- Honeypot --}}
                <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                    <label for="_hp_name">Leave this empty</label>
                    <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="_form_start" value="{{ time() }}">

                <div>
                    <label for="reg_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name <span aria-hidden="true" class="text-red-500">*</span></label>
                    <input type="text" id="reg_name" name="name" required
                           value="{{ old('name') }}" autocomplete="name"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                    @error('name')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="reg_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address <span aria-hidden="true" class="text-red-500">*</span></label>
                    <input type="email" id="reg_email" name="email" required
                           value="{{ old('email') }}" autocomplete="email"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                    @error('email')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="reg_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="tel" id="reg_phone" name="phone"
                           value="{{ old('phone') }}" autocomplete="tel"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="reg_company" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organization <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" id="reg_company" name="company"
                           value="{{ old('company') }}" autocomplete="organization"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                @if ($event->is_in_person)
                    <fieldset class="border border-gray-200 dark:border-gray-700 rounded p-4 space-y-3">
                        <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Mailing Address <span class="text-gray-400 font-normal">(optional)</span></legend>
                        <div>
                            <label for="reg_addr1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <input type="text" id="reg_addr1" name="address_line_1"
                                   value="{{ old('address_line_1') }}" autocomplete="address-line1"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label for="reg_addr2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address Line 2</label>
                            <input type="text" id="reg_addr2" name="address_line_2"
                                   value="{{ old('address_line_2') }}" autocomplete="address-line2"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label for="reg_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                            <input type="text" id="reg_city" name="city"
                                   value="{{ old('city') }}" autocomplete="address-level2"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label for="reg_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State</label>
                            <input type="text" id="reg_state" name="state"
                                   value="{{ old('state') }}" autocomplete="address-level1"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label for="reg_zip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zip Code</label>
                            <input type="text" id="reg_zip" name="zip"
                                   value="{{ old('zip') }}" autocomplete="postal-code"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                        </div>
                    </fieldset>
                @endif

                @if ($event->mailing_list_opt_in_enabled)
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="mailing_list_opt_in" value="1"
                                   {{ old('mailing_list_opt_in') ? 'checked' : '' }}
                                   class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
                            Keep me informed about future events and updates
                        </label>
                    </div>
                @endif

                <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Register for this event</button>
            </form>
        @endif
    @endif
@endisset
