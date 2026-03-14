<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageWidget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'page_id',
        'widget_type_id',
        'label',
        'config',
        'query_config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config'       => 'array',
        'query_config' => 'array',
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
}
