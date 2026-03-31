<?php

namespace App\Models;

use App\Services\ImageSizeProfile;
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
        'parent_widget_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PageWidget::class, 'parent_widget_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PageWidget::class, 'parent_widget_id')
            ->orderBy('column_index')
            ->orderBy('sort_order');
    }

    // ── Static helpers — serialization & copying ─────────────────────────

    /**
     * Serialize a page's root widget stack into a content-template-ready array.
     */
    public static function serializeStack(string $pageId): array
    {
        $roots = static::where('page_id', $pageId)
            ->whereNull('parent_widget_id')
            ->with(['widgetType', 'children.widgetType'])
            ->orderBy('sort_order')
            ->get();

        return $roots->map(fn ($pw) => static::serializeOne($pw))->toArray();
    }

    private static function serializeOne(self $pw): array
    {
        $entry = [
            'handle'       => $pw->widgetType?->handle,
            'label'        => $pw->label,
            'config'       => $pw->config ?? [],
            'query_config' => $pw->query_config ?? [],
            'style_config' => $pw->style_config ?? [],
            'sort_order'   => $pw->sort_order,
        ];

        if ($pw->column_index !== null) {
            $entry['column_index'] = $pw->column_index;
        }

        if ($pw->children->isNotEmpty()) {
            $entry['children'] = $pw->children->map(fn ($child) => static::serializeOne($child))->toArray();
        }

        return $entry;
    }

    /**
     * Recursively copy all widgets from one page to another.
     */
    public static function copyBetweenPages(
        string $sourcePageId,
        string $targetPageId,
        ?string $sourceParentId = null,
        ?string $targetParentId = null,
    ): void {
        $widgets = static::where('page_id', $sourcePageId)
            ->where('parent_widget_id', $sourceParentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($widgets as $widget) {
            $new = static::create([
                'page_id'          => $targetPageId,
                'parent_widget_id' => $targetParentId,
                'column_index'     => $widget->column_index,
                'widget_type_id'   => $widget->widget_type_id,
                'label'            => $widget->label,
                'config'           => $widget->config,
                'query_config'     => $widget->query_config,
                'style_config'     => $widget->style_config,
                'sort_order'       => $widget->sort_order,
                'is_active'        => $widget->is_active,
            ]);

            static::copyBetweenPages($sourcePageId, $targetPageId, $widget->id, $new->id);
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
