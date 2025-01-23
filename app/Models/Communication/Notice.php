<?php

namespace App\Models\Communication;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    use HasConfig, LogsActivity;
    protected $fillable =[
        'title',
        'body',
        'school_id',
        'sender_id',
        'is_public',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'datetime',
        'is_public' => 'boolean'
    ];

    protected $appends = [
        'type'
    ];

    public function getTypeAttribute()
    {
        return $this->configs();
    }

    public function school()
    {
        return $this->belongsTo('App\Models\School');
    }

    public function sender()
    {
        return $this->belongsTo('App\Models\User', 'sender_id');
    }

    public function recipients() {
        return $this->belongsToMany('App\Models\User', 'notice_recipients', 'notice_id', 'user_id');
    }
}
