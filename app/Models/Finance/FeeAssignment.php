<?php

namespace App\Models\Finance;

use App\Models\Academic\Term;
use App\Models\Model;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * FeeAssignment represents a student's obligation to pay a specific fee in a term.
 * This decouples "fee definition" from "student debt" → enables accurate reporting.
 *
 * @property string $id
 * @property string $school_id
 * @property string $fee_id
 * @property string $user_id
 * @property string $term_id
 * @property float $original_amount
 * @property float $concession_amount
 * @property float $amount_due
 * @property float $amount_paid
 * @property float $balance
 * @property \Carbon\Carbon $due_date
 * @property string $status              // pending, partial, paid, overdue
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class FeeAssignment extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity;

    protected $table = 'fee_assignments';

    protected $fillable = [
        'school_id',
        'fee_id',
        'user_id',
        'term_id',
        'original_amount',
        'concession_amount',
        'amount_due',
        'amount_paid',
        'due_date',
        'status',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'concession_amount' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
    ];

    protected $appends = ['balance'];

    protected $hidden = ['deleted_at', 'created_at', 'updated_at'];

    protected array $globalFilterFields = [
        'status',
    ];

    protected array $hiddenTableColumns = [
        'deleted_at', 'created_at', 'updated_at',
    ];

    /** Virtual attribute: amount_due - amount_paid */
    public function getBalanceAttribute(): float
    {
        return (float) ($this->amount_due - $this->amount_paid);
    }

    /** Relationships */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /** Activity log */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fee_assignment')
            ->logOnly(['amount_due', 'amount_paid', 'status', 'due_date'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) =>
                "Fee assignment for {$this->user?->name} {$eventName}"
            );
    }
}
