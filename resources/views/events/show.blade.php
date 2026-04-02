@extends('layouts.public')

@section('content')
<main>

    @if ($isCancelled)
        <div role="alert">
            <strong>This event has been cancelled.</strong>
            <p>We're sorry for any inconvenience. Please check back for future events.</p>
        </div>
    @endif

    <article>
        <header>
            <h1>{{ $event->title }}</h1>

            @if ($dates->isNotEmpty())
                <ul>
                    @foreach ($dates as $d)
                        <li>
                            <time datetime="{{ $d->starts_at->toIso8601String() }}">
                                {{ $d->starts_at->format('F j, Y') }}
                            </time>
                            @php $loc = $d->effectiveLocation(); @endphp
                            @if ($loc['is_in_person'] && $loc['is_virtual'])
                                &mdash; In-person + Online
                                @if ($loc['city']) ({{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif) @endif
                            @elseif ($loc['is_in_person'])
                                @if ($loc['city']) &mdash; {{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif @endif
                            @elseif ($loc['is_virtual'])
                                &mdash; Online
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Physical location --}}
            @if ($event->is_in_person)
                <address>
                    @if ($event->address_line_1)
                        {{ $event->address_line_1 }}<br>
                    @endif
                    @if ($event->address_line_2)
                        {{ $event->address_line_2 }}<br>
                    @endif
                    @if ($event->city || $event->state || $event->zip)
                        {{ implode(', ', array_filter([$event->city, $event->state])) }}
                        @if ($event->zip) {{ $event->zip }} @endif
                    @endif
                    @if ($event->map_url)
                        <br>
                        <a href="{{ $event->map_url }}" target="_blank" rel="noopener noreferrer">
                            {{ $event->map_label ?? 'View map' }}
                        </a>
                    @endif
                </address>
            @endif

            {{-- Virtual location --}}
            @if ($event->is_virtual)
                <p>
                    <strong>Online event</strong>
                    @if ($event->meeting_url && (session('registration_success') || ! $event->registration_open))
                        &mdash;
                        <a href="{{ $event->meeting_url }}" target="_blank" rel="noopener noreferrer">
                            Join online
                        </a>
                    @elseif ($event->meeting_url && ! $isCancelled)
                        &mdash; Meeting link provided after registration.
                    @endif
                </p>
            @endif

            @if ($event->is_free)
                <p><strong>Free event</strong></p>
            @endif
        </header>

        @if ($event->description)
            <div class="event-description">
                {!! \App\Services\Media\InlineImageRenderer::process($event->description) !!}
            </div>
        @endif

        {{-- Registration area --}}
        <section>
            @if (session('registration_success') || request()->query('registration') === 'success')
                <div role="status">
                    <strong>You're registered!</strong>
                    <p>We look forward to seeing you. A confirmation will be sent to your email address.</p>
                </div>

            @elseif (request()->query('registration') === 'cancelled')
                <div role="status">
                    <p>Registration was cancelled. No payment was taken.</p>
                </div>

            @elseif ($isCancelled)
                {{-- No form — event is cancelled --}}

            @elseif ($isAtCapacity)
                <p>This event is at capacity. Registration is closed.</p>

            @elseif (! $event->registration_open)
                <p>Registration is not open for this event.</p>

            @elseif ($registrationOpen)
                <h2>Register</h2>

                @if ($errors->has('register'))
                    <div role="alert">{{ $errors->first('register') }}</div>
                @endif

                @if (! $event->is_free)
                    <p>Registration fee: <strong>${{ number_format((float) $event->price, 2) }}</strong></p>
                @endif

                <form method="POST" action="{{ $event->is_free ? route('events.register', $event->slug) : route('events.checkout', $event->slug) }}">
                    @csrf

                    {{-- Honeypot — hidden from real users, bots fill it --}}
                    <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                        <label for="_hp_name">Leave this empty</label>
                        <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="hidden" name="_form_start" value="{{ time() }}">

                    <div>
                        <label for="name">Full Name <span aria-hidden="true">*</span></label>
                        <input type="text" id="name" name="name" required
                               value="{{ old('name') }}" autocomplete="name">
                        @error('name')<span role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label for="email">Email Address <span aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" required
                               value="{{ old('email') }}" autocomplete="email">
                        @error('email')<span role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label for="phone">Phone <span>(optional)</span></label>
                        <input type="tel" id="phone" name="phone"
                               value="{{ old('phone') }}" autocomplete="tel">
                        @error('phone')<span role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label for="company">Organization <span>(optional)</span></label>
                        <input type="text" id="company" name="company"
                               value="{{ old('company') }}" autocomplete="organization">
                        @error('company')<span role="alert">{{ $message }}</span>@enderror
                    </div>

                    @if ($event->is_in_person)
                        <fieldset>
                            <legend>Mailing Address <span>(optional)</span></legend>

                            <div>
                                <label for="address_line_1">Address</label>
                                <input type="text" id="address_line_1" name="address_line_1"
                                       value="{{ old('address_line_1') }}" autocomplete="address-line1">
                                @error('address_line_1')<span role="alert">{{ $message }}</span>@enderror
                            </div>

                            <div>
                                <label for="address_line_2">Address Line 2</label>
                                <input type="text" id="address_line_2" name="address_line_2"
                                       value="{{ old('address_line_2') }}" autocomplete="address-line2">
                            </div>

                            <div>
                                <label for="city">City</label>
                                <input type="text" id="city" name="city"
                                       value="{{ old('city') }}" autocomplete="address-level2">
                            </div>

                            <div>
                                <label for="state">State</label>
                                <input type="text" id="state" name="state"
                                       value="{{ old('state') }}" autocomplete="address-level1">
                            </div>

                            <div>
                                <label for="zip">Zip Code</label>
                                <input type="text" id="zip" name="zip"
                                       value="{{ old('zip') }}" autocomplete="postal-code">
                            </div>
                        </fieldset>
                    @endif

                    <button type="submit">{{ $event->is_free ? 'Register for this event' : 'Register & pay' }}</button>
                </form>
            @endif
        </section>

        <footer>
            <a href="{{ route('events.index') }}">&larr; Back to Events</a>
        </footer>
    </article>

</main>
@endsection
