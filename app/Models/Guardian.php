<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use SpykApp\LaravelCustomFields\Traits\HasCustomFields;
use SpykApp\LaravelCustomFields\Traits\LoadCustomFields;

class Guardian extends Model
{
    /** @use HasFactory<\Database\Factories\GuardianFactory> */
    use HasFactory, HasCustomFields, LoadCustomFields, SoftDeletes;

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
