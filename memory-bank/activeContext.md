# School Manager — Active Context

## Current Development Phase

**Person & Authentication Foundation — R13 Corrections**

The project is working on a corrections/fixes pass for the Person Authentication Foundation module, specifically addressing R13 integration issues.

## Immediate Objective

Complete R13 corrections for:
1. **MFA protected probe route** — Fixing `EnsureTwoFactorVerified` middleware behaviour (removing redundant check).
2. **MFA username identifier** — Using `User::getTwoFactorUserIdentifier()` (returns username, not email) for TOTP credential labels, and fixing Laratrust School model team args.
3. **Safe-device cookie replication** — Replaying emitted safe-device cookie in tests via `withUnencryptedCookie`.
4. **Rate-limit expiry and school admin outcomes** — Proving natural rate-limit expiry and correct school admin authorization outcomes.

## Active Decisions

| Decision | Status | Notes |
|----------|--------|-------|
| Person identity separated from auth account | Implemented | Profile holds identity; User holds credentials |
| MFA owned by Laragear, not DeviceTracker | Implemented | `config/devices.php` `google_2fa_enabled` is false |
| School-scoped admin roles pass School model to Laratrust | Implemented | Not `$school->id` (UUID string treated as class name) |
| Email OTP verifies Person email only | Implemented | Does not enable email login |
| Soft-deleted Person fails authentication | Implemented | `EnsureAccountEligible` middleware invalidates session |
| TOTP label uses username, not email | Implemented | `User::getTwoFactorUserIdentifier()` |
| Feature/Entitlement Registry sealed at boot | Implemented | Phase 1, consumers: File Manager + School Management |

## Relevant Outstanding Tasks

From documentation TODOs:

1. **HRM Staff identity resolution** — HRM must decide whether to attach Staff capacity to existing Person or create new Person via User Management APIs. Email equality alone must never establish Person identity.
2. **Staff listing/search** — Profile owns name/email; Auth Account owns username/lifecycle. HRM UI must not query removed `users.name` / `users.email` columns.
3. **Section context activation** — Section context activation and school-switch clearing belong to Section Management / Academic workflows, not SchoolService workspace selection.
4. **Cross-school workspace authority** — Tenant-wide cross-School workspace authority should have explicit catalogue capability, not inferred from role name or `schools.view-any`.
5. **File Manager Phase 4** — Integration audit complete; deferred Spatie Media Library migration for avatars, school branding, educational resources.
6. **Spatie upload paths still out of Entitlement quota scope** — Documented, deferred.

## Current Working Tree Changes

Modified files (unstaged):
- `app/Http/Middleware/EnsurePasswordChanged.php` — Corrections applied
- `app/Http/Middleware/EnsureTwoFactorVerified.php` — Shield removed (R13 fix)
- `tests/Feature/Auth/PersonAuthenticationR13CorrectionsTest.php` — Test corrections

Deleted:
- `APPLY-FILE-MANAGER-PHASE2-REVIEW-FIXES.md`