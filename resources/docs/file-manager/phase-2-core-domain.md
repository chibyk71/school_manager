# File Manager Phase 2 — Core Domain

## Scope

Phase 2 delivers the File Manager core domain on top of Phase 1 storage providers:

- Explicit `Folder` and `File` identities with ownership boundaries
- Explicit `Attachment` relationships to business owners
- Backend-enforced authorization and ownership boundaries
- Module registry (code-defined) and per-school enablement
- Compensating cleanup when upload persistence fails after a physical write
- Minimal functional browser UI

Out of scope: trash/purge, Spatie mass migration, full UI polish, SVAR/Vuefinder.

## Ownership

Every `Folder` and `File` has an explicit `ownership` value:

- `system` — platform-scoped (`school_id` null)
- `school` — school-scoped (`school_id` required)

Rules:

- System resources cannot be managed from a school context
- School resources cannot be managed outside their school context
- Parent and child folders share the same ownership boundary
- File and folder share the same ownership boundary when a folder is set
- File and storage provider have compatible ownership boundaries

## Modules

`ModuleDefinitionRegistry` is the developer-owned source of truth (capability ceilings, labels).
`SchoolModuleConfiguration` stores per-school enablement and settings only.

- Disabling a module blocks relevant **new** operations; it does not delete folders, files, attachments, or the configuration row
- Capability ceilings are enforced on folder create/update and at operation time

## Capabilities

Effective capability for a folder operation:

`Module maximum ∩ Folder configuration ∩ User authorization ∩ Tenant/ownership boundary`

Capability flags: `browse`, `create_folder`, `upload`, `download`, `rename`, `move`, `attach`, `delete`.

Listing files **inside a specific folder** requires that folder's effective `browse` capability. Standalone/root listing (`folder_id` null) does not consult a folder capability.

## Permissions

| Permission | Purpose |
|------------|---------|
| `file-manager.view` | Browse / download within scope |
| `file-manager.manage` | Create folders, upload, organize, delete FM-managed content |
| `file-manager.attach` | Attach / detach |
| `file-manager.configure` | Module enablement / configuration |

Distinct from `storage-providers.*`.

## Lifecycle ownership

- Ordinary File Manager uploads are always `managed_by = file_manager`. Clients cannot set `managed_by`.
- Trusted path `FileService::registerModuleManaged()` creates module-owned files (not exposed on the ordinary upload HTTP endpoint).
- Ordinary File Manager delete refuses module-managed files.

## Upload consistency

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
- School-boundary enforcement is fail-closed for every allow-listed owner type (School, Student, Guardian, Staff, Profile via role links, User via `school_users` / profile links). When membership cannot be verified, attach is denied.
- Phase 2 does **not** invoke per-model Laravel policies on the owner (e.g. `StudentPolicy::view`) beyond File Manager permissions (`file-manager.attach`) and the school-boundary rules above. Record-level authorization on the business owner remains the calling module's responsibility when attaching from domain workflows.
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
