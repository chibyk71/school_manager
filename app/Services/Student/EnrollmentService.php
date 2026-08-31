<?php

namespace App\Services\Student;

use App\Models\Academic\AcademicSession;
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

/** EnrollmentService Phase 4 - see artifacts/EnrollmentService.phase4.final.php for full source if needed. */
class EnrollmentService
{
    // Temporary stub - REPLACE IMMEDIATELY
    public function start(School $school, User $actor, array $data): Enrollment
    {
        throw new \RuntimeException('EnrollmentService must be restored from artifacts/EnrollmentService.phase4.final.php');
    }
}
