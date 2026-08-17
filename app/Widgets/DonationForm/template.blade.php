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

    $activeFunds    = \App\Models\Fund::where('is_active', true)->where('is_archived', false)->orderBy('name')->get();
    $showFunds      = $activeFunds->isNotEmpty();

    $donationFormConfig = [
        'checkoutUrl' => $checkoutUrl,
        'successPage' => $successPage,
        'csrfToken'   => csrf_token(),
    ];
@endphp

<div x-data="donationForm">
    <script x-ref="config" type="application/json">@json($donationFormConfig)</script>


    @include('widget-shared.inline-prose', ['tag' => 'h2', 'class' => '', 'key' => 'heading', 'type' => 'text', 'value' => $heading, 'label' => 'Heading'])

    @if ($donationStatus === 'success')
        <div role="status" class="alert alert--success">
            <strong>Thank you for your donation!</strong>
            <p>Your contribution has been received. You will receive a receipt by email.</p>
        </div>

    @elseif ($donationStatus === 'cancelled')
        <div role="status" class="alert alert--warning">
            <p>Your donation was cancelled. No payment was taken.</p>
        </div>

    @else

        <div class="donation-section">
            <p class="donation-section__heading">Select an amount</p>

            <div class="amount-grid">
                @foreach ($presetAmounts as $preset)
                    <button type="button"
                            @click="selectAmount({{ $preset }})"
                            :aria-pressed="amount === {{ $preset }} && !showCustom"
                            class="amount-btn"
                            :class="amount === {{ $preset }} && !showCustom ? 'is-active' : ''">
                        ${{ number_format($preset, strpos((string) $preset, '.') !== false ? 2 : 0) }}
                    </button>
                @endforeach
                <button type="button" @click="selectCustom()" :aria-pressed="showCustom"
                        class="amount-btn"
                        :class="showCustom ? 'is-active' : ''">Custom</button>
            </div>

            <div x-show="showCustom" x-cloak class="donation-section__sub">
                <label for="donation_custom_amount" class="form-label">Custom amount ($)</label>
                <input type="number" id="donation_custom_amount" x-model="customAmount"
                       min="1" max="10000" step="0.01" placeholder="Enter amount"
                       class="form-narrow">
            </div>
        </div>

        @if ($showFrequency)
        <div class="donation-section">
            <p class="donation-section__heading">Frequency</p>

            <div class="frequency-grid">
                <button type="button" @click="frequency = 'one_off'" :aria-pressed="frequency === 'one_off'"
                        class="frequency-btn"
                        :class="frequency === 'one_off' ? 'is-active' : ''">One-time</button>
                @if ($showMonthly)
                <button type="button" @click="frequency = 'monthly'" :aria-pressed="frequency === 'monthly'"
                        class="frequency-btn"
                        :class="frequency === 'monthly' ? 'is-active' : ''">Monthly</button>
                @endif
                @if ($showAnnual)
                <button type="button" @click="frequency = 'annual'"  :aria-pressed="frequency === 'annual'"
                        class="frequency-btn"
                        :class="frequency === 'annual' ? 'is-active' : ''">Annual</button>
                @endif
            </div>
        </div>
        @endif

        @if ($showFunds)
        <div class="donation-section" @custom-select-change="setFund($event)">
            <label for="donation_fund" class="form-label">Designate to a fund (optional)</label>
            <x-custom-select
                name="fund_id"
                id="donation_fund"
                :options="$activeFunds->map(fn ($f) => ['value' => (string) $f->id, 'label' => $f->name])->values()->all()"
                placeholder="General / Unrestricted"
            />
        </div>
        @endif

        <div x-show="error" x-cloak role="alert" class="alert alert--error">
            <span x-text="error"></span>
        </div>

        <button type="button" @click="submit()" :disabled="loading"
                class="btn btn--primary">
            <span x-show="!loading">Donate</span>
            <span x-show="loading" x-cloak>Processing…</span>
        </button>

    @endif

</div>
