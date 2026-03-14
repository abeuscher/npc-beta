<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function getDisplayLocationAttribute(): string
    {
        return collect([$this->city, $this->state])->filter()->implode(', ');
    }
}
