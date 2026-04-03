<div
    class="help-search-container"
    x-data="{
        open: false,
        focusIndex: -1,
        close() { this.open = false; this.focusIndex = -1; },
    }"
    x-on:click.away="close()"
    x-on:keydown.escape.window="close()"
>
    <div class="help-search-input-wrap">
        <svg xmlns="http://www.w3.org/2000/svg" class="help-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
            type="text"
            wire:model.live.debounce.300ms="query"
            placeholder="Search help…"
            class="help-search-input"
            x-on:focus="if ($wire.query.length) open = true"
            x-on:input="open = true; focusIndex = -1"
            x-on:keydown.arrow-down.prevent="if (open) focusIndex = Math.min(focusIndex + 1, {{ count($results) - 1 }})"
            x-on:keydown.arrow-up.prevent="if (open) focusIndex = Math.max(focusIndex - 1, 0)"
            x-on:keydown.enter.prevent="if (open && focusIndex >= 0) { $refs['result-' + focusIndex]?.click(); }"
            autocomplete="off"
        />
    </div>

    @if (count($results) > 0 && $query !== '')
        <div class="help-search-dropdown" x-show="open" x-cloak>
            @foreach ($results as $index => $article)
                <a
                    href="{{ \App\Filament\Pages\HelpArticlePage::articleUrl($article['slug']) }}"
                    class="help-search-result"
                    :class="{ 'help-search-result--focused': focusIndex === {{ $index }} }"
                    x-ref="result-{{ $index }}"
                    x-on:mouseenter="focusIndex = {{ $index }}"
                >
                    <div class="help-search-result-header">
                        <span class="help-search-result-title">{{ $article['title'] }}</span>
                        @if ($article['category'])
                            <span class="help-search-badge help-search-badge--{{ $article['category'] }}">{{ $article['category'] }}</span>
                        @endif
                    </div>
                    @if ($article['description'])
                        <p class="help-search-result-desc">{{ \Illuminate\Support\Str::limit($article['description'], 100) }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
