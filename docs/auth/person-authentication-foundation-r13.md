# Person & Authentication Foundation — R13 integration notes

## Scope boundaries

Person / User Management owns Person identity, Authentication Account provisioning, account lifecycle, and the Person–account relationship.

Authentication owns login eligibility, credentials, password recovery, email OTP, MFA challenge, and authenticated session eligibility.

Authorization owns roles, permissions, and assignment.

School owns School administration and membership; it integrates with the foundation for optional administrator assignment during onboarding only.

HRM owns Staff hiring and Staff identity resolution (whether to reuse an existing Person). Section/Academic owns Section context.

## School administrator provisioning

- School contact `email` never triggers administrator assignment.
- Explicit administrator fields only: `account_id` / `admin_id`, or `admin_email` / `admin_name` (and aliases).
- Existing account path: explicit account identifier; membership + admin role outside UserAccountService.
- New account path: new Person (never email-matched), then UserAccountService; then membership + role.
- When admin assignment is requested, School create + assignment run in one DB transaction.
- School-scoped admin role: pass the **School model** to Laratrust `hasRole` / `addRole`
  (not `$school->id`). A plain UUID string is treated as a model class name by Helper::getIdFor().


## MFA challenge (Laragear Two-Factor v3.0.0)

- TOTP credential label: `User::getTwoFactorUserIdentifier()` returns the account **username**
  (not Person email). The package default uses `email`, which is not a users-table attribute.
- Label identifier only — does not change login identifiers or Person email ownership.

- Enrollment: `createTwoFactorAuth` → `confirmTwoFactorAuth`.
- Login challenge: `validateTwoFactorCode($code, useRecoveryCodes: true)` only.
- Enabled check: `hasTwoFactorEnabled()` (not legacy `hasTwoFactorAuth`).
- Challenge attempts rate-limited by account id + IP (5 attempts / 15 minutes). Locked requests do not increment; success clears.

## Email OTP verification

- Verifies Person primary email for `auth_email_verified_at` only; does not enable email login.
- Invalid OTP attempts: 5 failures / 15-minute decay; blocked requests do not hit again; success clears.

## Person soft-delete and sessions

- Soft-deleted Person fails `User::canAuthenticate()`.
- `EnsureAccountEligible` middleware invalidates the session on subsequent requests.
- Restore does not change AccountLifecycle; fresh login is required after restore.

## Credential-bearing events

Domain events must not carry plaintext passwords. Controlled delivery uses notifications / service return values only.

Legacy events cleaned: `UserAccountCreated`, `UserCreated`, `UserPasswordResetByAdmin`, `TenantAdminCreated`. Canonical: `AuthenticationAccountCreated`.

## Future-domain TODOs

<!-- TODO(HRM): Staff identity resolution — when hiring, HRM must decide whether to attach a Staff capacity to an existing Person or create a new Person via User Management Person APIs. Email equality alone must never establish Person identity. Account provisioning remains UserAccountService; role assignment remains Authorization. -->

<!-- TODO(HRM): Staff listing/search — Profile owns name and email; Authentication Account owns username/lifecycle. HRM UI must not query removed users.name / users.email columns. -->

<!-- TODO(Section/Academic): Section context activation and school-switch clearing belong to Section Management / Academic workflows, not SchoolService workspace selection. -->

<!-- TODO(Authorization): Tenant-wide cross-School workspace authority is not granted by role name or schools.view-any reinterpretation; introduce an explicit catalogue capability if required. -->
