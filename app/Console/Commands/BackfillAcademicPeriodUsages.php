<?php

namespace App\Console\Commands;

use App\Contracts\Academic\TracksAcademicUsage;
use App\Models\Academic\AcademicSession;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Models\Student\StudentSessionPlacement;
use App\Services\Academic\AcademicPeriodUsageRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Idempotent backfill of academic_period_usages for live tracked resources.
 *
 * Fails loudly (non-zero exit) on cross-school or invalid session relationships.
 * Write mode runs in a single DB transaction so a failure cannot leave a partial registry.
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

        try {
            $runner = function () use ($models, $registry, $dry, $schoolFilter, &$registered, &$skipped) {
                foreach ($models as $class) {
                    $query = $class::query();

                    if ($schoolFilter && $class !== StudentSessionPlacement::class) {
                        $query->where('school_id', $schoolFilter);
                    }

                    $query->orderBy('id')->chunkById(200, function ($rows) use (
                        $registry,
                        $dry,
                        $schoolFilter,
                        &$registered,
                        &$skipped,
                        $class
                    ) {
                        foreach ($rows as $row) {
                            /** @var Model $row */
                            if (! $row instanceof TracksAcademicUsage) {
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

                            $schoolId = $row->academicUsageSchoolId();
                            $sessionId = $row->academicUsageSessionId();

                            if ($sessionId === null || $schoolId === null) {
                                if ($class === StudentSessionPlacement::class
                                    || $class === Enrollment::class
                                    || $class === Admission::class
                                ) {
                                    throw ValidationException::withMessages([
                                        'academic_usage' => sprintf(
                                            'Live %s#%s is missing school_id or academic_session_id required for academic usage tracking.',
                                            class_basename($class),
                                            $row->getKey()
                                        ),
                                    ]);
                                }

                                $skipped++;

                                continue;
                            }

                            if (! $row->shouldTrackAcademicUsage()) {
                                $skipped++;

                                continue;
                            }

                            if ($dry) {
                                $session = AcademicSession::query()->whereKey($sessionId)->first();
                                if ($session === null) {
                                    throw ValidationException::withMessages([
                                        'academic_session_id' => sprintf(
                                            'Academic session %s does not exist for %s#%s.',
                                            $sessionId,
                                            class_basename($class),
                                            $row->getKey()
                                        ),
                                    ]);
                                }
                                if ((string) $session->school_id !== (string) $schoolId) {
                                    throw ValidationException::withMessages([
                                        'academic_session_id' => sprintf(
                                            'Cross-school contradiction: %s#%s school=%s session=%s belongs to school=%s.',
                                            class_basename($class),
                                            $row->getKey(),
                                            $schoolId,
                                            $sessionId,
                                            $session->school_id
                                        ),
                                    ]);
                                }
                                $registered++;
                            } else {
                                $registry->register($row);
                                $registered++;
                            }
                        }
                    });
                }
            };

            if ($dry) {
                $runner();
            } else {
                DB::transaction($runner);
            }
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->implode('; ');
            $this->error('Backfill aborted (contradictory academic relationship): '.$message);

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Backfill aborted: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(($dry ? '[dry-run] Would register' : 'Registered')." {$registered} usage row(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
