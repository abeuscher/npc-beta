<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PageLayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'label',
        'display',
        'columns',
        'layout_config',
        'appearance_config',
        'sort_order',
    ];

    protected $casts = [
        'layout_config'     => 'array',
        'appearance_config' => 'array',
    ];

    public function scopeForOwner($query, Model $owner)
    {
        return $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(PageWidget::class, 'layout_id')
            ->orderBy('column_index')
            ->orderBy('sort_order');
    }
}
