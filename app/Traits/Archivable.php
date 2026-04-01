<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Archivable
{
    public function scopeWithoutArchived(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }
}
