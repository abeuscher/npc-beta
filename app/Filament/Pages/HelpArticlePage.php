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

        $crumbs[] = $this->article->title;

        return $crumbs;
    }

    protected function loadRelatedArticles(): array
    {
        $tags = $this->article->tags ?? [];

        if (empty($tags)) {
            return [];
        }

        $tagsJson = json_encode($tags);

        return HelpArticle::query()
            ->where('id', '!=', $this->article->id)
            ->whereRaw("tags::jsonb ??| array(SELECT jsonb_array_elements_text(?::jsonb))", [$tagsJson])
            ->selectRaw("help_articles.*, (
                SELECT COUNT(*) FROM jsonb_array_elements_text(help_articles.tags::jsonb) AS t
                WHERE t IN (SELECT jsonb_array_elements_text(?::jsonb))
            ) AS overlap_count", [$tagsJson])
            ->orderBy('title')
            ->limit(6)
            ->get()
            ->toArray();
    }
}
