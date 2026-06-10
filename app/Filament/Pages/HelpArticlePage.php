<?php

namespace App\Filament\Pages;

use App\Models\HelpArticle;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class HelpArticlePage extends Page
{
    protected static string $view = 'filament.pages.help-article';

    protected static ?string $slug = 'help/{slug}';

    protected static bool $shouldRegisterNavigation = false;

    public ?HelpArticle $article = null;

    public array $relatedArticles = [];

    public static function articleUrl(string $slug): string
    {
        return Filament::getCurrentPanel()->getUrl() . '/help/' . $slug;
    }

    public function mount(string $slug): void
    {
        $this->article = HelpArticle::where('slug', $slug)->first();

        if (! $this->article) {
            abort(404);
        }

        $this->relatedArticles = $this->loadRelatedArticles();
    }

    public function getTitle(): string
    {
        return $this->article?->title ?? 'Help';
    }

    public function getBreadcrumbs(): array
    {
        $crumbs = [
            HelpIndexPage::helpUrl() => 'Help',
        ];

        if ($this->article->category) {
            $crumbs[HelpCategoryPage::categoryUrl($this->article->category)] = HelpCategoryPage::categoryLabel($this->article->category);
        }

        $ancestors = [];
        $cursor = $this->article->parent();
        $guard = 0;
        while ($cursor && $guard++ < 10) {
            $ancestors[] = $cursor;
            $cursor = $cursor->parent();
        }

        foreach (array_reverse($ancestors) as $ancestor) {
            $crumbs[self::articleUrl($ancestor->slug)] = $ancestor->title;
        }

        $crumbs[] = $this->article->title;

        return $crumbs;
    }

    protected function loadRelatedArticles(): array
    {
        return app(\App\Services\HelpArticleService::class)->relatedTo($this->article);
    }
}
