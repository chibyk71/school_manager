# File Manager Phase 1 — Storage Providers

## Purpose and scope

Phase 1 establishes a **storage provider registry**: configure, validate, and test local and S3-compatible storage destinations with explicit system vs school ownership and encrypted secrets.

**In scope**

- Provider registration and persistence
- System-owned vs school-owned providers with strict context isolation
- Local and S3-compatible drivers (Laravel Filesystem / Flysystem)
- Encrypted secrets, safe replacement, redaction in API/UI
- Connection testing against unsaved configuration (with path/endpoint safety)
- Honest capability reporting
- Minimal dependency-check contract (no invented usage data)
- File Manager **policy** settings via the existing Settings infrastructure
- Administrative UI under Settings → Storage Providers

**Out of scope (later phases)**

- File browser, uploads, downloads, attachments
- Trash, retention, bulk migration between providers
- Migrating all Media Library / filesystem consumers onto the registry

## Ownership and authorization

| Context | Visible providers | May create / update / test |
|---------|-------------------|----------------------------|
| Active school | School-owned providers for **that school only** | School-owned for that school (if policy allows) |
| No active school (platform) | System-owned only | System-owned only |

- Ownership is **explicit** (`system` | `school`). System is never inferred from a null `school_id` alone.
- School users **do not** list, view, or receive configuration for system-owned providers.
- School users **cannot** create or update system-owned providers, even with `storage-providers.manage`.
- Cross-school access is rejected (404) for show/update/dependencies.
- `storage-providers.view` / `storage-providers.manage` are capability gates only; ownership isolation is enforced in the manager and controller.

## Settings policy

| Key | Scope | Behavior |
|-----|-------|----------|
| `file_manager.allow_school_provider_management` | school | When `false`, school-context create/update/connection-test of school-owned providers is rejected (403). Default `true` when no row exists. Enforced via `SettingsResolver`. **Fail-closed:** if resolution throws, management is denied. |
| `file_manager.default_provider_preference` | school | Advisory default driver (`local` \| `s3`) passed to the create UI. Does **not** change Laravel filesystem defaults or Media Library disk. |

Provider credentials are never stored as generic Settings values.

## Secrets

- Stored encrypted (Laravel `Crypt`) in `storage_providers.secrets`
- Never returned in API, Inertia props, or resources after persistence
- UI shows only `secrets_configured: true|false`
- Blank secret field on update **preserves** the existing secret
- Explicit `clear_secrets` flag clears stored secrets

## Connection testing

- Accepts submitted configuration (including unsaved forms)
- Does **not** persist or overwrite provider rows
- **Local roots** must resolve under `storage_path()` (path traversal and paths outside storage are rejected)
- **S3 endpoints / base URLs**:
  - `http` / `https` only; no URL credentials
  - Hostnames and resolved DNS must not target loopback, private, link-local, unique-local, or cloud-metadata addresses
  - Redirects disabled on the probe HTTP client
  - Private / on-prem hosts may be explicitly allowlisted via `filesystems.connection_test_allowed_hosts` / `STORAGE_PROVIDER_TEST_ALLOWED_HOSTS`
  - Limitation: request-time DNS rebinding cannot be fully pinned without a custom HTTP handler; prefer public endpoints or allowlists
- HTTP client timeouts are bounded (connect 5s, total 10s)
- Probe objects are cleaned up on success and failure
- Failures return a generic message; logs record only a static warning (no exception message, config, or secrets)
- Ownership boundary applies: school context cannot run system-ownership connection tests

## Dependency check

Phase 1 does not track file operations against provider IDs. The dependency endpoint returns:

- `tracking_implemented: false`
- empty `known_dependencies`
- `safe_to_remove: null`
- summary stating tracking is not implemented

Absence of rows is **not** certification that a provider is safe to remove. The endpoint is subject to the same visibility rules as show.

## Legacy Advanced Storage

Routes and UI under **Settings → Advanced → Storage** (`advanced.storage` via legacy helpers) remain available. Runtime consumers (Media Library `MEDIA_DISK`, `config/filesystems.php`, backups, etc.) are unchanged in Phase 1. The new registry is additive.

## Permissions

| Permission | Purpose |
|------------|---------|
| `storage-providers.view` | List and view safe provider details **within the current ownership context** |
| `storage-providers.manage` | Create, update, and test connections **within the current ownership context** |

## Supported drivers

- **local** — root path optional; must stay under application storage
- **s3** — key, secret, region, bucket, optional endpoint/url/path-style

Capability flags (read, write, streaming, file size, temporary/signed URLs) reflect the selected adapter and configuration only.
