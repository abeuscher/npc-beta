<div class="space-y-2 text-sm">
    @forelse ($errors as $error)
        <div class="flex gap-3 rounded border border-red-200 bg-red-50 px-3 py-2 dark:border-red-800 dark:bg-red-950">
            <span class="font-semibold text-red-700 dark:text-red-400">Row {{ $error['row'] ?? '?' }}</span>
            <span class="text-red-600 dark:text-red-300">{{ $error['message'] ?? '' }}</span>
        </div>
    @empty
        <p class="text-gray-500">No errors recorded.</p>
    @endforelse
</div>
