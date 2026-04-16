<?php

namespace App\Models;

use App\Services\Media\ImageSizeProfile;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PageWidget extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'layout_id',
        'column_index',
        'widget_type_id',
        'label',
        'config',
        'query_config',
        'appearance_config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config'            => 'array',
        'query_config'      => 'array',
        'appearance_config' => 'array',
        'is_active'         => 'boolean',
    ];

    public function scopeForOwner($query, Model $owner)
    {
        return $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    public function scopeInSlot($query, Model $owner, ?string $layoutId, ?int $columnIndex)
    {
        if ($layoutId) {
            return $query->where('layout_id', $layoutId)
                ->where('column_index', $columnIndex ?? 0);
        }

        return $query->forOwner($owner)->whereNull('layout_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function widgetType(): BelongsTo
    {
        return $this->belongsTo(WidgetType::class);
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(PageLayout::class, 'layout_id');
    }

    /**
     * Recursively copy every widget + layout owned by $source to $target.
     * Media attached to each widget is copied across via spatie's copy().
     */
    public static function copyOwnedStack(Model $source, Model $target): void
    {
        $rootWidgets = static::query()
            ->forOwner($source)
            ->whereNull('layout_id')
            ->orderBy('sort_order')
            ->get();

        foreach ($rootWidgets as $widget) {
            $widget->loadMissing('widgetType');
            $new = static::create([
                'owner_type'        => $target->getMorphClass(),
                'owner_id'          => $target->getKey(),
                'layout_id'         => null,
                'column_index'      => null,
                'widget_type_id'    => $widget->widget_type_id,
                'label'             => $widget->label,
                'config'            => $widget->config,
                'query_config'      => $widget->query_config,
                'appearance_config' => static::resolveAppearance($widget->appearance_config ?? [], $widget->widgetType?->handle),
                'sort_order'        => $widget->sort_order,
                'is_active'         => $widget->is_active,
            ]);

            static::copyMediaTo($widget, $new);
        }

        $layouts = PageLayout::query()
            ->forOwner($source)
            ->orderBy('sort_order')
            ->with('widgets')
            ->get();

        foreach ($layouts as $layout) {
            $newLayout = PageLayout::create([
                'owner_type'    => $target->getMorphClass(),
                'owner_id'      => $target->getKey(),
                'label'         => $layout->label,
                'display'       => $layout->display,
                'columns'       => $layout->columns,
                'layout_config' => $layout->layout_config,
                'sort_order'    => $layout->sort_order,
            ]);

            foreach ($layout->widgets as $widget) {
                $widget->loadMissing('widgetType');
                $new = static::create([
                    'owner_type'        => $target->getMorphClass(),
                    'owner_id'          => $target->getKey(),
                    'layout_id'         => $newLayout->id,
                    'column_index'      => $widget->column_index,
                    'widget_type_id'    => $widget->widget_type_id,
                    'label'             => $widget->label,
                    'config'            => $widget->config,
                    'query_config'      => $widget->query_config,
                    'appearance_config' => static::resolveAppearance($widget->appearance_config ?? [], $widget->widgetType?->handle),
                    'sort_order'        => $widget->sort_order,
                    'is_active'         => $widget->is_active,
                ]);

                static::copyMediaTo($widget, $new);
            }
        }
    }

    /**
     * Return the widget's appearance_config, falling back to the widget type's
     * defaultAppearanceConfig() when the stored value is empty.
     */
    public static function resolveAppearance(array $stored, ?string $widgetTypeHandle): array
    {
        if (! empty($stored)) {
            return $stored;
        }

        if ($widgetTypeHandle) {
            $def = app(\App\Services\WidgetRegistry::class)->find($widgetTypeHandle);
            if ($def) {
                return $def->defaultAppearanceConfig();
            }
        }

        return $stored;
    }

    private static function copyMediaTo(self $source, self $target): void
    {
        foreach ($source->getMedia() as $media) {
            $media->copy($target, $media->collection_name, $media->disk);
        }
    }

    public function configImageUrls(): array
    {
        $urls = [];
        $schema = $this->widgetType?->config_schema ?? [];

        foreach ($schema as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video'])) {
                $media = $this->getFirstMedia("config_{$field['key']}");
                $urls[$field['key']] = $media?->getUrl();
            }
        }

        return $urls;
    }

    public function appearanceImageUrl(): ?string
    {
        $media = $this->getFirstMedia('appearance_background_image');
        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('appearance_background_image')->singleFile();
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
