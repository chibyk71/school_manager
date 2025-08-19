<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
/**
 *  This model stores the type of fees expected to be collected by the school.
 *  so the fees are just a collection of fee types. to group fees together.
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $color
 * @property string $options
 * @property int $school_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $deleted_at
 */
class FeeType extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeTypeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'color',
        'options',
        'school_id'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fee_type')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
