<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportSource extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'notes',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(ImportSession::class);
    }

    public function idMaps(): HasMany
    {
        return $this->hasMany(ImportIdMap::class);
    }
}
