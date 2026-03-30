@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
    $regs       = $contact
        ? $contact->eventRegistrations()->with('event')->orderByDesc('registered_at')->get()
        : collect();
@endphp

@if ($portalUser && $contact)
    @if ($regs->isEmpty())
        <p class="text-gray-600 dark:text-gray-400">No event registrations on file.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4 font-semibold text-gray-900 dark:text-gray-100">Event</th>
                        <th class="py-2 pr-4 font-semibold text-gray-900 dark:text-gray-100">Date</th>
                        <th class="py-2 font-semibold text-gray-900 dark:text-gray-100">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($regs as $reg)
                        <tr>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $reg->event->title ?? '—' }}</td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $reg->event?->starts_at?->format('F j, Y') ?? '—' }}</td>
                            <td class="py-2 text-gray-700 dark:text-gray-300">{{ ucfirst($reg->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
