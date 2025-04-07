<?php

namespace App\Traits;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    /**
     * Trait BelongsToSchool
     *
     * This trait provides functionality for models that belong to a specific school.
     * It includes methods for defining relationships, handling automatic assignment
     * of the school ID during model creation, and retrieving records with a fallback
     * mechanism for global (null school_id) entries.
     *
     * Methods:
     * - getSchoolIdColumn(): Returns the name of the column used to store the school ID.
     * - school(): Defines the "belongs to" relationship with the School model.
     * - bootBelongsToSchool(): Boot method to add a global scope for filtering by school
     *   and automatically assign the current school ID when creating a new model instance.
     * - withSchoolFallback(): Retrieves records for the current school or falls back to
     *   global entries (null school_id), grouping by a specified column and returning
     *   distinct entries.
     *
     * Usage:
     * - Include this trait in models that need to be associated with a school.
     * - Ensure the model has a `school_id` column or equivalent, as defined by
     *   the `getSchoolIdColumn` method.
     *
     * Example:
     * ```php
     * use App\Traits\BelongsToSchool;
     *
     * class Student extends Model
     * {
     *     use BelongsToSchool;
     * }
     *
     * // Automatically assigns the current school ID when creating a new student.
     * $student = Student::create(['name' => 'John Doe']);
     *
     * // Retrieve distinct students grouped by name, considering the current school
     * // and falling back to global entries.
     * $students = Student::withSchoolFallback('name');
     * ```
     */
    public static function getSchoolIdColumn(): string
    {
        return 'school_id';
    }

    public function school()
    {
        return $this->belongsTo(School::class, static::getSchoolIdColumn());
    }

    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            $schoolIdColumn = static::getSchoolIdColumn();
            if (!$model->getAttribute($schoolIdColumn) && !$model->relationLoaded('school')) {
                $currentSchool = GetSchoolModel();
                if ($currentSchool) {
                    $model->setAttribute($schoolIdColumn, $currentSchool->id);
                    $model->setRelation('school', $currentSchool);
                }
            }
        });
    }
}
