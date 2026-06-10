<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\HelpArticleService;
use Illuminate\Support\Facades\Cache;

class LlmsTxtController extends Controller
{
    public function index()
    {
        $body = Cache::remember('llms_txt', 3600, function () {
            return $this->build();
        });

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function full()
    {
        abort_unless(isPublicWebsite(), 404);

        $body = Cache::remember('llms_full_txt', 3600, function () {
            return $this->buildFull();
        });

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    private function build(): string
    {
        $siteName    = SiteSetting::get('site_name', config('app.name'));
        $description = SiteSetting::get('site_description', '');
        $contact     = SiteSetting::get('contact_email', '');
        $baseUrl     = rtrim(SiteSetting::get('base_url', config('app.url')), '/');

        $pages = Page::query()
            ->published()
            ->where('noindex', false)
            ->whereNotIn('type', ['system', 'member', 'post'])
            ->orderBy('slug')
            ->get(['slug', 'title']);

        $lines = [];
        $lines[] = "# {$siteName}";
        $lines[] = '';

        if (filled($description)) {
            $lines[] = "> {$description}";
            $lines[] = '';
        }

        $lines[] = '## Pages';
        $lines[] = '';

        foreach ($pages as $page) {
            $url   = $page->slug === 'home' ? $baseUrl : $baseUrl . '/' . $page->slug;
            $title = $page->title ?: $page->slug;
            $lines[] = "- [{$title}]({$url})";
        }

        if (isPublicWebsite()) {
            $articles = HelpArticle::query()
                ->orderBy('title')
                ->get(['slug', 'title', 'description']);

            if ($articles->isNotEmpty()) {
                $lines[] = '';
                $lines[] = '## Documentation';
                $lines[] = '';

                foreach ($articles as $article) {
                    $line = "- [{$article->title}]({$baseUrl}/docs/{$article->slug}.md)";
                    if (filled($article->description)) {
                        $line .= " — {$article->description}";
                    }
                    $lines[] = $line;
                }
            }
        }

        if (filled($contact)) {
            $lines[] = '';
            $lines[] = '## Contact';
            $lines[] = '';
            $lines[] = "Email: {$contact}";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildFull(): string
    {
        $siteName    = SiteSetting::get('site_name', config('app.name'));
        $description = SiteSetting::get('site_description', '');
        $baseUrl     = rtrim(SiteSetting::get('base_url', config('app.url')), '/');

        $articles = HelpArticle::query()
            ->orderBy('title')
            ->get(['slug', 'title', 'content']);

        $service = app(HelpArticleService::class);

        $lines = [];
        $lines[] = "# {$siteName}";
        $lines[] = '';

        if (filled($description)) {
            $lines[] = "> {$description}";
            $lines[] = '';
        }

        foreach ($articles as $article) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = "# {$article->title}";
            $lines[] = '';
            $lines[] = "Canonical: {$baseUrl}/docs/{$article->slug}";
            $lines[] = '';
            $lines[] = $service->bodyWithoutLeadingH1($article->content);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
