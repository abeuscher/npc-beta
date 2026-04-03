<x-filament-panels::page>
    <x-filament::section>
        @if (count($this->articles) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">No articles in this category.</p>
        @else
            <div class="space-y-4">
                @foreach ($this->articles as $article)
                    <div>
                        <a href="{{ \App\Filament\Pages\HelpArticlePage::articleUrl($article['slug']) }}"
                           class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                            {{ $article['title'] }}
                        </a>
                        @if ($article['description'])
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $article['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
