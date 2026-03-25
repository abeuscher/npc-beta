<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'event',
        'description',
        'meta',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'meta'       => 'array',
    ];
}
