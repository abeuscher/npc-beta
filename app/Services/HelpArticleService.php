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
}
