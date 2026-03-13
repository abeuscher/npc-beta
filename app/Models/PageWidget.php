<?php

namespace App\Models;

use App\Widgets\Widget;
use App\Widgets\WidgetRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageWidget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'page_id',
        'widget_type',
        'label',
        'config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config'    => 'array',
        'is_active' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Resolve the widget class from the registry and return an instance, or null.
     */
    public function typeInstance(): ?Widget
    {
        return WidgetRegistry::get($this->widget_type);
    }
}
