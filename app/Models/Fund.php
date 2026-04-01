<?php

namespace App\Models;

use App\Traits\Archivable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    use Archivable, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'restriction_type',
        'is_archived',
        'quickbooks_account_id',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
