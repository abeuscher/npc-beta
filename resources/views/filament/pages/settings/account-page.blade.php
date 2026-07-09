<x-filament-panels::page>
    @php($account = $this->account)

    {{-- Prominent pre-lock warning: shown when a payment is overdue / in grace,
         so the person who can fix it sees it before the admin panel locks. --}}
    @if ($account['attention'])
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" />
                <div class="space-y-1">
                    <p class="font-semibold">A recent payment didn’t go through</p>
                    <p class="text-sm">
                        Please update your billing to keep admin access.
                        @if ($account['attention']['locksAt'])
                            If it isn’t resolved, admin access to this site will be paused on
                            <strong>{{ $account['attention']['locksAt'] }}</strong>.
                        @else
                            If it isn’t resolved, admin access to this site will be paused.
                        @endif
                        Your public site, donations, and member portal keep running — this only affects the back office.
                    </p>
                    @if ($account['portalUrl'])
                        <p class="pt-1">
                            <x-filament::link :href="$account['portalUrl']" target="_blank" rel="noopener noreferrer" class="font-medium">
                                Update your payment in the billing portal →
                            </x-filament::link>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Subscription: plan + plain-English status badge --}}
    <x-filament::section>
        <x-slot name="heading">Subscription</x-slot>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Plan</div>
                <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                    {{ $account['plan']['name'] ?? '—' }}
                </div>
                @if ($account['plan'] && $account['plan']['price'])
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $account['plan']['price'] }}</div>
                @endif
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
                <div class="mt-1">
                    <x-filament::badge :color="$account['status']['color']">
                        {{ $account['status']['label'] }}
                    </x-filament::badge>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Next invoice: date, amount, and line items (subscription + any hours) --}}
    @if ($account['nextInvoice'])
        <x-filament::section>
            <x-slot name="heading">Next invoice</x-slot>

            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @if ($account['nextInvoice']['date'])
                        Due {{ $account['nextInvoice']['date'] }}
                    @else
                        Upcoming
                    @endif
                </div>
                @if ($account['nextInvoice']['amount'])
                    <div class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $account['nextInvoice']['amount'] }}
                    </div>
                @endif
            </div>

            @if (! empty($account['nextInvoice']['lineItems']))
                <div class="mt-4 divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/10 dark:border-white/10">
                    @foreach ($account['nextInvoice']['lineItems'] as $item)
                        <div class="flex items-center justify-between gap-4 py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">{{ $item['description'] ?: 'Line item' }}</span>
                            <span class="font-medium text-gray-950 dark:text-white">{{ $item['amount'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- Billing contact (read-only) + hand-off to the hosted portal --}}
    <x-filament::section>
        <x-slot name="heading">Billing</x-slot>

        <div class="space-y-4">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Billing contact</div>
                <div class="mt-1 text-base text-gray-950 dark:text-white">
                    {{ $account['billingContactEmail'] ?? '—' }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Change the billing email, update your card, or view receipts in the billing portal.
                </div>
            </div>

            @if ($account['portalUrl'])
                <div>
                    <x-filament::button
                        tag="a"
                        :href="$account['portalUrl']"
                        target="_blank"
                        rel="noopener noreferrer"
                        icon="heroicon-o-arrow-top-right-on-square"
                        color="gray"
                    >
                        Manage billing
                    </x-filament::button>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Opens Stripe’s secure billing portal in a new tab.
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Honest, explicit staleness — fresh-to-the-day is the designed contract --}}
    @if ($account['asOf'])
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Billing information as of {{ $account['asOf']['relative'] }}@if ($account['asOf']['exact']) ({{ $account['asOf']['exact'] }})@endif.
            A payment made just now can take up to a day to appear here.
        </p>
    @endif
</x-filament-panels::page>
