<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FeeConcession model for providing discounts, waivers, or reductions to students or groups of students.
 *
 * @property int $id
 * @property int $school_id
 * @property int $fee_type_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property float $amount
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class FeeConcession extends Model
{
    use HasFactory, BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'fee_type_id',
        'name',
        'description',
        'type',
        'amount',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be hidden for arrays and JSON responses.
     *
     * @var array<string>
     */
    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'description',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fee_concession')
            ->logOnly(['name', 'description', 'type', 'amount', 'start_date', 'end_date', 'school_id', 'fee_type_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Fee concession {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the fee type associated with this fee concession.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feeType()
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    /**
     * Get the school associated with this fee concession.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the users associated with this fee concession.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_fee_concessions', 'fee_concession_id', 'user_id');
    }
}