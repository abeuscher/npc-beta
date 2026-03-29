<?php

namespace App\Models;

use App\Models\User;
use App\Observers\PageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy(PageObserver::class)]
class Page extends Model
{
    use HasFactory, HasSlug, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'author_id', // required; set to auth user on creation
        'meta_title',
        'meta_description',
        'custom_fields',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'type'   => 'default',
        'status' => 'draft',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'published_at'  => 'datetime',
    ];

    public function scopePublished($query): void
    {
        $query->where('status', 'published');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function pageWidgets(): HasMany
    {
        return $this->hasMany(PageWidget::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
