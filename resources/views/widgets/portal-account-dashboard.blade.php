@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)
    <p class="text-gray-700 dark:text-gray-300 mb-4">Welcome back, {{ $contact->first_name }}.</p>

    <dl class="space-y-2">
        <div class="flex gap-2">
            <dt class="font-medium text-gray-900 dark:text-gray-100">Email</dt>
            <dd class="text-gray-600 dark:text-gray-400">{{ $portalUser->email }}</dd>
        </div>

        @if ($contact->household_id && $contact->household_id !== $contact->id)
        <div class="flex gap-2">
            <dt class="font-medium text-gray-900 dark:text-gray-100">Household</dt>
            <dd class="text-gray-600 dark:text-gray-400">{{ $contact->householdName() }}</dd>
        </div>
        @endif
    </dl>
@endif
