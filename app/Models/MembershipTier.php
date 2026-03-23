<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class MembershipTier extends Model
{
    use HasFactory, HasSlug, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'billing_interval',
        'default_price',
        'renewal_notice_days',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'default_price'       => 'decimal:2',
        'is_active'           => 'boolean',
        'renewal_notice_days' => 'integer',
        'sort_order'          => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'tier_id');
    }
}
