<div class="space-y-6 text-sm">

    {{-- ── Summary counts ── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @if ($donationsCount > 0)
            <div class="rounded-lg border bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xl font-bold text-green-600">{{ number_format($donationsCount) }}</p>
                <p class="mt-1 text-xs text-gray-500">Donation{{ $donationsCount === 1 ? '' : 's' }}</p>
            </div>
        @endif
        @if ($membershipsCount > 0)
            <div class="rounded-lg border bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xl font-bold text-green-600">{{ number_format($membershipsCount) }}</p>
                <p class="mt-1 text-xs text-gray-500">Membership{{ $membershipsCount === 1 ? '' : 's' }}</p>
            </div>
        @endif
        @if ($transactionsCount > 0)
            <div class="rounded-lg border bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xl font-bold text-blue-600">{{ number_format($transactionsCount) }}</p>
                <p class="mt-1 text-xs text-gray-500">Transaction{{ $transactionsCount === 1 ? '' : 's' }}</p>
            </div>
        @endif
        @if ($contactsCount > 0)
            <div class="rounded-lg border bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xl font-bold text-amber-500">{{ number_format($contactsCount) }}</p>
                <p class="mt-1 text-xs text-gray-500">Auto-created contact{{ $contactsCount === 1 ? '' : 's' }}</p>
            </div>
        @endif
    </div>

    {{-- ── Donations ── --}}
    @if ($record->model_type === \App\Enums\ImportModelType::Donation)
    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Donations</h3>

        @if ($donationsCount > $donations->count())
            <p class="text-gray-500">Showing first {{ $donations->count() }} of {{ number_format($donationsCount) }} donations.</p>
        @endif

        @if ($donations->isEmpty())
            <p class="text-gray-400 italic">No donations in this session.</p>
        @else
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b text-xs text-gray-500 uppercase">
                        <th class="py-1 pr-3">Contact</th>
                        <th class="py-1 pr-3">Amount</th>
                        <th class="py-1 pr-3">Type</th>
                        <th class="py-1 pr-3">Status</th>
                        <th class="py-1 pr-3">External ID</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($donations as $donation)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">
                                @php
                                    $c = $donation->contact;
                                    $name = $c ? trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) : null;
                                @endphp
                                {{ $name ?: ($c?->email ?? '—') }}
                            </td>
                            <td class="py-1 pr-3">{{ number_format((float) $donation->amount, 2) }} {{ strtoupper($donation->currency ?? 'USD') }}</td>
                            <td class="py-1 pr-3">{{ $donation->type ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $donation->status ?: '—' }}</td>
                            <td class="py-1 pr-3 font-mono text-xs text-gray-500">{{ $donation->external_id ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif

    {{-- ── Memberships ── --}}
    @if ($record->model_type === \App\Enums\ImportModelType::Membership)
    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Memberships</h3>

        @if ($membershipsCount > $memberships->count())
            <p class="text-gray-500">Showing first {{ $memberships->count() }} of {{ number_format($membershipsCount) }} memberships.</p>
        @endif

        @if ($memberships->isEmpty())
            <p class="text-gray-400 italic">No memberships in this session.</p>
        @else
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b text-xs text-gray-500 uppercase">
                        <th class="py-1 pr-3">Contact</th>
                        <th class="py-1 pr-3">Status</th>
                        <th class="py-1 pr-3">Starts</th>
                        <th class="py-1 pr-3">Expires</th>
                        <th class="py-1 pr-3">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($memberships as $membership)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">
                                @php
                                    $c = $membership->contact;
                                    $name = $c ? trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) : null;
                                @endphp
                                {{ $name ?: ($c?->email ?? '—') }}
                            </td>
                            <td class="py-1 pr-3">{{ $membership->status ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $membership->starts_on?->format('M j, Y') ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $membership->expires_on?->format('M j, Y') ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $membership->amount_paid !== null ? number_format((float) $membership->amount_paid, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif

    {{-- ── Invoice-detail → Transactions ── --}}
    @if ($record->model_type === \App\Enums\ImportModelType::InvoiceDetail)
    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Transactions</h3>

        @if ($transactionsCount > $transactions->count())
            <p class="text-gray-500">Showing first {{ $transactions->count() }} of {{ number_format($transactionsCount) }} transactions.</p>
        @endif

        @if ($transactions->isEmpty())
            <p class="text-gray-400 italic">No transactions in this session.</p>
        @else
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b text-xs text-gray-500 uppercase">
                        <th class="py-1 pr-3">Invoice #</th>
                        <th class="py-1 pr-3">Amount</th>
                        <th class="py-1 pr-3">Payment method</th>
                        <th class="py-1 pr-3">Occurred</th>
                        <th class="py-1 pr-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $tx)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">{{ $tx->invoice_number ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ number_format((float) $tx->amount, 2) }}</td>
                            <td class="py-1 pr-3">{{ $tx->payment_method ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $tx->occurred_at?->format('M j, Y') ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $tx->status ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif

    {{-- ── Auto-created contacts ── --}}
    @if ($contactsCount > 0)
    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Auto-created contacts</h3>

        @if ($contactsCount > $contacts->count())
            <p class="text-gray-500">Showing first {{ $contacts->count() }} of {{ number_format($contactsCount) }} auto-created contacts.</p>
        @endif

        @if ($contacts->isNotEmpty())
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b text-xs text-gray-500 uppercase">
                        <th class="py-1 pr-3">Name</th>
                        <th class="py-1 pr-3">Email</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contacts as $contact)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">{{ trim("{$contact->first_name} {$contact->last_name}") ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $contact->email ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif

    <p class="text-gray-500">
        Approve to confirm these records, or roll back to delete them.
    </p>
</div>
