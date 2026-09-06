# URGENT: Restore LifecycleOperationalService.php

The file on the branch tip was briefly overwritten by a failed large-file push.

## Immediate restore (required)

From a local clone with the project artifacts folder available:

```bash
# Option A — full Phase 7 auth+dates+category fix (preferred)
cp artifacts/phase7/LifecycleOperationalService.auth-dates.php \
   app/Services/Student/LifecycleOperationalService.php

# Option B — last known good pre-auth service (af326ac tip content)
# git show af326ac5:app/Services/Student/LifecycleOperationalService.php \
#   > app/Services/Student/LifecycleOperationalService.php
# Then still apply Option A for category/date fixes.

git add app/Services/Student/LifecycleOperationalService.php
git commit -m "fix(student-lifecycle): restore LifecycleOperationalService with category + inclusive date filters"
git push origin feature/student-lifecycle-phase7
```

Also available: `artifacts/phase7/LifecycleOperationalService.pre-corruption.php` (tip content before placeholder).

## What auth-dates version adds

1. `normalizeReportFilters()` — inclusive day bounds for date_from/date_to/deadline_*
2. `needsAttention` / `upcomingDeadlines` / `recentlyCompleted` accept `?array $categories` and scope **count + limit** to permitted categories
3. `placementsQuery` uses placement `school_id`
4. Report queries call normalize once

## Already on the branch

- Operations controller passes authorized categories (with Reflection fallback if service not yet restored) — `76051df`
- Reports controller passes session/level/section options + normalizeReportFilters — `de8a689`
- Reports.vue PrimeVue Select filters — `af326ac`
- Tests: `Phase7AuthAndDateFiltersTest.php` — `b98d377`
