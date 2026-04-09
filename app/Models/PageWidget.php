<?php

namespace App\Models;

use App\Services\Media\ImageSizeProfile;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PageWidget extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    protected $fillable = [
        'page_id',
        'layout_id',
        'column_index',
        'widget_type_id',
        'label',
        'config',
        'query_config',
        'style_config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config'       => 'array',
        'query_config' => 'array',
        'style_config' => 'array',
        'is_active'    => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function widgetType(): BelongsTo
    {
        return $this->belongsTo(WidgetType::class);
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(PageLayout::class, 'layout_id');
    }

    // ── Static helpers — serialization & copying ─────────────────────────

    /**
     * Serialize a page's root widget stack into a content-template-ready array.
     */
    public static function serializeStack(string $pageId): array
    {
        $roots = static::where('page_id', $pageId)
            ->whereNull('layout_id')
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::where('page_id', $pageId)
            ->with(['widgets.widgetType'])
            ->orderBy('sort_order')
            ->get();

        $items = [];

        foreach ($roots as $pw) {
            $items[] = ['sort' => $pw->sort_order, 'data' => static::serializeOne($pw)];
        }

        foreach ($layouts as $layout) {
            $items[] = ['sort' => $layout->sort_order, 'data' => static::serializeLayout($layout)];
        }

        usort($items, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_column($items, 'data');
    }

    private static function serializeOne(self $pw): array
    {
        $entry = [
            'type'         => 'widget',
            'handle'       => $pw->widgetType?->handle,
            'label'        => $pw->label,
            'config'       => $pw->config ?? [],
            'query_config' => $pw->query_config ?? [],
            'style_config' => $pw->style_config ?? [],
            'sort_order'   => $pw->sort_order,
            'is_active'    => $pw->is_active,
        ];

        if ($pw->column_index !== null) {
            $entry['column_index'] = $pw->column_index;
        }

        return $entry;
    }

    private static function serializeLayout(PageLayout $layout): array
    {
        $slots = [];
        foreach ($layout->widgets as $widget) {
            $idx = $widget->column_index ?? 0;
            $slots[$idx][] = static::serializeOne($widget);
        }

        return [
            'type'          => 'layout',
            'label'         => $layout->label,
            'display'       => $layout->display,
            'columns'       => $layout->columns,
            'layout_config' => $layout->layout_config ?? [],
            'sort_order'    => $layout->sort_order,
            'slots'         => $slots,
        ];
    }

    /**
     * Recursively copy all widgets from one page to another.
     */
    public static function copyBetweenPages(
        string $sourcePageId,
        string $targetPageId,
    ): void {
        // Copy root widgets
        $rootWidgets = static::where('page_id', $sourcePageId)
            ->whereNull('layout_id')
            ->orderBy('sort_order')
            ->get();

        foreach ($rootWidgets as $widget) {
            static::create([
                'page_id'        => $targetPageId,
                'layout_id'      => null,
                'column_index'   => null,
                'widget_type_id' => $widget->widget_type_id,
                'label'          => $widget->label,
                'config'         => $widget->config,
                'query_config'   => $widget->query_config,
                'style_config'   => $widget->style_config,
                'sort_order'     => $widget->sort_order,
                'is_active'      => $widget->is_active,
            ]);
        }

        // Copy layouts with their child widgets
        $layouts = PageLayout::where('page_id', $sourcePageId)
            ->with('widgets')
            ->orderBy('sort_order')
            ->get();

        foreach ($layouts as $layout) {
            $newLayout = PageLayout::create([
                'page_id'       => $targetPageId,
                'label'         => $layout->label,
                'display'       => $layout->display,
                'columns'       => $layout->columns,
                'layout_config' => $layout->layout_config,
                'sort_order'    => $layout->sort_order,
            ]);

            foreach ($layout->widgets as $widget) {
                static::create([
                    'page_id'        => $targetPageId,
                    'layout_id'      => $newLayout->id,
                    'column_index'   => $widget->column_index,
                    'widget_type_id' => $widget->widget_type_id,
                    'label'          => $widget->label,
                    'config'         => $widget->config,
                    'query_config'   => $widget->query_config,
                    'style_config'   => $widget->style_config,
                    'sort_order'     => $widget->sort_order,
                    'is_active'      => $widget->is_active,
                ]);
            }
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && str_contains($media->mime_type, 'svg')) {
            return;
        }

        $profile = ImageSizeProfile::photo();

        $this->addMediaConversion('webp')
            ->width($profile->maxWidth)
            ->height($profile->maxHeight)
            ->format('webp');

        foreach ($profile->breakpoints as $width) {
            $this->addMediaConversion("responsive-{$width}")
                ->width($width)
                ->format('webp');
        }
    }
}
