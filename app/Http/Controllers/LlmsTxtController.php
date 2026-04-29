<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SiteSetting;
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

        if (filled($contact)) {
            $lines[] = '';
            $lines[] = '## Contact';
            $lines[] = '';
            $lines[] = "Email: {$contact}";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
