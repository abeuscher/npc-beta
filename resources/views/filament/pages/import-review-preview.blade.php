<div class="space-y-6 text-sm">

    {{-- ── New contacts ── --}}
    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">New contacts</h3>

        @if ($total > 20)
            <p class="text-gray-500">Showing first 20 of {{ number_format($total) }} new contacts.</p>
        @else
            <p class="text-gray-500">{{ number_format($total) }} new contact(s) in this import.</p>
        @endif

        @if ($contacts->isEmpty())
            <p class="text-gray-400 italic">No new contacts.</p>
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

    {{-- ── Staged updates ── --}}
    @if ($stagedTotal > 0)
    <div class="space-y-3">
        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Staged updates to existing contacts</h3>

        @if ($stagedTotal > 20)
            <p class="text-gray-500">Showing first 20 of {{ number_format($stagedTotal) }} staged updates.</p>
        @else
            <p class="text-gray-500">{{ number_format($stagedTotal) }} existing contact(s) will be updated on approval.</p>
        @endif

        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b text-xs text-gray-500 uppercase">
                    <th class="py-1 pr-3">Contact</th>
                    <th class="py-1 pr-3">Proposed changes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stagedUpdates as $update)
                <tr class="border-b border-gray-100 align-top">
                    <td class="py-1 pr-3">
                        {{ trim(($update->contact->first_name ?? '') . ' ' . ($update->contact->last_name ?? '')) ?: '—' }}
                        @if ($update->contact?->email)
                            <br><span class="text-gray-400 text-xs">{{ $update->contact->email }}</span>
                        @endif
                    </td>
                    <td class="py-1 pr-3">
                        @if (! empty($update->attributes))
                            <ul class="space-y-0.5">
                                @foreach ($update->attributes as $field => $value)
                                <li>
                                    <span class="font-mono text-xs text-gray-500">{{ $field }}</span>
                                    <span class="text-gray-400 mx-1">→</span>
                                    <span>
                                        @if (is_array($value))
                                            @if ($field === 'custom_fields')
                                                @foreach ($value as $cfHandle => $cfValue)
                                                    <span class="inline-block mr-2"><span class="font-mono text-xs text-gray-400">{{ $cfHandle }}:</span> {{ is_scalar($cfValue) ? $cfValue : json_encode($cfValue) }}</span>
                                                @endforeach
                                            @else
                                                <span class="font-mono text-xs text-gray-500">{{ json_encode($value) }}</span>
                                            @endif
                                        @else
                                            {{ $value ?? '(blank)' }}
                                        @endif
                                    </span>
                                </li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-gray-400 italic">No field changes</span>
                        @endif
                        @if (! empty($update->tag_ids))
                            <p class="mt-1 text-xs text-gray-400">+ {{ count($update->tag_ids) }} tag(s) will be added</p>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
