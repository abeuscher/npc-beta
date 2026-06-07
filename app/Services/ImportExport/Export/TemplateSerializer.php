<?php

namespace App\Services\ImportExport\Export;

use App\Models\Template;
use Illuminate\Support\Collection;

/**
 * Serializes Template rows into the bundle's portable template shape. Content
 * templates carry a widget tree (delegated to {@see WidgetTreeSerializer});
 * page templates carry chrome page-slug references + custom SCSS + the
 * session-301 structural deviation columns.
 */
class TemplateSerializer
{
    public function __construct(private WidgetTreeSerializer $widgetTree) {}

    /**
     * @param  Collection<int, Template>  $templates
     * @return array<int, array<string, mixed>>
     */
    public function serializeMany(Collection $templates): array
    {
        return $templates->map(fn (Template $t) => $this->serializeTemplate($t))->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTemplate(Template $template): array
    {
        $data = [
            'name'        => $template->name,
            'type'        => $template->type,
            'description' => $template->description,
            'is_default'  => $template->is_default,
        ];

        if ($template->type === 'content') {
            $data['widgets'] = $this->widgetTree->forOwner($template);

            return $data;
        }

        // Page template — chrome page slug references + custom SCSS.
        // Colour is no longer per-template (session-297 relocation to the
        // site-wide Theme palette); the colour columns were dropped.
        $data['custom_scss']      = $template->custom_scss;
        $data['header_page_slug'] = $template->headerPage?->slug;
        $data['footer_page_slug'] = $template->footerPage?->slug;

        // Session-301 per-template structural deviation. Additive — carried
        // so a template's selected scheme + chrome suppression round-trips
        // (dropping them would silently lose a page's deviation config). The
        // standalone portable-theme feature is a separate post-301 session;
        // this is only the correctness carry-through of the new columns.
        $data['scheme']    = $template->scheme;
        $data['no_header'] = (bool) $template->no_header;
        $data['no_footer'] = (bool) $template->no_footer;

        return $data;
    }
}
