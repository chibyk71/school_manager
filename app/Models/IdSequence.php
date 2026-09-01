<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdSequence extends Model
{
    protected $table = 'id_sequences';

    protected $fillable = [
        'type',
        'school_id',
        'scope_key',
        'year',
        'last_value',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_value' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
