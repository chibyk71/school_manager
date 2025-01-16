<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeInstallmentDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeInstallmentDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'fee_installment_id', // fee installment id
        'user_id', // user id of the student
        'amount', // amount of the instalment
        'due_date', // date when the instalment is due
        'paid_date', // date when the instalment is paid
        'status', // paid, unpaid, overdue
        'punisment' // if instalment is not paid then punisment will be added
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'punisment' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function feeInstallment()
    {
        return $this->belongsTo(FeeInstallment::class);
    }
}
