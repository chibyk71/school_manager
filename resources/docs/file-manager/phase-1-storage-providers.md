# File Manager Phase 1 — Storage Providers

## Purpose and scope

Phase 1 establishes a **storage provider registry**: configure, validate, and test local and S3-compatible storage destinations with explicit system vs school ownership and encrypted secrets.

**In scope**

- Provider registration and persistence
- System-owned vs school-owned providers
- Local and S3-compatible drivers (Laravel Filesystem / Flysystem)
- Encrypted secrets, safe replacement, redaction in API/UI
- Connection testing against unsaved configuration
- Honest capability reporting
- Minimal dependency-check contract (no invented usage data)
- File Manager **policy** settings via the existing Settings infrastructure
- Administrative UI under Settings → Storage Providers

**Out of scope (later phases)**

- File browser, uploads, downloads, attachments
- Trash, retention, bulk migration between providers
- Migrating all Media Library / filesystem consumers onto the registry

## Ownership

| Ownership | `school_id` | Who may mutate |
|-----------|-------------|----------------|
| `system`  | must be `NULL` | Platform / tenant context (not from a school-scoped session mutating system config) |
| `school`  | required FK   | Actors in that school with `storage-providers.manage` |

Ownership is **explicit**. System is never inferred from a null `school_id` alone.

School A cannot read or change School B’s providers. School context cannot update system-owned provider configuration.

## Secrets

- Stored encrypted (Laravel `Crypt`) in `storage_providers.secrets`
- Never returned in API, Inertia props, or resources after persistence
- UI shows only `secrets_configured: true|false`
- Blank secret field on update **preserves** the existing secret
- Explicit `clear_secrets` flag clears stored secrets

## Connection testing

- Accepts submitted configuration (including unsaved forms)
- Does **not** persist or overwrite provider rows
- Returns a safe success/failure message (no credentials, no raw exception text that may contain secrets)
- Success means the probe operation succeeded, not a guarantee of every future file operation

## Settings vs provider registry

- **Settings** (`file_manager.*`): code-owned policy defaults (e.g. preferred driver hint, allow school management). School scope only; no system Settings scope.
- **Provider registry** (`storage_providers` table): identity, ownership, connection config, encrypted secrets.

Provider credentials are never stored as generic Settings values.

## Dependency check

Phase 1 does not track file operations against provider IDs. The dependency endpoint returns:

- `tracking_implemented: false`
- empty `known_dependencies`
- `safe_to_remove: null`
- summary stating tracking is not implemented

Absence of rows is **not** certification that a provider is safe to remove.

## Legacy Advanced Storage

Routes and UI under **Settings → Advanced → Storage** (`advanced.storage` via legacy helpers) remain available. Runtime consumers (Media Library `MEDIA_DISK`, `config/filesystems.php`, backups, etc.) are unchanged in Phase 1. The new registry is additive.

## Permissions

| Permission | Purpose |
|------------|---------|
| `storage-providers.view` | List and view safe provider details |
| `storage-providers.manage` | Create, update, test connections |

## Supported drivers

- **local** — root path optional (defaults to application private storage)
- **s3** — key, secret, region, bucket, optional endpoint/url/path-style

Capability flags (read, write, streaming, file size, temporary/signed URLs) reflect the selected adapter and configuration only.
