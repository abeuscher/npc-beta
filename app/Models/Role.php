<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'label'];

    /**
     * Display label — falls back to the machine name if no label is set.
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: str($this->name)->replace('_', ' ')->title()->toString();
    }
}
