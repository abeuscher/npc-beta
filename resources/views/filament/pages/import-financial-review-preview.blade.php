<div class="space-y-4 text-sm">
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

    <p class="text-gray-500">
        Approve to confirm these records, or roll back to delete them.
    </p>
</div>
