@php
    $donationStatus = request()->query('donation');
    $checkoutUrl    = '/' . ltrim(config('site.donations_prefix', 'donate'), '/') . '/checkout';
    $heading        = $config['heading'] ?? null;

    $rawAmounts     = $config['amounts'] ?? null;
    $presetAmounts  = $rawAmounts
        ? array_values(array_filter(array_map(
            fn ($v) => is_numeric(trim($v)) && (float) trim($v) > 0 ? (float) trim($v) : null,
            explode(',', $rawAmounts)
          )))
        : [10, 25, 50, 100];

    $showMonthly    = ($config['show_monthly'] ?? false) == true;
    $showAnnual     = ($config['show_annual']  ?? false) == true;
    $showFrequency  = $showMonthly || $showAnnual;
    $successPage    = $config['success_page'] ?? null;

    $activeFunds    = \App\Models\Fund::where('is_active', true)->orderBy('name')->get();
    $showFunds      = $activeFunds->isNotEmpty();
@endphp

<div x-data="{
    amount: null,
    customAmount: '',
    showCustom: false,
    frequency: 'one_off',
    fundId: null,
    loading: false,
    error: null,
    selectAmount(val) {
        this.amount = val;
        this.showCustom = false;
        this.customAmount = '';
    },
    selectCustom() {
        this.amount = null;
        this.showCustom = true;
    },
    getAmount() {
        return this.showCustom ? parseFloat(this.customAmount) : this.amount;
    },
    async submit() {
        this.error = null;
        const amt = this.getAmount();
        if (!amt || isNaN(amt) || amt < 1) {
            this.error = 'Please enter an amount of at least $1.';
            return;
        }
        if (amt > 10000) {
            this.error = 'Amount cannot exceed $10,000.';
            return;
        }
        this.loading = true;
        try {
            const res = await fetch('{{ $checkoutUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    amount: amt,
                    type: this.frequency === 'one_off' ? 'one_off' : 'recurring',
                    frequency: this.frequency === 'one_off' ? null : this.frequency,
                    @if ($successPage) success_page: @js($successPage), @endif
                    ...(this.fundId ? { fund_id: this.fundId } : {}),
                }),
            });
            const data = await res.json();
            if (data.url) {
                window.location.href = data.url;
            } else {
                this.error = data.error ?? 'Something went wrong. Please try again.';
                this.loading = false;
            }
        } catch (e) {
            this.error = 'Something went wrong. Please try again.';
            this.loading = false;
        }
    }
}">

    @if ($heading)
        <h2 class="text-2xl font-heading font-bold mb-4 text-gray-900 dark:text-gray-100">{{ $heading }}</h2>
    @endif

    @if ($donationStatus === 'success')
        <div role="status" class="rounded border border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/30 p-4 mb-4">
            <strong class="text-green-800 dark:text-green-200">Thank you for your donation!</strong>
            <p class="text-green-700 dark:text-green-300 mt-1">Your contribution has been received. You will receive a receipt by email.</p>
        </div>

    @elseif ($donationStatus === 'cancelled')
        <div role="status" class="rounded border border-yellow-300 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-900/30 p-4 mb-4">
            <p class="text-yellow-800 dark:text-yellow-200">Your donation was cancelled. No payment was taken.</p>
        </div>

    @else

        <div class="mb-6">
            <p class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Select an amount</p>

            <div class="flex flex-wrap gap-2">
                @foreach ($presetAmounts as $preset)
                    <button type="button"
                            @click="selectAmount({{ $preset }})"
                            :aria-pressed="amount === {{ $preset }} && !showCustom"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded font-medium text-gray-700 dark:text-gray-300 hover:border-primary hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                            :class="amount === {{ $preset }} && !showCustom ? 'border-primary bg-primary text-white dark:text-white' : ''">
                        ${{ number_format($preset, strpos((string) $preset, '.') !== false ? 2 : 0) }}
                    </button>
                @endforeach
                <button type="button" @click="selectCustom()" :aria-pressed="showCustom"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded font-medium text-gray-700 dark:text-gray-300 hover:border-primary hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                        :class="showCustom ? 'border-primary bg-primary text-white dark:text-white' : ''">Custom</button>
            </div>

            <div x-show="showCustom" x-cloak class="mt-3">
                <label for="donation_custom_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom amount ($)</label>
                <input type="number" id="donation_custom_amount" x-model="customAmount"
                       min="1" max="10000" step="0.01" placeholder="Enter amount"
                       class="block w-full max-w-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
            </div>
        </div>

        @if ($showFrequency)
        <div class="mb-6">
            <p class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Frequency</p>

            <div class="flex flex-wrap gap-2">
                <button type="button" @click="frequency = 'one_off'" :aria-pressed="frequency === 'one_off'"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded font-medium text-gray-700 dark:text-gray-300 hover:border-primary hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                        :class="frequency === 'one_off' ? 'border-primary bg-primary text-white dark:text-white' : ''">One-time</button>
                @if ($showMonthly)
                <button type="button" @click="frequency = 'monthly'" :aria-pressed="frequency === 'monthly'"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded font-medium text-gray-700 dark:text-gray-300 hover:border-primary hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                        :class="frequency === 'monthly' ? 'border-primary bg-primary text-white dark:text-white' : ''">Monthly</button>
                @endif
                @if ($showAnnual)
                <button type="button" @click="frequency = 'annual'"  :aria-pressed="frequency === 'annual'"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded font-medium text-gray-700 dark:text-gray-300 hover:border-primary hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                        :class="frequency === 'annual' ? 'border-primary bg-primary text-white dark:text-white' : ''">Annual</button>
                @endif
            </div>
        </div>
        @endif

        @if ($showFunds)
        <div class="mb-6">
            <label for="donation_fund" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Designate to a fund (optional)</label>
            <select id="donation_fund" x-model="fundId"
                    class="block w-full max-w-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                <option value="">General / Unrestricted</option>
                @foreach ($activeFunds as $fund)
                    <option value="{{ $fund->id }}">{{ $fund->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div x-show="error" x-cloak role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4 text-red-800 dark:text-red-200">
            <span x-text="error"></span>
        </div>

        <button type="button" @click="submit()" :disabled="loading"
                class="px-6 py-2.5 bg-primary text-white rounded font-semibold hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
            <span x-show="!loading">Donate</span>
            <span x-show="loading" x-cloak>Processing…</span>
        </button>

    @endif

</div>
