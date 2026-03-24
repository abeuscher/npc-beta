@if ($logs->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No activity recorded.</p>
@else
    <ul class="space-y-0 divide-y divide-gray-100 dark:divide-gray-700">
        @foreach ($logs as $log)
            <li class="py-2 first:pt-0 last:pb-0">
                <p class="text-sm text-gray-900 dark:text-white">
                    <span class="font-medium capitalize">{{ $log->event }}</span>
                    {{ class_basename($log->subject_type) }}@if ($log->description) — {{ $log->description }}@endif
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $log->created_at->format('M j, Y g:i a') }}
                </p>
            </li>
        @endforeach
    </ul>
@endif
