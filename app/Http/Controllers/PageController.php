<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Collection;

class PageController extends Controller
{
    public function home()
    {
        $page = Page::where('slug', 'home')
            ->where('is_published', true)
            ->firstOrFail();

        $widgets = $this->resolveWidgets($page);

        return view('pages.show', compact('page', 'widgets'));
    }

    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $widgets = $this->resolveWidgets($page);

        return view('pages.show', compact('page', 'widgets'));
    }

    private function resolveWidgets(Page $page): Collection
    {
        return $page->pageWidgets()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($pw) => [
                'instance' => $pw->typeInstance(),
                'config'   => $pw->config ?? [],
                'data'     => $pw->typeInstance()?->resolveData($pw->config ?? []) ?? [],
            ]);
    }
}
