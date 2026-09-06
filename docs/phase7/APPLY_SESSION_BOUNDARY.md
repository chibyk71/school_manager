# Apply dashboard session boundary (null ≠ all history)

## Invariant
Student Lifecycle never selects academic sessions and never treats a missing
session as "aggregate every historical session".

- Session resolution: `AcademicCalendarService::currentSession()` (controller).
- Lifecycle service: accepts explicit `?AcademicSession $session` for testability only.
- `$session === null` → all dashboard metrics are **0**.

## Apply

```bash
cp artifacts/phase7/LifecycleOperationalService.session-boundary.php \
   app/Services/Student/LifecycleOperationalService.php

# or
patch -p1 < docs/phase7/ops_session_boundary.patch

git add app/Services/Student/LifecycleOperationalService.php
git commit -m "fix(student-lifecycle): null session yields zero dashboard metrics, not all-history"
git push origin feature/student-lifecycle-phase7
```

Controller already resolves via AcademicCalendarService only.
Tests: `tests/Unit/StudentLifecycle/Phase7DashboardSessionBoundaryTest.php`
