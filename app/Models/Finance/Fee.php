<?php

namespace App\Models\Finance;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Term;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * This Model Represent the individual fees to be paid. grouped by fee_type,
 * each school will create a fee structure, with a n amount payable, affected users or group,
 * and a description.
 *
 * 
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Fee extends Model
{
    /** @use HasFactory<\Database\Factories\Finance\FeeFactory> */
    use HasFactory;

    protected $fillable = [
        'term_id',
        'fee_type_id',
        'amount',
        'description',
        'due_date'
    ];

    protected $cast = [
        'amount' => 'decimal:2',
        'due_date' => 'date'
    ];

    public function fee_type()
    {
        return $this->belongsTo(FeeType::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

     /**
     * The classSections that belong to the FeeType
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function classSections()
    {
        return $this->belongsToMany(ClassSection::class, 'fee_class_section', 'fee_id', 'class_section_id');
    }
}
