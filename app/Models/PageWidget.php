<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageWidget extends Model
{
    use HasFactory, HasUuids;

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
}
