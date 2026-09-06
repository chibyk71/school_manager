# Apply session-scoped dashboardCounts

Controller already passes `AcademicCalendarService::currentSession()` into `dashboardCounts`.

Apply the service change:

```bash
# Preferred: full file from project artifacts
cp artifacts/phase7/LifecycleOperationalService.session-dashboard.php \
   app/Services/Student/LifecycleOperationalService.php

# Or patch from tip base:
patch -p1 < docs/phase7/ops_session_dashboard.patch

git add app/Services/Student/LifecycleOperationalService.php
git commit -m "fix(student-lifecycle): session-scope dashboard placement and capacity"
git push origin feature/student-lifecycle-phase7
```

Tests: `tests/Unit/StudentLifecycle/Phase7DashboardSessionScopeTest.php`
