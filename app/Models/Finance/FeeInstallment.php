<?php

namespace App\Models\Finance;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
/**
 * This Model is used to create payment plans for fees, its sytores installment settings for a fee.
 * payments will be tracked using the payment model.
 * @property int $id
 * @property int $fee_id
 * @property int $no_of_installment
 * @property \Carbon\Carbon $due_date
 * @property float $initial_amount_payable
 * @property array $options
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FeeInstallment extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeInstallmentFactory> */
    use HasFactory, LogsActivity;

    public function  getActivityLogOptions()
    {
        return LogOptions::defaults()
        ->LogOnlyDirty()
        ->useLogName('Fee Installment')
        ->logExcept(['updated_at']);
    }

    protected $fillable = [
        'fee_id', // fee that has instalment enanbled
        'no_of_installment', // numbet of times that installments can be paid, ammount payable for each installment is calculated by dividing the total fee amount by this number
        'due_date', // the date by which the installment is to be paid
        'initial_amount_payable', // The first amount to be paid before installment starts
        'options'
    ];

    protected $casts = [
        'options' => 'array',
        'due_date' => 'date'
    ];

    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }
}
