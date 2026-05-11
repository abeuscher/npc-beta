<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpArticle extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'content',
        'tags',
        'app_version',
        'last_updated',
        'category',
        'embedding',
        'search_weight',
        'parent_slug',
    ];

    protected $casts = [
        'tags' => 'array',
        'last_updated' => 'date',
        'embedding' => 'array',
    ];

    public function routes(): HasMany
    {
        return $this->hasMany(HelpArticleRoute::class);
    }

    public function parent(): ?self
    {
        if (! $this->parent_slug) {
            return null;
        }

        return static::where('slug', $this->parent_slug)->first();
    }
}
