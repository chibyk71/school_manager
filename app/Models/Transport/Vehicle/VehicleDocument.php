<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Model;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleDocument extends Model
{
    /** @use HasFactory<\Database\Factories\Transport\Vehicle\VehicleDocumentFactory> */
    use HasFactory, LogsActivity, HasConfig;

    protected $fillable = [
        'vehicle_id',
        'title',
        'date_of_expiry',
        'description',
        'options'
    ];

    protected $casts = ['options' => 'array', 'date_of_expiry' => 'date'];

    protected $primaryKey = 'id';
    protected $table = 'vehicle_documents';

    protected $appends = [
        'type'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle_document')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function vehicle()
    {
        return $this->belongsTo('App\Models\Transport\Vehicle\Vehicle');
    }

    public function getTypeAttribute() {
        return $this->configs();
    }
}
