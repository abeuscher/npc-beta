<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('sitemap_xml', 3600, function () {
            return $this->buildSitemap();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots()
    {
        $baseUrl = rtrim(SiteSetting::get('base_url', config('app.url')), '/');

        $content = "User-agent: *\nAllow: /\nSitemap: {$baseUrl}/sitemap.xml\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    private function buildSitemap(): string
    {
        $baseUrl = rtrim(SiteSetting::get('base_url', config('app.url')), '/');

        $pages = Page::query()
            ->published()
            ->where('noindex', false)
            ->whereNotIn('type', ['system', 'member'])
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $loc     = $page->slug === 'home' ? $baseUrl : $baseUrl . '/' . $page->slug;
            $lastmod = $page->updated_at->toW3cString();

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
