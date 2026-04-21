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
        'contacts_field_map',
        'contacts_custom_field_map',
        'contacts_match_key',
        'contacts_match_key_column',
        'events_field_map',
        'events_custom_field_map',
        'events_match_key',
        'events_match_key_column',
        'events_contact_match_key',
        'donations_field_map',
        'donations_custom_field_map',
        'donations_contact_match_key',
        'memberships_field_map',
        'memberships_custom_field_map',
        'memberships_contact_match_key',
        'invoices_field_map',
        'invoices_custom_field_map',
        'invoices_contact_match_key',
        'notes_field_map',
        'notes_custom_field_map',
        'notes_contact_match_key',
    ];

    protected $casts = [
        'contacts_field_map'           => 'array',
        'contacts_custom_field_map'    => 'array',
        'events_field_map'             => 'array',
        'events_custom_field_map'      => 'array',
        'donations_field_map'          => 'array',
        'donations_custom_field_map'   => 'array',
        'memberships_field_map'        => 'array',
        'memberships_custom_field_map' => 'array',
        'invoices_field_map'           => 'array',
        'invoices_custom_field_map'    => 'array',
        'notes_field_map'              => 'array',
        'notes_custom_field_map'       => 'array',
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
