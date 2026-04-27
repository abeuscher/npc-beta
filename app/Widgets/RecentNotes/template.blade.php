<div class="np-recent-notes">
    <h3 class="np-recent-notes__heading">Recent Notes</h3>
    @php
        $items = $widgetData['items'] ?? [];
    @endphp
    @if (empty($items))
        <p class="np-recent-notes__empty">No notes yet.</p>
    @else
        <ul class="np-recent-notes__list">
            @foreach ($items as $note)
                <li class="np-recent-notes__item">
                    @if (! empty($note['note_subject']))
                        <h4 class="np-recent-notes__subject">{{ $note['note_subject'] }}</h4>
                    @endif
                    <p class="np-recent-notes__meta">
                        @if (! empty($note['note_occurred_at']))
                            <span class="np-recent-notes__date">{{ $note['note_occurred_at'] }}</span>
                        @endif
                        @if (! empty($note['note_author_name']))
                            <span class="np-recent-notes__author"><em>{{ $note['note_author_name'] }}</em></span>
                        @endif
                    </p>
                    @if (! empty($note['note_body_excerpt']))
                        <p class="np-recent-notes__body">{{ $note['note_body_excerpt'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
