<?php

namespace App\Models\Employee;

use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payroll extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\PayrollFactory> */
    use HasFactory, HasTransaction, LogsActivity;

    protected $fillable = [
        'staff_id',
        'salary_id',
        'bonus',
        'deduction',
        'net_salary',
        'payment_date',
        'description',
        'status',
        'school_id'
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'net_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deduction' => 'decimal:2'
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    public function getAmount() {
        return $this->net_salary;
    }

    public function getTransactionType() {
        return 'expense';
    }

    public function getCategory() {
        return 'salary';
    }

    public function getActivitylogOptions() {
        return LogOptions::defaults()
            ->logOnly(['staff_id', 'salary_id', 'bonus', 'deduction', 'net_salary', 'payment_date', 'description', 'status'])
            ->useLogName('payroll');
    }
}
