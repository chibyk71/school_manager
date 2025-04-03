<?php

namespace App\Models\Communication;

use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Feedback extends Model
{
    use BelongsToSchool, BelongsToSections, LogsActivity, HasConfig;
    protected $table = 'feedback';

    protected $fillable = [
        'feedbackable_id',
        'feedbackable_type',
        'school_id',
        'handled_by',
        'status',
        'subject',
        'message',

    ];

    protected $appends = [
        'category'// "Complaint", "Suggestion", "Appreciation"
    ];

    public function getActivityLogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at']);
    }

    public function feedbackable()
    {
        return $this->morphTo();
    }

    public function getCategoryAttribute()
    {
        return $this->configs();
    }

    public function handledBy()
    {
        return $this->belongsTo('App\Models\User', 'handled_by');   
    }

    public function scopeHandled($query)
    {
        return $query->where('status', 'handled');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
