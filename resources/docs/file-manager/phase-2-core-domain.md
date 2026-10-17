# File Manager Phase 2 — Core Domain

## Purpose and scope

Phase 2 establishes the **core File Manager domain**:

- Nested logical folders (not physical directories)
- Code-defined module registration and per-school module configuration
- Stable `File` identities independent of physical storage paths
- Explicit `Attachment` relationships to business owners
- Backend-enforced authorization and ownership boundaries
- Safe integration with Phase 1 storage providers

**Out of scope:** trash/retention/purge, asynchronous storage migration, SVAR/Vuefinder, broad Spatie Media Library migration, antivirus, automatic failover.

## Ownership

Every `Folder` and `File` has an explicit `ownership` value:

| Value | `school_id` |
|-------|-------------|
| `system` | must be `NULL` |
| `school` | must identify the owning school |

Ownership is **never** inferred from a null `school_id` alone.

Invariants enforced in services and model `saving` hooks:

- Parent and child folders share the same ownership boundary
- File and folder share the same ownership boundary when a folder is set
- File and storage provider have compatible ownership boundaries
- School context never lists or mutates another school’s resources (404 / exception)
- System resources require platform context (no active school)

## Module registry vs school configuration

- **`ModuleDefinitionRegistry`** (code): authoritative module codes and maximum capabilities
- **`SchoolModuleConfiguration`** (database): per-school enablement and optional settings

Rules:

- Unknown module codes are rejected
- School configuration cannot elevate capabilities beyond the registry maximum
- Disabling a module blocks relevant **new** operations; it does not delete folders, files, attachments, or the configuration row
- Re-enabling does not silently reset existing settings
- Lazy provisioning: absence of a configuration row means the active module is enabled by default

## Capabilities

Effective capability model:

`Module maximum ∩ Folder configuration ∩ User authorization ∩ Tenant/ownership boundary`

Capability flags: `browse`, `create_folder`, `upload`, `download`, `rename`, `move`, `attach`, `delete`.

Permissions (Laratrust catalogue; distinct from `storage-providers.*`):

| Permission | Purpose |
|------------|---------|
| `file-manager.view` | Browse and download |
| `file-manager.manage` | Create/rename/move/delete folders; upload/delete File Manager-managed files |
| `file-manager.attach` | Attach / detach |
| `file-manager.configure` | Per-school module configuration |

Frontend capability flags are informational only.

## Files and storage

- Stable UUID identity; `storage_key` is opaque (`fm/{uuid}[.ext]`) and hidden from ordinary API responses
- Actual `storage_provider_id` is independent of a folder’s current default provider
- Changing a folder default provider affects **future** uploads only
- Standalone files (null `folder_id`) are first-class
- `managed_by`: `file_manager` \| `module` — ordinary File Manager delete refuses module-managed files

### Upload consistency

1. Validate and authorize
2. Resolve permitted provider; generate storage key
3. Write physical object
4. Persist `File` metadata in a transaction
5. On DB failure after write: compensating delete
6. If cleanup fails: log non-secret diagnostics (`file_manager.upload_orphaned_object` / `file_manager.compensating_delete_failed`); do **not** report success

No silent fallback to another provider. Retrieval always goes through authorized backend resolution of the File ID.

## Attachments

- Owner types from `AttachableOwnerRegistry` only (aliases or allow-listed FQCNs)
- Dual authorization: File accessible in context; owner resolved and school-aligned when applicable
- Duplicate attachments permitted by default
- Detach does not delete the physical object or the File row

## Routes

Under `auth` middleware, prefix `file-manager`:

- Browser UI: `GET /file-manager` (`file-manager.browser`)
- Folders / files JSON API and download stream
- Module configuration: `GET|PATCH /file-manager/modules...`

## Compatibility

- Phase 1 storage provider management unchanged
- Spatie Media Library consumers unchanged (one-way compatibility; no mass migration)
- Settings remain policy-only (`FileManagerSettings`); provider secrets and module configuration stay out of generic Settings values

## Tables

- `fm_folders`, `fm_files`, `fm_attachments`, `fm_school_module_configurations`
- Ownership CHECK constraints on engines that support them (same pattern as `storage_providers`)
