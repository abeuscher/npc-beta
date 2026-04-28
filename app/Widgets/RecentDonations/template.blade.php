<div class="np-recent-donations">
    <h3 class="np-recent-donations__heading">Recent Donations</h3>
    @php
        $items = $widgetData['items'] ?? [];
    @endphp
    @if (empty($items))
        <p class="np-recent-donations__empty">No recent donations.</p>
    @else
        <ul class="np-recent-donations__list">
            @foreach ($items as $donation)
                <li class="np-recent-donations__item">
                    @if (! empty($donation['donation_amount']))
                        <p class="np-recent-donations__amount">
                            <strong>${{ $donation['donation_amount'] }}</strong>
                        </p>
                    @endif
                    <p class="np-recent-donations__meta">
                        @if (! empty($donation['donation_date']))
                            <span class="np-recent-donations__date">{{ $donation['donation_date'] }}</span>
                        @endif
                        <span class="np-recent-donations__fund">{{ ! empty($donation['donation_fund_name']) ? $donation['donation_fund_name'] : 'Unrestricted' }}</span>
                        @if (($donation['donation_status'] ?? '') !== '' && $donation['donation_status'] !== 'active')
                            <span class="np-recent-donations__status"><em>{{ $donation['donation_status'] }}</em></span>
                        @endif
                    </p>
                    @if (! empty($donation['donation_origin']))
                        <p class="np-recent-donations__origin">Origin: <strong>{{ $donation['donation_origin'] }}</strong></p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
