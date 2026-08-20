<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// The submission observer is registered by the Forms plugin's provider
// (FormSubmission::observe() at boot — contract surface 1's inversion of the
// former #[ObservedBy] attribute; a core-model reference to a plugin class
// would be a banned core→plugin reach).
class FormSubmission extends Model
{
    use HasFactory, SoftDeletes;

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
