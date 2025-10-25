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
 * SalaryStructure model representing specific salary components for a department role in a school.
 *
 * @property int $id
 * @property int $school_id
 * @property int $salary_id
 * @property int $department_role_id
 * @property float $amount
 * @property string $currency
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property array $salary_type
 */
class SalaryStructure extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery, HasConfig;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'salary_structures';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'salary_id',
        'department_role_id',
        'amount',
        'currency',
        'effective_date',
        'name',
        'description',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'salary_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'string',
        'effective_date' => 'date',
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
        'amount',
        'currency',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('salary_structure')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Salary structure {$this->name} for department role ID {$this->department_role_id} was {$eventName}");
    }

    /**
     * Get the salary type from configurations.
     *
     * @return array
     */
    public function getSalaryTypeAttribute(): array
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
     * Define the relationship to the Salary model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    /**
     * Define the relationship to the DepartmentRole model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function departmentRole()
    {
        return $this->belongsTo(DepartmentRole::class, 'department_role_id');
    }
}