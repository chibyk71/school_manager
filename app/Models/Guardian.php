<?php

namespace App\Models;

use App\Models\Academic\Student;
use App\Traits\HasSchemalessAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    /** @use HasFactory<\Database\Factories\GuardianFactory> */
    use HasFactory, HasSchemalessAttributes, SoftDeletes;

    protected $fillable = [
        'user_id',
    ];

    /**
     * Get the user that owns the Guardian
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The childeren that belong to the Guardian
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function childeren(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian_pivot', 'guardian_id', 'student_id');
    }
}
