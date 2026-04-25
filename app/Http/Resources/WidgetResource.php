<?php

namespace App\Http\Resources;

use App\Models\PageWidget;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetResource extends JsonResource
{
    public $preserveKeys = true;

    public array $requiredHandles = [];

    public function withRequiredHandles(array $handles): static
    {
        $this->requiredHandles = $handles;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var PageWidget $pw */
        $pw = $this->resource;

        $config = $pw->config ?? [];
        $definition = app(WidgetRegistry::class)->find($pw->widgetType?->handle ?? '');
        $querySettings = $definition?->querySettings($config);

        return [
            'id'                          => $pw->id,
            'widget_type_id'              => $pw->widget_type_id,
            'widget_type_handle'          => $pw->widgetType?->handle ?? '',
            'widget_type_label'           => $pw->widgetType?->label ?? 'Unknown',
            'widget_type_config_schema'   => $pw->widgetType?->config_schema ?? [],
            'widget_type_assets'          => $pw->widgetType?->assets ?? [],
            'widget_type_default_open'    => $pw->widgetType?->default_open ?? false,
            'widget_type_required_config' => $pw->widgetType?->required_config,
            'layout_id'                   => $pw->layout_id,
            'column_index'                => $pw->column_index,
            'label'                       => $pw->label ?? '',
            'config'                      => $config,
            'resolved_defaults'           => app(WidgetConfigResolver::class)->resolvedDefaults($pw),
            'query_config'                => $pw->query_config ?? [],
            'query_settings'              => $querySettings === null ? null : [
                'has_panel'         => $querySettings->hasPanel,
                'order_by_options'  => $querySettings->orderByOptions,
                'supports_tags'     => $querySettings->supportsTags,
            ],
            'appearance_config'           => $pw->appearance_config ?? [],
            'sort_order'                  => $pw->sort_order ?? 0,
            'is_active'                   => $pw->is_active,
            'is_required'                 => in_array($pw->widgetType?->handle ?? '', $this->requiredHandles, true),
            'image_urls'                  => $pw->configImageUrls(),
            'appearance_image_url'        => $pw->appearanceImageUrl(),
        ];
    }
}
