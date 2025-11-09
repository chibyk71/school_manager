<?php

namespace App\Models\Finance;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Term;
use App\Models\Model;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fee model representing individual fees to be paid, grouped by fee type.
 * Each fee is associated with a school, term, and optional branch.
 *
 * @property string $id
 * @property string $school_id
 * @property string $fee_type_id
 * @property string $term_id
 * @property string $recorded_by
 * @property string|null $description
 * @property float $amount
 * @property \Carbon\Carbon $due_date
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Fee extends Model
{
    use HasFactory, BelongsToSchool, HasTableQuery, HasTransaction, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'branch_id',
        'fee_type_id',
        'term_id',
        'recorded_by',
        'description',
        'amount',
        'due_date',
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
        'due_date' => 'date',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
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
            ->logOnly(['amount', 'description', 'due_date', 'fee_type_id', 'term_id'])
            ->logOnlyDirty()
            ->useLogName('fee')
            ->setDescriptionForEvent(fn(string $eventName) => "Fee {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the fee type associated with this fee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * Get the term associated with this fee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Get the class sections associated with this fee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function classSections()
    {
        return $this->belongsToMany(ClassSection::class, 'fee_class_section', 'fee_id', 'class_section_id');
    }

    /**
     * Get the transaction type for fees.
     *
     * @return string
     */
    public function getTransactionType(): string
    {
        return 'income';
    }

    /**
     * Get the category for the transaction.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return $this->feeType->name ?? 'fee';
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
