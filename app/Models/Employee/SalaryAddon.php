<?php

namespace App\Models\Employee;

use App\Models\Model;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * SalaryAddon model representing custom salary components (bonuses, allowances, overtime, deductions) for employees.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string $name
 * @property float $amount
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $effective_date
 * @property string|null $recurrence
 * @property \Illuminate\Support\Carbon|null $recurrence_end_date
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SalaryAddon extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery, HasConfig;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'salary_addons';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'type',
        'amount',
        'description',
        'effective_date',
        'recurrence',
        'recurrence_end_date',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
        'recurrence_end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'description',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'type',
        'amount',
        'recurrence',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('salary_addon')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Salary addon '{$this->name}' ({$this->type}) for user ID {$this->user_id} was {$eventName}");
    }

    /**
     * Get the salary addon type from configurations.
     *
     * @return array
     */
    public function getTypeAttribute(): array
    {
        return $this->configs();
    }

    /**
     * Define the relationship to the School model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Define the relationship to the User model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}