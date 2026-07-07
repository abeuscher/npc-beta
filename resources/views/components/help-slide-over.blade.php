@props(['article' => null])

@if ($article)
    <div
        x-data="{ open: false }"
        x-on:keydown.escape.window="open = false"
        x-effect="document.body.style.overflow = open ? 'hidden' : ''"
    >
        {{-- Trigger button --}}
        <button
            type="button"
            x-on:click="open = true"
            title="Help"
            class="help-trigger fi-icon-btn"
        >
            <x-heroicon-o-question-mark-circle class="help-trigger-icon" />
            <span class="sr-only">Help</span>
        </button>

        {{-- Backdrop --}}
        <div
            class="help-backdrop"
            :class="{ 'help-backdrop--open': open }"
            x-on:click="open = false"
        ></div>

        {{-- Slide-over panel --}}
        <div
            class="help-panel"
            :class="{ 'help-panel--open': open }"
        >
            {{-- Header --}}
            <div class="help-panel-header">
                <h2 class="help-panel-title">{{ $article->title }}</h2>
                <button type="button" x-on:click="open = false" class="help-panel-close">
                    <x-heroicon-o-x-mark class="help-panel-close-icon" />
                    <span class="sr-only">Close help</span>
                </button>
            </div>

            {{-- Content --}}
            <div class="help-panel-body">
                {!! \Illuminate\Support\Str::markdown($article->content) !!}
            </div>

            {{-- Footer --}}
            <div class="help-panel-footer">
                Last updated {{ $article->last_updated?->format('F j, Y') }}
                @if ($article->app_version)
                    &middot; v{{ $article->app_version }}
                @endif
                <a href="{{ \App\Filament\Pages\HelpArticlePage::articleUrl($article->slug) }}"
                   class="help-panel-full-link"
                   style="display: block; margin-top: 0.5rem; font-weight: 500; color: var(--c-500, #6366f1); text-decoration: none;"
                >
                    Open in full page &rarr;
                </a>
            </div>
        </div>
    </div>
@endif
