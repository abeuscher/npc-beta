<?php

namespace App\Services;

use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetRegistry;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

class WidgetRenderer
{
    /**
     * Render a single widget to HTML + inline styles + inline scripts.
     *
     * @param  array<string, array>  $fallbackCollectionData  Optional pre-resolved collection data (e.g. demo data for admin preview). Keyed by collection handle.
     * @return array{html: string|null, styles: string, scripts: string}
     */
    public static function render(
        PageWidget $pw,
        array $columnChildren = [],
        array $fallbackCollectionData = [],
        string $slotHandle = 'page_builder_canvas',
        ?Model $record = null,
    ): array
    {
        $widgetType = $pw->widgetType;

        if (! $widgetType) {
            return ['html' => null, 'styles' => '', 'scripts' => ''];
        }

        if ($slotHandle === 'record_detail_sidebar' && $record === null) {
            return ['html' => null, 'styles' => '', 'scripts' => ''];
        }

        $isCanvas = $slotHandle === 'page_builder_canvas';

        $config  = app(WidgetConfigResolver::class)->resolve($pw);
        $styles  = '';
        $scripts = '';

        // Resolve image config fields to media objects
        $configMedia = [];
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video']) && ! empty($config[$field['key']])) {
                $configMedia[$field['key']] = $pw->getFirstMedia("config_{$field['key']}");
            }
        }

        // Process inline images in richtext fields
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'richtext' && ! empty($config[$field['key']])) {
                $config[$field['key']] = \App\Services\Media\InlineImageRenderer::process($config[$field['key']]);
            }
        }

        $pageContext = app(PageContext::class);
        $tokens = app(PageContextTokens::class);

        if ($isCanvas) {
            $ctxPage = $pageContext->currentPage;
            $ownerPage = ($pw->owner instanceof \App\Models\Page) ? $pw->owner : null;
            $tokenPage = ($ctxPage && $ctxPage->exists) ? $ctxPage : $ownerPage;
            // PostController / PageController View::share `pageContext` but do
            // not app()->instance(PageContext::class, ...), so the container
            // resolves a fresh PageContext with currentPage=null in production.
            // Templates that read $pageContext->currentPage (BlogPager) need
            // the resolved page; rebuild from $tokenPage so production parity
            // matches the test pattern that does bind the container.
            $pageContext = new PageContext($tokenPage);
        } else {
            $tokenPage = null;
        }

        $widgetData = null;
        $contract = null;
        $definition = app(WidgetRegistry::class)->find($widgetType->handle);
        if ($definition !== null) {
            $contract = $definition->dataContract($config);
            if ($contract !== null) {
                $contract = self::mergeUserQueryConfig($contract, $pw->query_config ?? []);

                $skip = $contract->source === DataContract::SOURCE_PAGE_CONTEXT
                    && ! self::configHasTokens($config);

                if (! $skip) {
                    if ($isCanvas) {
                        $slot = app(SlotRegistry::class)->find('page_builder_canvas')->ambientContext($pageContext, $tokenPage);
                    } elseif ($slotHandle === 'record_detail_sidebar') {
                        $slot = app(SlotRegistry::class)->find('record_detail_sidebar')->ambientContext($record);
                    } else {
                        $slot = app(SlotRegistry::class)->find($slotHandle)->ambientContext();
                    }
                    $widgetData = app(ContractResolver::class)->resolve([$contract], $slot, $fallbackCollectionData)[0];
                }
            }
        }

        if ($isCanvas) {
            foreach ($widgetType->config_schema ?? [] as $field) {
                $type = $field['type'] ?? '';
                $key = $field['key'] ?? '';
                if (! $key || ! isset($config[$key]) || ! is_string($config[$key])) {
                    continue;
                }
                if ($type !== 'richtext' && $type !== 'text') {
                    continue;
                }
                $escape = $type === 'richtext';

                if ($contract !== null && $contract->source === DataContract::SOURCE_PAGE_CONTEXT && is_array($widgetData)) {
                    $text = $config[$key];
                    foreach ($widgetData as $token => $value) {
                        $replacement = $escape ? e((string) $value) : (string) $value;
                        $text = str_replace('{{' . $token . '}}', $replacement, $text);
                    }
                    $config[$key] = $text;
                } else {
                    $config[$key] = $tokens->substitute($config[$key], $tokenPage, $escape);
                }
            }
        }

        // Build template variables and render
        $html = '';

        if ($widgetType->render_mode === 'server') {
            $templateVars = [
                'config'             => $config,
                'configMedia'        => $configMedia,
                'pageContext'        => $pageContext,
                'pageContextTokens'  => $tokens,
                'widgetData'         => $widgetData,
            ];

            if (! empty($columnChildren)) {
                $templateVars['children'] = $columnChildren;
            }

            $html = $widgetType->template
                ? Blade::render($widgetType->template, $templateVars)
                : '';

            if ($widgetType->css) {
                $styles .= "\n" . $widgetType->css;
            }
            if ($widgetType->js) {
                $scripts .= "\n" . $widgetType->js;
            }
        } else {
            $html = $widgetType->code
                ? '<script>' . $widgetType->code . '</script>'
                : '';

            if ($widgetType->css) {
                $styles .= "\n" . $widgetType->css;
            }
        }

        return ['html' => $html, 'styles' => $styles, 'scripts' => $scripts];
    }

    private static function configHasTokens(array $config): bool
    {
        foreach ($config as $value) {
            if (is_string($value) && str_contains($value, '{{')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Merge user-supplied query_config into a copy of the contract's filters,
     * restricted to the closed set of honored knobs. Non-honored keys
     * (e.g. EventsListing's date_range) remain immutable contract defaults.
     */
    private static function mergeUserQueryConfig(DataContract $contract, array $userConfig): DataContract
    {
        if ($contract->querySettings === null || ! $contract->querySettings->hasPanel) {
            return $contract;
        }

        $honoredKeys = ['limit', 'order_by', 'direction', 'include_tags', 'exclude_tags'];
        $userFilters = array_intersect_key($userConfig, array_flip($honoredKeys));
        if ($userFilters === []) {
            return $contract;
        }

        return new DataContract(
            version: $contract->version,
            source: $contract->source,
            fields: $contract->fields,
            filters: array_merge($contract->filters, $userFilters),
            model: $contract->model,
            resourceHandle: $contract->resourceHandle,
            contentType: $contract->contentType,
            querySettings: $contract->querySettings,
            formatHints: $contract->formatHints,
        );
    }

    /**
     * Collect CSS/JS/SCSS asset paths from a widget type into an accumulator.
     */
    public static function collectAssets(?WidgetType $widgetType, array &$assets): void
    {
        if (! $widgetType) {
            return;
        }

        $widgetAssets = $widgetType->assets ?? [];

        foreach (['css', 'js', 'scss'] as $type) {
            foreach ($widgetAssets[$type] ?? [] as $path) {
                if (! in_array($path, $assets[$type], true)) {
                    $assets[$type][] = $path;
                }
            }
        }
    }
}
