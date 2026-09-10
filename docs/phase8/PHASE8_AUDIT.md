# Phase 8 — Consolidation, Hardening & Finalization

**PR:** https://github.com/chibyk71/school_manager/pull/11

## Canonical pathway

Application → Approval → Admission → Acceptance → **EnrollmentService** → Finalization → Student → **PlacementAllocationService** → Numbers → Promotion

## Removed

- `App\Services\Student\StudentEnrollmentService`
- `App\Services\UserManagement\StudentEnrollmentService`

## HTTP adapters

| Controller | Service |
|------------|---------|
| StudentController | EnrollmentService |
| StudentPlacementController | placeManually + closeCurrentPlacement |

## Tests (Phase8ConsolidationDomainTest)

- Obsolete SES classes gone
- StudentController has no SES import / broken call
- Placement destroy uses closeCurrentPlacement
- Academic\Student shim: table, BelongsToSchool, relations, query
- Foreign-school admission / session rejected
- placeManually / closeCurrentPlacement cross-school rejected
- closeCurrentPlacement closes school placements
- Double finalize rejected
- Capacity-1 sequential placeManually rejects second student

## Prior-phase concurrency retained

Phase5: concurrent capacity slot, concurrent admission/registration numbers  
Phase6: capacity override authorization

## Artifacts if needed

- artifacts/phase8/PlacementAllocationService.phase8.php (includes closeCurrentPlacement)
- artifacts/phase8/Phase8ConsolidationDomainTest.php
- artifacts/phase8/StudentController.phase8.php
