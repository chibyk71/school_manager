# School Management Phase 1 — Push status

**Do not merge.**

## Remote commits (partial)
- States scaffold
- SchoolManagementFeatures definition + DefaultEntitlementProfile schools.max_count
- Nullable code/email/type migration
- **AppServiceProvider registers SchoolManagementFeatures** (blocking fix landed)
- SchoolCapacityService

## Full tip SHA (local / artifact)
`7469c5160011fd5eba3676e2d7c0a8f06fa476b4`

Still missing on remote relative to full tip:
- SchoolReadinessEvaluator, SchoolLifecycleService
- Main lifecycle+capacity migration (state, contact_name, school_capacity_locks, defensive backfill)
- School model (HasStates, contact_name, state cast)
- SchoolService createDraft integration
- Policy lifecycle methods, controller actions, routes
- Store/Update requests (name-only draft; state prohibited)
- Domain unit tests

## Owner recovery
Artifact: `artifacts/school-management-phase1/phase1-complete-7469c51.bundle`
and `complete-tip/` sources.

```bash
git checkout feature/school-management-module
git pull
# apply full tip from local clone or bundle, then push
```
