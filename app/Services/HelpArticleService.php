<?php

namespace App\Services;

use App\Models\HelpArticle;
use App\Models\HelpArticleRoute;

class HelpArticleService
{
    /**
     * Return the help article for the given Filament route name, or null if none exists.
     */
    public function forRoute(string $routeName): ?HelpArticle
    {
        $mapping = HelpArticleRoute::where('route_name', $routeName)
            ->with('article')
            ->first();

        return $mapping?->article;
    }

    /**
     * Every corpus file opens its body with its own `# Title` H1. The public
     * docs surfaces emit a title H1 of their own (from frontmatter), so strip
     * a leading H1 line from the body to keep exactly one H1 per document.
     */
    public function bodyWithoutLeadingH1(string $content): string
    {
        return ltrim(preg_replace('/^#\s[^\n]*\n/', '', ltrim($content), 1));
    }

    /**
     * Return up to $limit articles sharing at least one tag with the given
     * article, with an overlap_count column for the number of shared tags.
     */
    public function relatedTo(HelpArticle $article, int $limit = 6): array
    {
        $tags = $article->tags ?? [];

        if (empty($tags)) {
            return [];
        }

        $tagsJson = json_encode($tags);

        return HelpArticle::query()
            ->where('id', '!=', $article->id)
            ->whereRaw("tags::jsonb ??| array(SELECT jsonb_array_elements_text(?::jsonb))", [$tagsJson])
            ->selectRaw("help_articles.*, (
                SELECT COUNT(*) FROM jsonb_array_elements_text(help_articles.tags::jsonb) AS t
                WHERE t IN (SELECT jsonb_array_elements_text(?::jsonb))
            ) AS overlap_count", [$tagsJson])
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
