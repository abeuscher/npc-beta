<div class="space-y-3 text-sm">
    @if ($total > 20)
        <p class="text-gray-500">Showing first 20 of {{ number_format($total) }} contacts.</p>
    @else
        <p class="text-gray-500">{{ number_format($total) }} contact(s) in this import.</p>
    @endif

    @if ($contacts->isEmpty())
        <p class="text-gray-400 italic">No contacts found.</p>
    @else
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b text-xs text-gray-500 uppercase">
                    <th class="py-1 pr-3">Name</th>
                    <th class="py-1 pr-3">Email</th>
                    <th class="py-1 pr-3">Phone</th>
                    <th class="py-1 pr-3">Location</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contacts as $contact)
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3">{{ trim("{$contact->first_name} {$contact->last_name}") ?: '—' }}</td>
                    <td class="py-1 pr-3">{{ $contact->email ?: '—' }}</td>
                    <td class="py-1 pr-3">{{ $contact->phone ?: '—' }}</td>
                    <td class="py-1 pr-3">{{ collect([$contact->city, $contact->state])->filter()->implode(', ') ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
