<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageLayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_id',
        'label',
        'display',
        'columns',
        'layout_config',
        'sort_order',
    ];

    protected $casts = [
        'layout_config' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(PageWidget::class, 'layout_id')
            ->orderBy('column_index')
            ->orderBy('sort_order');
    }
}
