<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'label',
        'url',
        'page_id',
        'parent_id',
        'sort_order',
        'target',
        'is_visible',
    ];

    protected $casts = [
        'is_visible'  => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'parent_id');
    }
}
