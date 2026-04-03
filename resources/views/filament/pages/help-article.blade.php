<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2">
            <x-filament::section>
                <div class="help-page-meta">
                    @if ($this->article->category)
                        <span class="help-search-badge help-search-badge--{{ $this->article->category }}">{{ $this->article->category }}</span>
                    @endif
                    @if ($this->article->last_updated)
                        <span>Updated {{ $this->article->last_updated->format('F j, Y') }}</span>
                    @endif
                    @if ($this->article->app_version)
                        <span>&middot; v{{ $this->article->app_version }}</span>
                    @endif
                </div>

                @if ($this->article->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 italic">{{ $this->article->description }}</p>
                @endif

                <div class="help-page-content">
                    {!! \Illuminate\Support\Str::markdown($this->article->content) !!}
                </div>
            </x-filament::section>
        </div>

        {{-- Related articles sidebar --}}
        <div>
            @if (count($this->relatedArticles) > 0)
                <x-filament::section heading="Related Articles">
                    @foreach ($this->relatedArticles as $related)
                        <a href="{{ \App\Filament\Pages\HelpArticlePage::articleUrl($related['slug']) }}" class="help-related-article">
                            <div class="help-search-result-header">
                                <span class="help-related-article-title">{{ $related['title'] }}</span>
                                @if ($related['category'])
                                    <span class="help-search-badge help-search-badge--{{ $related['category'] }}">{{ $related['category'] }}</span>
                                @endif
                            </div>
                            @if ($related['description'])
                                <p class="help-related-article-desc">{{ \Illuminate\Support\Str::limit($related['description'], 80) }}</p>
                            @endif
                        </a>
                    @endforeach
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
