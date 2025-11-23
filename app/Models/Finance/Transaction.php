<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Transaction model – the single source of truth for all financial movements.
 * Every income (fee payment) and expense creates a transaction.
 *
 * @property string $id
 * @property string $school_id
 * @property string $payable_type
 * @property string $payable_id
 * @property string $transaction_type        // income | expense
 * @property string $category                // Tuition, Salary, Utilities, etc.
 * @property float $amount
 * @property float|null $balance_after
 * @property \Carbon\Carbon $transaction_date
 * @property string|null $description
 * @property string|null $recorded_by
 * @property string|null $reference
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity, HasConfig, BelongsToSchool;

    protected $table = 'transactions';

    protected $fillable = [
        'school_id',
        'payable_type',
        'payable_id',
        'transaction_type',
        'category',
        'amount',
        'balance_after',
        'transaction_date',
        'description',
        'recorded_by',
        'reference',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
        'meta' => 'array',
    ];

    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /** Columns available for global search */
    protected array $globalFilterFields = [
        'category',
        'description',
        'reference',
        'transaction_type',
    ];

    /** Columns hidden from table UI (never searchable/sortable) */
    protected array $hiddenTableColumns = [
        'deleted_at',
        'created_at',
        'updated_at',
        'meta',
        'payable_type',
        'payable_id',
    ];

    /**
     * Polymorphic relationship – a transaction belongs to a payable model
     * (Fee, Payment, Expense, etc.)
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Who recorded this transaction */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** School scope already handled by BelongsToSchool trait */

    /** Activity log configuration */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transaction')
            ->logOnly(['transaction_type', 'category', 'amount', 'transaction_date', 'reference'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                "Financial transaction {$eventName}: {$this->amount} ({$this->transaction_type})"
            );
    }

    public function getConfigurableProperties(): array
    {
        // TODO add to seeder
        return ['category', 'transaction_type'];
    }
}
