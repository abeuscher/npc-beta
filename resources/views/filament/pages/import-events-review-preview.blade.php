<div class="space-y-6 text-sm">

    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">New events</h3>

        @if ($eventsTotal > 20)
            <p class="text-gray-500">Showing first 20 of {{ number_format($eventsTotal) }} new events.</p>
        @else
            <p class="text-gray-500">{{ number_format($eventsTotal) }} new event(s) in this import.</p>
        @endif

        @if ($events->isEmpty())
            <p class="text-gray-400 italic">No new events (all rows reused existing events).</p>
        @else
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b text-xs text-gray-500 uppercase">
                        <th class="py-1 pr-3">Title</th>
                        <th class="py-1 pr-3">Starts</th>
                        <th class="py-1 pr-3">Ends</th>
                        <th class="py-1 pr-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">{{ $event->title ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $event->starts_at?->format('M j, Y g:ia') ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $event->ends_at?->format('M j, Y g:ia') ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $event->status ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Registrations</h3>

        @if ($registrationsTotal > 20)
            <p class="text-gray-500">Showing first 20 of {{ number_format($registrationsTotal) }} registrations.</p>
        @else
            <p class="text-gray-500">{{ number_format($registrationsTotal) }} registration(s) in this import.</p>
        @endif

        @if ($registrations->isEmpty())
            <p class="text-gray-400 italic">No registrations.</p>
        @else
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b text-xs text-gray-500 uppercase">
                        <th class="py-1 pr-3">Contact</th>
                        <th class="py-1 pr-3">Event</th>
                        <th class="py-1 pr-3">Ticket</th>
                        <th class="py-1 pr-3">Fee</th>
                        <th class="py-1 pr-3">Payment state</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registrations as $reg)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">
                                {{ trim(($reg->contact->first_name ?? '') . ' ' . ($reg->contact->last_name ?? '')) ?: ($reg->contact->email ?? '—') }}
                            </td>
                            <td class="py-1 pr-3">{{ $reg->event->title ?? '—' }}</td>
                            <td class="py-1 pr-3">{{ $reg->ticket_type ?: '—' }}</td>
                            <td class="py-1 pr-3">{{ $reg->ticket_fee !== null ? number_format((float) $reg->ticket_fee, 2) : '—' }}</td>
                            <td class="py-1 pr-3">{{ $reg->payment_state ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="space-y-1">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Transactions</h3>
        <p class="text-gray-500">{{ number_format($transactionsTotal) }} transaction(s) created by this import.</p>
    </div>
</div>
