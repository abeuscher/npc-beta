<div class="np-memos">
    <h3 class="np-memos__heading">Memos</h3>
    @php
        $items = $widgetData['items'] ?? [];
    @endphp
    @if (empty($items))
        <p class="np-memos__empty">No memos posted yet — visit Collection Manager &rsaquo; Memos to add the first.</p>
    @else
        <ul class="np-memos__list">
            @foreach ($items as $memo)
                <li class="np-memos__item">
                    @if (! empty($memo['title']))
                        <h4 class="np-memos__title">{{ $memo['title'] }}</h4>
                    @endif
                    @if (! empty($memo['posted_at']))
                        <p class="np-memos__date">{{ \App\Support\DateFormat::format(\Illuminate\Support\Carbon::parse($memo['posted_at']), \App\Support\DateFormat::LONG_DATE) }}</p>
                    @endif
                    @if (! empty($memo['body']))
                        <div class="np-memos__body">{!! $memo['body'] !!}</div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
