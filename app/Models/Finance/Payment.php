<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Payment model for tracking payments made by students for fees or fee installments.
 *
 * @property string $id
 * @property string $school_id
 * @property string $user_id
 * @property string $payment_method
 * @property string $payment_status
 * @property float $payment_amount
 * @property string $payment_currency
 * @property string $payment_reference
 * @property \Illuminate\Support\Carbon $payment_date
 * @property string $payment_description
 * @property string|null $fee_installment_detail_id
 * @property string|null $fee_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\PaymentFactory> */
    use HasFactory, BelongsToSchool, HasTableQuery, LogsActivity, HasTransaction, HasUuids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'school_id',
        'user_id',
        'payment_method',
        'payment_status',
        'payment_amount',
        'payment_currency',
        'payment_reference',
        'payment_date',
        'payment_description',
        'fee_installment_detail_id',
        'fee_id',
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
        'payment_amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'payment_reference',
        'payment_description',
        'payment_status',
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
            ->useLogName('payment')
            ->logOnly(['id', 'school_id', 'user_id', 'payment_method', 'payment_status', 'payment_amount', 'payment_currency', 'payment_reference', 'payment_date', 'payment_description', 'fee_installment_detail_id', 'fee_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Payment {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the amount of the payment.
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->payment_amount ?? 0.0;
    }

    /**
     * Get the user (student) associated with this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the fee associated with this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the fee installment detail associated with this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feeInstallmentDetail()
    {
        return $this->belongsTo(FeeInstallmentDetail::class);
    }
}