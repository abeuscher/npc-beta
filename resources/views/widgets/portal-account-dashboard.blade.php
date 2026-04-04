@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)
    <p class="text-muted" style="margin-bottom: 1rem;">Welcome back, {{ $contact->first_name }}.</p>

    <dl class="portal-dl">
        <div class="portal-dl__row">
            <dt class="portal-dl__label">Email</dt>
            <dd class="portal-dl__value">{{ $portalUser->email }}</dd>
        </div>

        @if ($contact->household_id && $contact->household_id !== $contact->id)
        <div class="portal-dl__row">
            <dt class="portal-dl__label">Household</dt>
            <dd class="portal-dl__value">{{ $contact->householdName() }}</dd>
        </div>
        @endif
    </dl>
@endif
