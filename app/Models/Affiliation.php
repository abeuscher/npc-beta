<?php

namespace App\Models;

use App\Observers\AffiliationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AffiliationObserver::class)]
class Affiliation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'contact_id',
        'organization_id',
        'role',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Affiliation $affiliation) {
            if (! $affiliation->is_primary) {
                return;
            }

            static::query()
                ->where('contact_id', $affiliation->contact_id)
                ->when($affiliation->exists, fn ($q) => $q->where('id', '!=', $affiliation->id))
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function bindContactToOrganization(Contact $contact, Organization $organization): self
    {
        $contactHasPrimary = self::where('contact_id', $contact->id)
            ->where('is_primary', true)
            ->exists();

        return self::firstOrCreate(
            ['contact_id' => $contact->id, 'organization_id' => $organization->id],
            ['is_primary' => ! $contactHasPrimary]
        );
    }
}
