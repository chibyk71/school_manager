<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaction model representing financial transactions (income or expense) in the school management system.
 *
 * @property int $id
 * @property string $transaction_type
 * @property int|null $payable_id
 * @property string|null $payable_type
 * @property string $category
 * @property int $school_id
 * @property int|null $school_section_id
 * @property float $amount
 * @property string|null $payment_method
 * @property string|null $description
 * @property \Carbon\Carbon $transaction_date
 * @property string|null $reference_number
 * @property int|null $recorded_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Transaction extends Model
{
    use HasFactory, BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transaction_type',
        'payable_id',
        'payable_type',
        'category',
        'school_id',
        'school_section_id',
        'amount',
        'payment_method',
        'description',
        'transaction_date',
        'reference_number',
        'recorded_by',
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
        'transaction_date' => 'date',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'category',
        'description',
        'reference_number',
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
            ->useLogName('transaction')
            ->logOnly(['transaction_type', 'category', 'amount', 'transaction_date', 'school_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Transaction {$eventName} for school ID {$this->school_id}");
    }

    /**
     * Get the payable associated with this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function payable()
    {
        return $this->morphTo();
    }

    /**
     * Get the school associated with this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user who recorded this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the school section associated with this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }
}