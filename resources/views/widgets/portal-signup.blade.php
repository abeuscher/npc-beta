@php
    $tiers = \App\Models\MembershipTier::where('is_active', true)->where('is_archived', false)->orderBy('sort_order')->get();
    $hasPaidTiers = $tiers->contains(fn ($t) => $t->default_price && $t->default_price > 0);
@endphp

@if ($errors->any())
    <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4">
        <ul class="list-disc pl-5 text-sm text-red-800 dark:text-red-200 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST"
      x-data="{ tierId: '{{ old('tier_id', '') }}', tiers: {{ Js::from($tiers->map(fn ($t) => ['id' => $t->id, 'price' => (float) $t->default_price])) }} }"
      :action="(() => { const t = tiers.find(t => t.id === tierId); return t && t.price > 0 ? '{{ route('membership.checkout') }}' : '{{ route('portal.signup.post') }}'; })()"
      class="space-y-4">
    @csrf

    {{-- Honeypot --}}
    <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
        <label for="_hp_name_sw">Leave this empty</label>
        <input type="text" id="_hp_name_sw" name="_hp_name" tabindex="-1" autocomplete="off">
    </div>
    <input type="hidden" name="_form_start" value="{{ time() }}">

    <div>
        <label for="sw_first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First name <span aria-hidden="true" class="text-red-500">*</span></label>
        <input type="text" id="sw_first_name" name="first_name" required
               value="{{ old('first_name') }}" autocomplete="given-name"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        @error('first_name')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last name <span aria-hidden="true" class="text-red-500">*</span></label>
        <input type="text" id="sw_last_name" name="last_name" required
               value="{{ old('last_name') }}" autocomplete="family-name"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        @error('last_name')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email address <span aria-hidden="true" class="text-red-500">*</span></label>
        <input type="email" id="sw_email" name="email" required
               value="{{ old('email') }}" autocomplete="email"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        @error('email')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password <span aria-hidden="true" class="text-red-500">*</span></label>
        <input type="password" id="sw_password" name="password" required
               autocomplete="new-password" minlength="12"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        <small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">Minimum 12 characters.</small>
        @error('password')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm password <span aria-hidden="true" class="text-red-500">*</span></label>
        <input type="password" id="sw_password_confirmation" name="password_confirmation" required
               autocomplete="new-password" minlength="12"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
    </div>

    @if ($tiers->isNotEmpty())
        <div>
            <label for="sw_tier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Membership tier</label>
            <select id="sw_tier" name="tier_id" x-model="tierId"
                    class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
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
            @error('tier_id')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
        </div>
    @endif

    <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">
        <span x-text="(() => { const t = tiers.find(t => t.id === tierId); return t && t.price > 0 ? 'Create account & pay' : 'Create account'; })()">Create account</span>
    </button>
</form>

<p class="mt-4 text-sm text-gray-600 dark:text-gray-400"><a href="{{ route('portal.login') }}" class="text-primary hover:opacity-80">Already have an account? Log in</a></p>
