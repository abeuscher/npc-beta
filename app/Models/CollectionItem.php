<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'collection_id',
        'data',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'data'         => 'array',
        'is_published' => 'boolean',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function cmsTags(): MorphToMany
    {
        return $this->morphToMany(CmsTag::class, 'taggable', 'cms_taggables');
    }
}
