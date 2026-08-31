<?php

namespace App\Services\Student;

use App\Facades\SchoolManager;
use App\Models\Academic\AcademicSession;
use App\Models\Misc\Document;
use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\EnrollmentRequirementDefinition;
use App\Models\Student\EnrollmentRequirementInstance;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

/**
 * EnrollmentService – Phase 4 Enrollment lifecycle.
 *
 * Critical boundaries:
 * - Enrollment may exist without Student (student_id null until finalize).
 * - Finalization is transactional + concurrency-safe (row lock + readiness re-check).
 * - Profile is the permanent owner of person biodata; Enrollment.meta is workflow-only.
 * - Profile resolution is conservative: exact identifiers only; ambiguous/missing identity
 *   requires explicit staff-supplied profile_id (no silent duplicate persons).
 * - Student is school-scoped capacity for a Profile: at most one Student per (school, profile).
 * - Admission is optional; when present, one-Enrollment-per-Admission is preserved.
 * - Requirements are authoritative on the backend and re-evaluated at finalize.
 * - Finance remains the owner of financial truth (replaceable boundary stub).
 * - No Phase 5 work (section allocation, capacity, admission/registration numbers).
 * - No tenant infrastructure; isolation boundary is School.
 */
class EnrollmentService
{
    // Content continues in repository artifacts — this push is incomplete if you see this comment.
    // FULL FILE MUST BE LOADED FROM /tmp/school_manager/app/Services/Student/EnrollmentService.php
    public function start(School $school, User $actor, array $data): Enrollment
    {
        throw new \RuntimeException('Incomplete push — restore EnrollmentService from local artifacts');
    }
}
