# Phase 8A — Student Lifecycle Architecture & Stale-Code Audit

**Base:** `master` @ `1bf7f823` (Phase 7 merged via PR #10)  
**Branch:** `feature/student-lifecycle-phase8`  
**Date:** 2026-09-07

## Canonical lifecycle pathway

Application → Approval → Admission → Acceptance → Enrollment → Finalization → Student Identity → Placement → Registration Number → Admission Number → Ongoing lifecycle → Promotion

## Canonical implementations

| Responsibility | Implementation |
|---|---|
| Application | StudentApplicationService |
| Admission | AdmissionService |
| Enrollment / finalization | EnrollmentService |
| Placement | PlacementAllocationService |
| Registration numbers | RegistrationNumberService |
| Notifications | LifecycleNotificationService |
| Promotion | PromotionService + jobs |
| Ops/dashboard/export | LifecycleOperationalService |

## Removed in Phase 8

- App\Services\Student\StudentEnrollmentService (legacy wizard/application enroll)
- App\Services\UserManagement\StudentEnrollmentService (referenced non-existent Academic\Student create path)

## Compatibility

- App\Models\Academic\Student extends App\Models\Student\Student (same table)
- StudentPolicy registered for both FQCNs

## School isolation

- StudentController show/update/destroy abort 404 on cross-school
- EnrollmentService rejects foreign admission_id / session
- PlacementAllocationService asserts school bounds

## Remaining limitations

- Non-lifecycle modules still type-hint Academic\Student in places; shim covers class existence
- Full concurrent pest suite not runnable in agent sandbox (no vendor/php-dom/sqlite)
- StudentPlacementService retained as lower-level helper used by PAS/Status/Transfer
