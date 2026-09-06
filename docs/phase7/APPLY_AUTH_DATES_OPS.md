# Apply LifecycleOperationalService auth + inclusive dates

The GitHub connector cannot reliably push the full ~37KB `LifecycleOperationalService.php` in one shot from this agent.

**Required file (complete):** project artifacts path:

`artifacts/phase7/LifecycleOperationalService.auth-dates.php`

Also present as:
- `artifacts/phase7/LifecycleOperationalService.auth-dates.php`

## What this version adds

1. `normalizeReportFilters()` — `date_from`/`deadline_from` → startOfDay; `date_to`/`deadline_to` → endOfDay (used by applications/admissions/enrollments queries).
2. `needsAttention` / `upcomingDeadlines` / `recentlyCompleted` accept `?array $categories` and **count + limit only authorized categories** (`applications` | `admissions` | `enrollments`).
3. `placementsQuery` filters by `school_id` on the placement row (Phase 6).
4. Stray empty docblock removed.

## Apply locally

```bash
cp artifacts/phase7/LifecycleOperationalService.auth-dates.php \
   app/Services/Student/LifecycleOperationalService.php
git add app/Services/Student/LifecycleOperationalService.php
git commit -m "fix(student-lifecycle): category-scoped ops feeds + inclusive date filters"
git push origin feature/student-lifecycle-phase7
```

Controller already passes `$this->authorizedCategories()` (commit b98d377).
Reports controller already calls `normalizeReportFilters` (commit de8a689).
