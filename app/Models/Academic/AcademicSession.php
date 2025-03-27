<?php

namespace App\Models\Academic;

use App\Models\School;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicSession extends Model
{
    /** @use HasFactory<\Database\Factories\AcademicSessionFactory> */
    use HasFactory, BelongsToSchool  /** TODO:SoftDeletes*/;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'school_id',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function terms() {
        return $this->hasMany(Term::class);
    }

    public function currentSession () {
        return $this->first()->where('is_current', true);
    }
}
