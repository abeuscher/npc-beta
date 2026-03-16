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
        'embedding',
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
}
