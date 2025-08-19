<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\User;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\PaymentFactory> */
    use HasFactory, LogsActivity, HasTransaction;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payments')
            ->logAll()
            ->logExcept(['created_at'])
            ->logOnlyDirty();
    }

    protected $fillable = [
        'user_id',
        'payment_method',
        'payment_status',
        'payment_amount',
        'payment_currency',
        'payment_reference',
        'payment_date',
        'payment_description',
        'fee_installment_detail_id',
        'fee_id'
    ];

    public function getAmount() {
        return $this->payment_amount ?? 0.0;
    }

    protected $casts = [
        'payment_date' => 'datetime',
        'payment_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    public function feeInstallmentDetail()
    {
        return $this->belongsTo(FeeInstallmentDetail::class);
    }
}
