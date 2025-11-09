<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FeeInstallmentDetail model for tracking individual student installment details for a fee payment plan.
 *
 * @property string $id
 * @property string $school_id
 * @property string $fee_installment_id
 * @property string $user_id
 * @property float $amount
 * @property \Carbon\Carbon $due_date
 * @property string $status
 * @property \Carbon\Carbon|null $paid_date
 * @property float|null $punishment
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class FeeInstallmentDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeInstallmentDetailFactory> */
    use HasFactory, BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'fee_installment_id',
        'user_id',
        'amount',
        'due_date',
        'status',
        'paid_date',
        'punishment',
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
        'paid_date' => 'date',
        'punishment' => 'decimal:2',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
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
            ->useLogName('fee_installment_detail')
            ->logOnly(['fee_installment_id', 'user_id', 'amount', 'due_date', 'status', 'paid_date', 'punishment', 'school_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Fee installment detail {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the fee installment associated with this detail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feeInstallment()
    {
        return $this->belongsTo(FeeInstallment::class);
    }

    /**
     * Get the school associated with this fee installment detail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user (student) associated with this fee installment detail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}