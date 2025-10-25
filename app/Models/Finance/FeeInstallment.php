<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FeeInstallment model for creating payment plans for fees, storing installment settings for a fee.
 *
 * @property int $id
 * @property int $school_id
 * @property int $fee_id
 * @property int $no_of_installment
 * @property float|null $initial_amount_payable
 * @property \Carbon\Carbon $due_date
 * @property array|null $options
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class FeeInstallment extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeInstallmentFactory> */
    use HasFactory, BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'fee_id',
        'no_of_installment',
        'initial_amount_payable',
        'due_date',
        'options',
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
        'initial_amount_payable' => 'decimal:2',
        'due_date' => 'date',
        'options' => 'array',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'no_of_installment',
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
        'options',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fee_installment')
            ->logOnly(['fee_id', 'no_of_installment', 'initial_amount_payable', 'due_date', 'school_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Fee installment {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the fee associated with this installment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }
}