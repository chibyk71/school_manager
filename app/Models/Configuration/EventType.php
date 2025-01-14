<?php

namespace App\Models\Configuration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EventType extends Model
{
    /** @use HasFactory<\Database\Factories\Configuration\EventTypeFactory> */
    use HasFactory, LogsActivity;


    protected $fillable = [
        'name',
        'description',
        'color',
        'options'
    ];
    protected $casts = ['options' => 'array'];
    protected $primaryKey = 'id';
    protected $table = 'event_types';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event_type')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function events()
    {
        return $this->hasMany('App\Models\Calendar\Event');
    }

    public function getOption(string $option)
    {
        return Arr::get($this->options, $option);
    }

    public function scopeFilterById($q, $id)
    {
        if (!$id) {
            return $q;
        }

        return $q->where('id', '=', $id);
    }

    public function scopeFilterByName($q, $name, $s = 0)
    {
        if (!$name) {
            return $q;
        }

        return $s ? $q->where('name', '=', $name) : $q->where('name', 'like', '%' . $name . '%');
    }

}
