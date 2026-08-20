@php $member = $widgetData['item'] ?? null; @endphp

@if ($member && $member['has_contact'])
    <p class="text-muted portal-description">Welcome back, {{ $member['first_name'] }}.</p>

    <dl class="portal-dl">
        <div class="portal-dl__row">
            <dt class="portal-dl__label">Email</dt>
            <dd class="portal-dl__value">{{ $member['email'] }}</dd>
        </div>

        @if ($member['in_household'])
        <div class="portal-dl__row">
            <dt class="portal-dl__label">Household</dt>
            <dd class="portal-dl__value">{{ $member['household_name'] }}</dd>
        </div>
        @endif
    </dl>
@endif
