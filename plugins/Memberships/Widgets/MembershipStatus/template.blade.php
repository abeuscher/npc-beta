<div class="np-membership-status">
    <h3 class="np-membership-status__heading">Membership Status</h3>
    @php
        $item = $widgetData['item'] ?? null;
        $isLifetime = $item && empty($item['membership_expires_on']) && ($item['membership_billing_interval'] ?? '') === 'lifetime';
    @endphp
    @if ($item === null)
        <p class="np-membership-status__empty">No active membership.</p>
    @else
        @if (! empty($item['membership_tier_name']))
            <p class="np-membership-status__tier">{{ $item['membership_tier_name'] }}</p>
        @endif
        @if (! empty($item['membership_billing_interval']))
            <p class="np-membership-status__interval">{{ ucfirst($item['membership_billing_interval']) }}</p>
        @endif
        @if (! empty($item['membership_starts_on']))
            <p class="np-membership-status__starts">Started: {{ $item['membership_starts_on'] }}</p>
        @endif
        @if (! empty($item['membership_expires_on']))
            <p class="np-membership-status__expires">Expires: {{ $item['membership_expires_on'] }}</p>
        @elseif ($isLifetime)
            <p class="np-membership-status__expires"><em>Lifetime</em></p>
        @endif
        @if (! empty($item['membership_amount_paid']))
            <p class="np-membership-status__amount">Amount paid: ${{ $item['membership_amount_paid'] }}</p>
        @endif
    @endif
</div>
