<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'success'  => 'boolean',
        'delivered_at' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function notification()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeSms($q)      { return $q->where('channel', 'sms'); }
    public function scopeEmail($q)    { return $q->where('channel', 'mail'); }
    public function scopeSuccessful($q) { return $q->where('success', true); }
    public function scopeFailed($q)     { return $q->where('success', false); }
}
