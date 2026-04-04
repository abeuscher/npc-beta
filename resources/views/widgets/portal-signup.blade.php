@php
    $tiers = \App\Models\MembershipTier::where('is_active', true)->where('is_archived', false)->orderBy('sort_order')->get();
    $hasPaidTiers = $tiers->contains(fn ($t) => $t->default_price && $t->default_price > 0);
@endphp

@if ($errors->any())
    <div role="alert" class="alert alert--error">
        <ul class="error-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST"
      x-data="{ tierId: '{{ old('tier_id', '') }}', tiers: {{ Js::from($tiers->map(fn ($t) => ['id' => $t->id, 'price' => (float) $t->default_price])) }} }"
      :action="(() => { const t = tiers.find(t => t.id === tierId); return t && t.price > 0 ? '{{ route('membership.checkout') }}' : '{{ route('portal.signup.post') }}'; })()"
      class="form-stack">
    @csrf

    {{-- Honeypot --}}
    <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
        <label for="_hp_name_sw">Leave this empty</label>
        <input type="text" id="_hp_name_sw" name="_hp_name" tabindex="-1" autocomplete="off">
    </div>
    <input type="hidden" name="_form_start" value="{{ time() }}">

    <div>
        <label for="sw_first_name" class="form-label">First name <span aria-hidden="true" class="required-star">*</span></label>
        <input type="text" id="sw_first_name" name="first_name" required
               value="{{ old('first_name') }}" autocomplete="given-name">
        @error('first_name')<span role="alert" class="form-error">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_last_name" class="form-label">Last name <span aria-hidden="true" class="required-star">*</span></label>
        <input type="text" id="sw_last_name" name="last_name" required
               value="{{ old('last_name') }}" autocomplete="family-name">
        @error('last_name')<span role="alert" class="form-error">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_email" class="form-label">Email address <span aria-hidden="true" class="required-star">*</span></label>
        <input type="email" id="sw_email" name="email" required
               value="{{ old('email') }}" autocomplete="email">
        @error('email')<span role="alert" class="form-error">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_password" class="form-label">Password <span aria-hidden="true" class="required-star">*</span></label>
        <input type="password" id="sw_password" name="password" required
               autocomplete="new-password" minlength="12">
        <small class="form-hint">Minimum 12 characters.</small>
        @error('password')<span role="alert" class="form-error">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_password_confirmation" class="form-label">Confirm password <span aria-hidden="true" class="required-star">*</span></label>
        <input type="password" id="sw_password_confirmation" name="password_confirmation" required
               autocomplete="new-password" minlength="12">
    </div>

    @if ($tiers->isNotEmpty())
        <div>
            <label for="sw_tier" class="form-label">Membership tier</label>
            <select id="sw_tier" name="tier_id" x-model="tierId">
                <option value="">No membership</option>
                @foreach ($tiers as $tier)
                    <option value="{{ $tier->id }}">
                        {{ $tier->name }}
                        @if ($tier->default_price && $tier->default_price > 0)
                            — ${{ number_format((float) $tier->default_price, 2) }}/{{ $tier->billing_interval === 'monthly' ? 'month' : ($tier->billing_interval === 'annual' ? 'year' : ($tier->billing_interval === 'lifetime' ? 'lifetime' : 'one-time')) }}
                        @else
                            — Free
                        @endif
                    </option>
                @endforeach
            </select>
            @error('tier_id')<span role="alert" class="form-error">{{ $message }}</span>@enderror
        </div>
    @endif

    <button type="submit" class="btn btn--primary">
        <span x-text="(() => { const t = tiers.find(t => t.id === tierId); return t && t.price > 0 ? 'Create account & pay' : 'Create account'; })()">Create account</span>
    </button>
</form>

<p class="text-muted text-sm" style="margin-top: 1rem;"><a href="{{ route('portal.login') }}">Already have an account? Log in</a></p>
