<?php

namespace App\Http\Controllers;

use App\Filament\Pages\HelpCategoryPage;
use App\Models\HelpArticle;
use App\Models\SiteSetting;
use App\Services\HelpArticleService;

class DocsController extends Controller
{
    // Mirrors the admin help surface's category vocabulary (HelpCategoryPage);
    // index ordering follows this list, uncategorised articles sort last.
    private const CATEGORY_ORDER = ['crm', 'cms', 'finance', 'tools', 'settings', 'general'];

    public function index()
    {
        abort_unless(isPublicWebsite(), 404);

        $articles = HelpArticle::query()
            ->orderBy('title')
            ->get(['slug', 'title', 'description', 'category']);

        $grouped = $articles
            ->groupBy(fn (HelpArticle $a) => $a->category ?? 'general')
            ->sortBy(fn ($group, $category) => array_search($category, self::CATEGORY_ORDER, true) !== false
                ? array_search($category, self::CATEGORY_ORDER, true)
                : count(self::CATEGORY_ORDER));

        return view('docs.index', [
            'grouped'  => $grouped,
            'siteName' => SiteSetting::get('site_name', config('app.name')),
        ]);
    }

    public function show(string $slug)
    {
        abort_unless(isPublicWebsite(), 404);

        $article = HelpArticle::where('slug', $slug)->firstOrFail();

        // Breadcrumb ancestor chain from parent_slug, root-first (the same
        // guarded walk HelpArticlePage uses).
        $ancestors = [];
        $cursor = $article->parent();
        $guard = 0;
        while ($cursor && $guard++ < 10) {
            $ancestors[] = $cursor;
            $cursor = $cursor->parent();
        }
        $ancestors = array_reverse($ancestors);

        return view('docs.show', [
            'article'         => $article,
            'ancestors'       => $ancestors,
            'relatedArticles' => app(HelpArticleService::class)->relatedTo($article),
            'siteName'        => SiteSetting::get('site_name', config('app.name')),
        ]);
    }

    public function raw(string $slug)
    {
        abort_unless(isPublicWebsite(), 404);

        $article = HelpArticle::where('slug', $slug)->firstOrFail();

        $baseUrl = rtrim(SiteSetting::get('base_url', config('app.url')), '/');

        $lines = [];
        $lines[] = "# {$article->title}";
        $lines[] = '';
        $lines[] = $article->description;
        $lines[] = '';
        if ($article->last_updated) {
            $lines[] = 'Last updated: ' . $article->last_updated->toDateString();
        }
        $lines[] = "Canonical: {$baseUrl}/docs/{$article->slug}";
        $lines[] = '';
        $lines[] = app(HelpArticleService::class)->bodyWithoutLeadingH1($article->content);
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
        ]);
    }

    public static function categoryLabel(string $category): string
    {
        return HelpCategoryPage::categoryLabel($category);
    }
}
