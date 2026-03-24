@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)
    <p>Welcome back, {{ $contact->first_name }}.</p>

    <dl>
        <dt>Email</dt>
        <dd>{{ $portalUser->email }}</dd>

        @if ($contact->household_id && $contact->household_id !== $contact->id)
        <dt>Household</dt>
        <dd>{{ $contact->householdName() }}</dd>
        @endif
    </dl>
@endif
