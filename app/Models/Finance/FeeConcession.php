<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\User;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 *  prurpose is To provide discounts, waivers, or reductions to students or groups of students.
 *
 */
class FeeConcession extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeConcessionFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'amount',
        'fee_type_id',
        'start_date',
        'end_date',
        'school_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the feeType that owns the FeeConcession
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feeType()
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id', 'id');
    }

    /**
     * Get the school that owns the FeeConcession
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id', 'id');
    }

    /**
     * The users that belong to the FeeConcession
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_fee_concessions', 'fee_concession_id', 'user_id');
    }

    public function getActivityLogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty();
    }
}
