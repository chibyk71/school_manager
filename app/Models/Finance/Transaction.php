<?php

namespace App\Models\Finance;

use App\Models\Model;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'transaction_type',
        'payable_id',
        'payable_type',
        'school_id',
        'school_section_id',
        'reference_number',
        'amount',
        'payment_method',
        'description',
        'transaction_date',
        'recorded_by',
    ];

    public function payable()
    {
        return $this->morphTo();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }
}
