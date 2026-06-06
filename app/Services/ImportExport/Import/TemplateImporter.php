<?php

namespace App\Services\ImportExport\Import;

use App\Models\Page;
use App\Models\Template;
use App\Services\ImportExport\ImportLog;
use App\Services\TemplateAppearanceResolver;

/**
 * Imports a serialized template (upsert by name+type). Content templates have
 * their widget stack replaced via {@see WidgetTreeHydrator}; page templates
 * carry custom SCSS + session-301 deviation columns and have their chrome page
 * links reconnected in a second pass after pages land ({@see relinkChrome}).
 */
class TemplateImporter
{
    public function __construct(
        private WidgetTreeHydrator $widgetTree,
        private BundleAuthorResolver $authors,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function import(array $data, ImportLog $log, BundleMediaArchive $archive): void
    {
        $name = $data['name'] ?? null;
        $type = $data['type'] ?? null;

        if (! $name || ! in_array($type, ['page', 'content'], true)) {
            $log->warning('Template entry missing name or type, skipped.');

            return;
        }

        $template = Template::where('name', $name)->where('type', $type)->first();

        if (! $template) {
            $template = Template::create([
                'name'        => $name,
                'type'        => $type,
                'description' => $data['description'] ?? null,
                'is_default'  => false,
                'created_by'  => $this->authors->resolve(),
            ]);
        } else {
            $template->update([
                'description' => $data['description'] ?? $template->description,
            ]);
        }

        if ($type === 'content') {
            // Replace the template's widget stack with whatever the bundle carries.
            $template->widgets()->delete();
            $template->layouts()->delete();

            // Widgets array is the new format; `definition` is the pre-polymorphism
            // format, still accepted so older bundles round-trip cleanly.
            $widgets = $data['widgets'] ?? $data['definition'] ?? [];

            $this->widgetTree->hydrate($template, $widgets, $log, $archive);

            return;
        }

        // Colour keys from pre-297 export bundles are intentionally ignored —
        // colour is now the site-wide Theme palette, not template-owned.
        //
        // Session-301 columns carried additively + concretely: an older
        // bundle without these keys keeps the template's concrete current
        // value (never null); an unknown scheme string falls back to Default
        // (concrete-values rule — the render-time resolver guards too).
        $incomingScheme = $data['scheme'] ?? null;
        $scheme = is_string($incomingScheme) && in_array($incomingScheme, TemplateAppearanceResolver::schemes(), true)
            ? $incomingScheme
            : ($template->scheme ?: TemplateAppearanceResolver::DEFAULT_SCHEME);

        $template->update([
            'custom_scss' => $data['custom_scss'] ?? null,
            'scheme'      => $scheme,
            'no_header'   => (bool) ($data['no_header'] ?? $template->no_header),
            'no_footer'   => (bool) ($data['no_footer'] ?? $template->no_footer),
        ]);
    }

    /**
     * Re-link a page template to its chrome (header/footer) pages by slug, once
     * the pages exist on the target. Runs as a post-pass after the pages import.
     *
     * @param  array<string, mixed>  $data
     */
    public function relinkChrome(array $data, ImportLog $log): void
    {
        $name = $data['name'] ?? null;
        if (! $name) {
            return;
        }

        $template = Template::where('name', $name)->where('type', 'page')->first();
        if (! $template) {
            return;
        }

        $update = [];

        if (! empty($data['header_page_slug'])) {
            $headerPage = Page::where('slug', $data['header_page_slug'])->first();
            if ($headerPage) {
                $update['header_page_id'] = $headerPage->id;
            } else {
                $log->warning("Template \"{$name}\": header page slug '{$data['header_page_slug']}' not found.");
            }
        }

        if (! empty($data['footer_page_slug'])) {
            $footerPage = Page::where('slug', $data['footer_page_slug'])->first();
            if ($footerPage) {
                $update['footer_page_id'] = $footerPage->id;
            } else {
                $log->warning("Template \"{$name}\": footer page slug '{$data['footer_page_slug']}' not found.");
            }
        }

        if (! empty($update)) {
            $template->update($update);
        }
    }
}
