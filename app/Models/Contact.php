<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'prefix',
        'first_name',
        'last_name',
        'organization_name',
        'preferred_name',
        'email',
        'email_secondary',
        'phone',
        'phone_secondary',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'notes',
        'custom_data',
        'is_deceased',
        'do_not_contact',
        'source',
    ];

    protected $casts = [
        'custom_data' => SchemalessAttributes::class,
        'is_deceased' => 'boolean',
        'do_not_contact' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        if ($this->type === 'organization') {
            return $this->organization_name ?? '';
        }

        $parts = array_filter([$this->first_name, $this->last_name]);

        return implode(' ', $parts);
    }
}
