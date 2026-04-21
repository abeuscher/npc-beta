<div class="space-y-4 text-sm">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="font-semibold text-gray-700 dark:text-gray-200">
            {{ number_format($notesTotal) }} note{{ $notesTotal === 1 ? '' : 's' }} from this session
        </p>
        @if ($stagedTotal > 0)
            <p class="mt-1 text-xs text-gray-500">
                {{ number_format($stagedTotal) }} staged update{{ $stagedTotal === 1 ? '' : 's' }} to existing notes
            </p>
        @endif
    </div>

    @if ($notes->isEmpty())
        <p class="text-xs text-gray-500">No new notes in this session.</p>
    @else
        <table class="w-full text-left">
            <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="py-1 pr-3">Contact</th>
                    <th class="py-1 pr-3">Type</th>
                    <th class="py-1 pr-3">Subject</th>
                    <th class="py-1 pr-3">Occurred</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notes as $note)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="py-1 pr-3">
                            @php
                                $c = $note->notable;
                                $name = $c ? trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) : null;
                            @endphp
                            {{ $name ?: ($c?->email ?? '—') }}
                        </td>
                        <td class="py-1 pr-3 text-xs font-mono">{{ $note->type }}</td>
                        <td class="py-1 pr-3 text-xs">{{ \Illuminate\Support\Str::limit($note->subject ?? strip_tags($note->body), 40) }}</td>
                        <td class="py-1 pr-3 text-xs text-gray-500">{{ $note->occurred_at?->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($notesTotal > $notes->count())
            <p class="text-xs text-gray-400">… {{ $notesTotal - $notes->count() }} more (showing first {{ $notes->count() }})</p>
        @endif
    @endif
</div>
