<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailingListFilter extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'mailing_list_id',
        'field',
        'operator',
        'value',
        'sort_order',
    ];

    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(MailingList::class);
    }
}
