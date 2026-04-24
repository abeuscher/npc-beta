@php
    $registry = \App\Widgets\QuickActions\QuickActionsDefinition::actionRegistry();
    $selected = $config['actions'] ?? [];
@endphp
<div class="np-quick-actions">
    <h3 class="np-quick-actions__heading">Quick Actions</h3>
    @if (empty($selected))
        <p class="np-quick-actions__empty">No actions selected.</p>
    @else
        <ul class="np-quick-actions__list">
            @foreach ($selected as $key)
                @if (isset($registry[$key]))
                    @php
                        $entry = $registry[$key];
                        $url = $entry['url']();
                    @endphp
                    <li class="np-quick-actions__item">
                        <a href="{{ $url }}" class="np-quick-actions__link">{{ $entry['label'] }}</a>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</div>
