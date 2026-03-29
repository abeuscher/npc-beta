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
