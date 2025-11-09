<?php

namespace App\Models\Finance;

use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Expense model representing expenses made by a school or branch.
 *
 * @property string $id
 * @property string $school_id
 * @property string $recorded_by
 * @property float $amount
 * @property string $category
 * @property string|null $description
 * @property string $status
 * @property \Carbon\Carbon $expense_date
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Expense extends Model
{
    use BelongsToSchool, HasTableQuery, HasTransaction, LogsActivity, SoftDeletes, HasUuids, HasConfig;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'branch_id',
        'recorded_by',
        'amount',
        'category',
        'description',
        'expense_date',
        'status',
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
        'expense_date' => 'date',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'category',
        'description',
        'status',
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
            ->logOnly(['amount', 'category', 'description', 'status', 'expense_date'])
            ->logOnlyDirty()
            ->useLogName('expense')
            ->setDescriptionForEvent(fn(string $eventName) => "Expense {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the transaction type for expenses.
     *
     * @return string
     */
    public function getTransactionType(): string
    {
        return 'expense';
    }

    /**
     * TODO add to seeder ['bonus', 'allowance', 'overtime', 'deduction']
     * @var array<string>
     */
    public function getConfigurableProperties(): array {
        return ['category',];
    }

    /**
     * Get the category for the transaction.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Get the amount for the transaction.
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }
}