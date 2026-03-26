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
        <h2>{{ $heading }}</h2>
    @endif

    @if ($donationStatus === 'success')
        <div role="status">
            <strong>Thank you for your donation!</strong>
            <p>Your contribution has been received. You will receive a receipt by email.</p>
        </div>

    @elseif ($donationStatus === 'cancelled')
        <div role="status">
            <p>Your donation was cancelled. No payment was taken.</p>
        </div>

    @else

        <div>
            <p><strong>Select an amount</strong></p>

            <div>
                @foreach ($presetAmounts as $preset)
                    <button type="button" class="outline"
                            @click="selectAmount({{ $preset }})"
                            :aria-pressed="amount === {{ $preset }} && !showCustom">
                        ${{ number_format($preset, strpos((string) $preset, '.') !== false ? 2 : 0) }}
                    </button>
                @endforeach
                <button type="button" class="outline" @click="selectCustom()" :aria-pressed="showCustom">Custom</button>
            </div>

            <div x-show="showCustom" x-cloak>
                <label for="donation_custom_amount">Custom amount ($)</label>
                <input type="number" id="donation_custom_amount" x-model="customAmount"
                       min="1" max="10000" step="0.01" placeholder="Enter amount">
            </div>
        </div>

        @if ($showFrequency)
        <div>
            <p><strong>Frequency</strong></p>

            <div>
                <button type="button" class="outline" @click="frequency = 'one_off'" :aria-pressed="frequency === 'one_off'">One-time</button>
                @if ($showMonthly)
                <button type="button" class="outline" @click="frequency = 'monthly'" :aria-pressed="frequency === 'monthly'">Monthly</button>
                @endif
                @if ($showAnnual)
                <button type="button" class="outline" @click="frequency = 'annual'"  :aria-pressed="frequency === 'annual'">Annual</button>
                @endif
            </div>
        </div>
        @endif

        @if ($showFunds)
        <div>
            <label for="donation_fund">Designate to a fund (optional)</label>
            <select id="donation_fund" x-model="fundId">
                <option value="">General / Unrestricted</option>
                @foreach ($activeFunds as $fund)
                    <option value="{{ $fund->id }}">{{ $fund->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div x-show="error" x-cloak role="alert">
            <span x-text="error"></span>
        </div>

        <button type="button" @click="submit()" :disabled="loading">
            <span x-show="!loading">Donate</span>
            <span x-show="loading" x-cloak>Processing…</span>
        </button>

    @endif

</div>
