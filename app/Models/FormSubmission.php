<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'form_id',
        'contact_id',
        'data',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'data'       => 'array',
        'created_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class);
    }
}
