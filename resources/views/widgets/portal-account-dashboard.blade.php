@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
@endphp

@if ($portalUser && $contact)
    <p>Welcome back, {{ $contact->first_name }}.</p>

    <dl>
        <dt>Email</dt>
        <dd>{{ $portalUser->email }}</dd>
    </dl>
@endif
