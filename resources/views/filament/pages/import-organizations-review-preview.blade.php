<div class="space-y-4 text-sm">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="font-semibold text-gray-700 dark:text-gray-200">
            {{ number_format($orgsTotal) }} organization{{ $orgsTotal === 1 ? '' : 's' }} from this session
        </p>
        @if ($stagedTotal > 0)
            <p class="mt-1 text-xs text-gray-500">
                {{ number_format($stagedTotal) }} staged update{{ $stagedTotal === 1 ? '' : 's' }} to existing organizations
            </p>
        @endif
    </div>

    @if ($organizations->isEmpty())
        <p class="text-xs text-gray-500">No new organizations in this session.</p>
    @else
        <table class="w-full text-left">
            <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="py-1 pr-3">Name</th>
                    <th class="py-1 pr-3">Type</th>
                    <th class="py-1 pr-3">Website</th>
                    <th class="py-1 pr-3">Email</th>
                    <th class="py-1 pr-3">Location</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($organizations as $org)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="py-1 pr-3">{{ $org->name }}</td>
                        <td class="py-1 pr-3 text-xs font-mono">{{ $org->type ?? '—' }}</td>
                        <td class="py-1 pr-3 text-xs">{{ \Illuminate\Support\Str::limit($org->website ?? '—', 30) }}</td>
                        <td class="py-1 pr-3 text-xs">{{ $org->email ?? '—' }}</td>
                        <td class="py-1 pr-3 text-xs text-gray-500">{{ trim(($org->city ?? '') . ($org->state ? ', ' . $org->state : '')) ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($orgsTotal > $organizations->count())
            <p class="text-xs text-gray-400">… {{ $orgsTotal - $organizations->count() }} more (showing first {{ $organizations->count() }})</p>
        @endif
    @endif
</div>
