<?php

namespace App\Console\Commands;

use App\Models\Academic\AcademicPeriodUsage;
use App\Models\Academic\AcademicSession;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Models\Student\StudentSessionPlacement;
use App\Services\Academic\AcademicPeriodUsageRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Idempotent backfill of academic_period_usages for live tracked resources.
 *
 * Fails loudly on cross-school or invalid session relationships.
 */
class BackfillAcademicPeriodUsages extends Command
{
    protected $signature = 'academic:backfill-period-usages
                            {--school= : Limit to a single school UUID}
                            {--dry-run : Report without writing}';

    protected $description = 'Backfill academic_period_usages for Application, Admission, Enrollment, and Placement';

    public function handle(AcademicPeriodUsageRegistry $registry): int
    {
        $schoolFilter = $this->option('school');
        $dry = (bool) $this->option('dry-run');
        $registered = 0;
        $skipped = 0;

        $models = [
            StudentApplication::class,
            Admission::class,
            Enrollment::class,
            StudentSessionPlacement::class,
        ];

        foreach ($models as $class) {
            $query = $class::query();
            if (method_exists($class, 'bootSoftDeletes') || in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)) {
                // default query excludes soft-deleted
            }

            if ($schoolFilter && $class !== StudentSessionPlacement::class) {
                $query->where('school_id', $schoolFilter);
            }

            $query->orderBy('id')->chunkById(200, function ($rows) use ($registry, $dry, $schoolFilter, &$registered, &$skipped, $class) {
                foreach ($rows as $row) {
                    if (! $row instanceof \App\Contracts\Academic\TracksAcademicUsage) {
                        $skipped++;

                        continue;
                    }

                    if ($class === StudentSessionPlacement::class && $schoolFilter) {
                        $sid = $row->academicUsageSchoolId();
                        if ((string) $sid !== (string) $schoolFilter) {
                            $skipped++;

                            continue;
                        }
                    }

                    if (! $row->shouldTrackAcademicUsage()) {
                        $skipped++;

                        continue;
                    }

                    try {
                        if ($dry) {
                            // Validate without write
                            $sessionId = $row->academicUsageSessionId();
                            $schoolId = $row->academicUsageSchoolId();
                            $session = AcademicSession::query()->whereKey($sessionId)->first();
                            if ($session === null || (string) $session->school_id !== (string) $schoolId) {
                                throw ValidationException::withMessages([
                                    'academic_session_id' => "Invalid session relationship for {$class}#{$row->getKey()}",
                                ]);
                            }
                            $registered++;
                        } else {
                            $registry->register($row);
                            $registered++;
                        }
                    } catch (ValidationException $e) {
                        $this->error(sprintf(
                            'Contradictory academic relationship for %s#%s: %s',
                            $class,
                            $row->getKey(),
                            collect($e->errors())->flatten()->implode('; ')
                        ));

                        return false; // stop chunk
                    }
                }
            });
        }

        $this->info(($dry ? '[dry-run] Would register' : 'Registered')." {$registered} usage row(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
