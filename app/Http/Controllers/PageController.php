<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function home()
    {
        $page = Page::where('slug', 'home')
            ->where('is_published', true)
            ->firstOrFail();

        return view('pages.show', compact('page'));
    }

    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('pages.show', compact('page'));
    }
}
