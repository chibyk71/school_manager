# Feature & Entitlement Registry — Phase 1

## Purpose

Phase 1 builds the shared **Feature & Entitlement Registry** that School Manager modules use to register product features and declare the entitlement values that control them.

File Manager is the **only** initial consumer. School Management and every other domain module remain untouched as entitlement consumers in this phase.

## Architecture

| Responsibility | Owner |
|----------------|-------|
| Feature definitions & catalogue discovery | `EntitlementRegistry` |
| Effective-value resolution | `EntitlementResolver` + `EntitlementValueProvider` |
| Code-defined defaults | `DefaultEntitlementProfile` |
| Capacity comparison helper | `CapacityEvaluator` |
| Storage reservation / settlement | `StorageQuotaService` (File Manager) |
| Upload workflows, ownership, providers | Existing File Manager services |
| Authorization | Existing permission / Gate stack |
| Operation capabilities (browse, upload, …) | Existing File Manager capability registry |

A positive entitlement decision **never** substitutes for authorization or operation-capability checks.

## Registering a feature

Modules register during application bootstrap (see `AppServiceProvider`):

```php
$registry->feature('file_manager.storage', 'file_manager', function ($feature) {
    $feature
        ->name('File Manager Storage')
        ->description('…')
        ->entitlement(
            key: 'file_manager.storage_bytes',
            type: EntitlementType::StorageBytes,
            default: 1_099_511_627_776, // 1 TiB
            label: '…',
            description: '…',
            constraints: ['min' => 0],
            scopeSemantics: 'tenant_database',
        );
});
$registry->seal();
```

Registration is validated: duplicate feature keys, conflicting entitlement keys, invalid types/defaults, and empty required metadata are rejected with actionable exceptions. Silent overwrite is not possible.

## Resolving effective values

```php
$bytes = app(EntitlementResolver::class)->limit('file_manager.storage_bytes');
```

Consumers depend only on `EntitlementResolver`. The Phase 1 provider is `DefaultEntitlementProfile`. A future SaaS / subscription provider implements `EntitlementValueProvider` and is bound ahead of the default profile in the container — modules do not change their resolution calls.

Unknown feature or entitlement keys **fail closed** (never treated as enabled or unlimited).

## Initial entitlement

| Key | Type | Default | Meaning |
|-----|------|---------|---------|
| `file_manager.storage_bytes` | `storage_bytes` | `1_099_511_627_776` (1 TiB) | Maximum accounted File Manager-managed storage for the current tenant database |

This is a high **finite** limit, not an unlimited sentinel. Tests may bind a profile with a smaller limit without mutating production defaults.

**Not registered in Phase 1:** `schools.max_count` or any other future-module defaults.

## File Manager storage quota

### Scope of the quota

Includes only objects that pass through File Manager services:

- Ordinary uploads (`FileService::upload`)
- Trusted module-managed uploads (`FileService::registerModuleManaged`)

**Does not include** direct Spatie Media Library uploads (profile avatars, school branding, assignments, book covers, lesson plans, syllabi, custom-field media, etc.). Those paths remain out of scope; a future migration requires separate design and approval.

### Reservation workflow

1. Authorization, capability, ownership, provider, and input validation (existing).
2. **Atomic reservation** via `StorageQuotaService::reserve` (locks `fm_storage_accounts`, checks used + reserved vs limit, inserts pending reservation).
3. Physical write via existing `FileObjectStorage`.
4. Metadata persistence + **settlement** against authoritative `size_bytes` (same transaction path).
5. On failure with **confirmed** cleanup → `release`.
6. On **uncertain** cleanup → `markUncertain` (capacity retained until reconciliation).

Reservations are idempotent by `operation_id`.

### Lifecycle accounting

| State | Accounting |
|-------|------------|
| Active file | Counts toward `used_bytes` |
| Trashed / recoverable | Continues counting |
| Restored | Continues counting once (no double charge) |
| Purge in progress | Continues counting |
| Purge failed / uncertain | Continues counting |
| Confirmed permanent deletion + metadata removal | `releaseUsed` |
| Failed upload with uncertain cleanup | Reservation retained (`uncertain`) |

### Concurrency

Capacity decisions lock the tenant `fm_storage_accounts` row with `SELECT … FOR UPDATE`. This is safe across application processes on MySQL/MariaDB/PostgreSQL. SQLite (test default) serializes writes but does not provide the same multi-connection locking semantics; automated tests cover sequential reservation logic. Production deployments should use a driver that supports row-level locking.

### Persistence

- `fm_storage_accounts` — one row per scope (`tenant` in Phase 1)
- `fm_storage_reservations` — durable reservation identity, state, bytes, optional `file_id`

No per-tenant entitlement configuration tables, subscription tables, or school-capacity coordination tables are introduced.

## Extending for future modules

1. Add an `App\Entitlements\Definitions\{Module}Features` registrar.
2. Call it from the `EntitlementRegistry` singleton in `AppServiceProvider` before `seal()`.
3. Resolve values via `EntitlementResolver` / `CapacityEvaluator` / `FeatureAccess`.
4. Own workflow enforcement inside the module (do not move domain logic into the registry).

## Future SaaS provider

Implement `EntitlementValueProvider`, bind it in the container **before** `DefaultEntitlementProfile` in the resolver’s provider list. Consuming modules keep calling `EntitlementResolver::value()` / `limit()` / `isEnabled()`.

## Operational limitations

- Quota measures **File Manager-managed** storage only, not total application storage.
- Direct Spatie uploads are documented as out of scope.
- No automatic expiry of uncertain reservations; reconciliation must verify physical state via storage-provider abstractions.
- Concurrency guarantees depend on the database driver’s row-level locking support.
