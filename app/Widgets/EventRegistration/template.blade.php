@php $item = $widgetData['item'] ?? null; @endphp
@if ($item)
    @php
        $isCancelled    = $item['status'] === 'cancelled';
        $isAtCapacity   = (bool) $item['is_at_capacity'];
        $isSoldOut      = (bool) ($item['sold_out'] ?? false);
        $mode           = $item['registration_mode'] ?? 'open';
        $isFree         = (bool) $item['is_free'];
        $tiers          = $item['tiers'] ?? [];
        $tierCount      = count($tiers);
        $regOpen        = $mode === 'open' && ! $isCancelled && ! $isAtCapacity && ! $isSoldOut;
        $portalUser     = auth('portal')->user();
        $portalContact  = $portalUser?->contact;

        $singleTier  = $tierCount === 1 ? $tiers[0] : null;
        $singleIsPaid = $singleTier ? ((float) $singleTier['price']) > 0 : false;

        $submitLabel = match (true) {
            $tierCount === 0          => 'Register for this event',
            $tierCount === 1 && $singleIsPaid => 'Register & pay',
            $tierCount === 1          => 'Register for this event',
            default                   => 'Register',
        };
        $memberSubmitLabel = match (true) {
            $tierCount === 0          => 'Register as member',
            $tierCount === 1 && $singleIsPaid => 'Register & pay as member',
            $tierCount === 1          => 'Register as member',
            default                   => 'Register as member',
        };
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

    @elseif ($isSoldOut)
        <p class="text-muted">This event is sold out. Registration is closed.</p>

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
        @if ($errors->has('quantities'))
            <div role="alert" class="alert alert--error">{{ $errors->first('quantities') }}</div>
        @endif

        @php
            $renderTierPicker = function () use ($tiers, $tierCount) {
                if ($tierCount === 0) {
                    return;
                }

                $singleDefault = $tierCount === 1 ? 1 : 0;

                echo '<fieldset class="form-fieldset col-12 widget-event-registration__tier-picker">';
                if ($tierCount > 1) {
                    echo '<legend class="form-label">Select tickets <span aria-hidden="true" class="required-star">*</span></legend>';
                }

                foreach ($tiers as $tier) {
                    $isOut       = (bool) $tier['is_at_capacity'];
                    $remaining   = $tier['remaining_capacity'] ?? null;
                    $max         = $isOut ? 0 : ($remaining !== null ? (int) $remaining : 99);
                    $priceCents  = (int) round((float) $tier['price'] * 100);
                    $priceStr    = ((float) $tier['price']) > 0 ? '$' . number_format((float) $tier['price'], 2) : 'Free';
                    $oldQty      = old('quantities.' . $tier['id']);
                    $defaultQty  = $oldQty !== null ? max(0, (int) $oldQty) : $singleDefault;
                    if ($defaultQty > $max) {
                        $defaultQty = $max;
                    }

                    echo '<div class="widget-event-registration__tier-row">';
                    echo '<label for="qty_' . e($tier['id']) . '" class="widget-event-registration__tier-label">';
                    echo '<strong>' . e($tier['name']) . '</strong> &mdash; ' . e($priceStr);
                    if ($isOut) {
                        echo ' <span class="text-muted">(sold out)</span>';
                    } elseif ($remaining !== null) {
                        echo ' <span class="text-muted">&mdash; ' . (int) $remaining . ' left</span>';
                    }
                    echo '</label>';
                    echo '<input type="number"';
                    echo ' id="qty_' . e($tier['id']) . '"';
                    echo ' name="quantities[' . e($tier['id']) . ']"';
                    echo ' min="0" max="' . (int) $max . '" step="1"';
                    echo ' value="' . (int) $defaultQty . '"';
                    echo ' inputmode="numeric"';
                    echo ' data-tier-price-cents="' . (int) $priceCents . '"';
                    echo ' class="widget-event-registration__tier-quantity"';
                    if ($isOut) { echo ' disabled aria-disabled="true"'; }
                    echo '>';
                    echo '</div>';
                }

                $anyPaid = false;
                foreach ($tiers as $t) { if (((float) $t['price']) > 0) { $anyPaid = true; break; } }
                if ($anyPaid) {
                    echo '<div class="widget-event-registration__subtotal" aria-live="polite">';
                    echo 'Subtotal: <strong>$<span data-event-registration-subtotal>0.00</span></strong>';
                    echo '</div>';
                }
                echo '</fieldset>';
            };
        @endphp

        @if ($portalUser)
            <form method="POST" action="{{ route('portal.events.register', $item['slug']) }}" class="form-grid widget-event-registration__form" style="margin-bottom: 0.5rem;"
                  onsubmit="if(this._busy)return false;this._busy=true;setTimeout(()=>this.querySelector('button[type=submit]').disabled=true,0);">
                @csrf
                @php $renderTierPicker(); @endphp

                <div class="col-12">
                    <label for="reg_notes_member" class="form-label">Notes <span class="text-muted-light" style="font-weight: normal;">(optional)</span></label>
                    <textarea id="reg_notes_member" name="notes" rows="3"
                              placeholder="Anything we should know? Additional attendees, dietary needs, accessibility, etc.">{{ old('notes') }}</textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn--primary">
                        {{ $memberSubmitLabel }}
                    </button>
                </div>
            </form>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <input type="hidden" name="redirect_after_logout" value="{{ url()->current() }}">
                <button type="submit" class="btn btn--link text-sm text-muted">Log out</button>
            </form>
        @else
            <a href="{{ route('portal.login', ['intended' => url()->current()]) }}" class="btn btn--primary" style="margin-bottom: 1rem;">Register as member</a>

            <h3 style="margin-top: 1.5rem; margin-bottom: 0.75rem;">Or register as a guest</h3>

            <form method="POST" action="{{ route('events.register', $item['slug']) }}" class="form-grid widget-event-registration__form"
                  onsubmit="if(this._busy)return false;this._busy=true;setTimeout(()=>this.querySelector('button[type=submit]').disabled=true,0);">
                @csrf

                {{-- Honeypot --}}
                <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                    <label for="_hp_name">Leave this empty</label>
                    <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="_form_start" value="{{ time() }}">

                @php $renderTierPicker(); @endphp

                <div class="col-{{ \App\Support\FormFieldConfig::width('name') }}">
                    <label for="reg_name" class="form-label">Full Name <span aria-hidden="true" class="required-star">*</span></label>
                    <input type="text" id="reg_name" name="name" required
                           value="{{ old('name') }}" autocomplete="name"
                           @error('name') aria-describedby="reg_name-error" aria-invalid="true" @enderror>
                    @error('name')<span id="reg_name-error" role="alert" class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="col-{{ \App\Support\FormFieldConfig::width('email') }}">
                    <label for="reg_email" class="form-label">Email Address <span aria-hidden="true" class="required-star">*</span></label>
                    <input type="email" id="reg_email" name="email" required
                           value="{{ old('email') }}" autocomplete="email"
                           @error('email') aria-describedby="reg_email-error" aria-invalid="true" @enderror>
                    @error('email')<span id="reg_email-error" role="alert" class="form-error">{{ $message }}</span>@enderror
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
                    <label for="reg_notes" class="form-label">Notes <span class="text-muted-light" style="font-weight: normal;">(optional)</span></label>
                    <textarea id="reg_notes" name="notes" rows="3"
                              placeholder="Anything we should know? Additional attendees, dietary needs, accessibility, etc.">{{ old('notes') }}</textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn--primary">
                        {{ $submitLabel }}
                    </button>
                </div>
            </form>
        @endif

        <script>
        (function () {
            var forms = document.querySelectorAll('form.widget-event-registration__form');
            forms.forEach(function (form) {
                var subtotalEl = form.querySelector('[data-event-registration-subtotal]');
                var inputs = form.querySelectorAll('input[data-tier-price-cents]');
                if (inputs.length === 0) { return; }
                function recalc() {
                    var cents = 0;
                    inputs.forEach(function (i) {
                        var q = parseInt(i.value || '0', 10) || 0;
                        var p = parseInt(i.getAttribute('data-tier-price-cents') || '0', 10) || 0;
                        if (q < 0) q = 0;
                        cents += q * p;
                    });
                    if (subtotalEl) {
                        subtotalEl.textContent = (cents / 100).toFixed(2);
                    }
                }
                inputs.forEach(function (i) { i.addEventListener('input', recalc); });
                recalc();
            });
        })();
        </script>
    @endif
@endif
