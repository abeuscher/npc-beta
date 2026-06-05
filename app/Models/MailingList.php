<?php

namespace App\Models;

use App\Services\MailingListQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailingList extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'conjunction',
        'raw_where',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function filters(): HasMany
    {
        return $this->hasMany(MailingListFilter::class)->orderBy('sort_order');
    }

    public function contacts(): Builder
    {
        return MailingListQueryBuilder::build($this);
    }

    /**
     * Duplicate this list's definition and its filter rules into a new record.
     * Membership is derived from the filters (the `contacts()` query), so there
     * is nothing else to copy.
     */
    public function duplicate(): self
    {
        $copy = $this->replicate();
        $copy->name = 'Copy of ' . $this->name;
        $copy->save();

        foreach ($this->filters as $filter) {
            $newFilter = $filter->replicate(['mailing_list_id']);
            $newFilter->mailing_list_id = $copy->id;
            $newFilter->save();
        }

        return $copy;
    }
}
