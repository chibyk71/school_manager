---
title: File Manager Phase 4 — Administrator Browser
---

# File Manager Phase 4 — Administrator Browser & Integration Readiness

## Scope

Phase 4 delivers a production-ready administrator browser and documents integration readiness. It does **not** migrate Spatie Media Library consumers or redesign ownership/lifecycle.

### In scope

- Navigation context (root, folders, Trash)
- Server-side search and filters within authorized folder/ownership scope
- Folder rename (existing update contract)
- File move (existing move contract)
- Honest upload feedback and duplicate-submission protection
- Metadata and lifecycle presentation (`managed_by`, size, MIME)
- Confirmations for destructive actions
- Accessibility and responsive layout improvements
- Representative consumer audit and Spatie inventory (assessment only)
- User and developer documentation

### Explicit non-goals

- File rename
- Mass Spatie migration or dual-write
- New authorization system or browser library
- Cross-provider migration

## Permissions

| Permission | Effect |
|------------|--------|
| `file-manager.view` | Browse listings within authorized scope |
| `file-manager.manage` | Create/rename/delete folders, upload, trash, restore, move, purge recovery |
| `file-manager.attach` | Attachment operations (existing) |
| `file-manager.configure` | Module configuration (existing) |

Backend authorization remains authoritative. UI capabilities are hints only.

## Search and filters

- Query params: `q` (search term, max 100 chars), `managed_by` (`file_manager` \| `module`)
- Applied only within the current folder and school/system ownership boundary
- Results bounded (service limit 100 by default, max 200)
- Ordinary browse never returns trashed files

## Folder rename

Uses `PATCH file-manager/folders/{id}` → `FolderService::update` with Rename capability.

## File move

Uses `PATCH file-manager/files/{id}/move` → `FileService::move`. Ownership boundaries and folder capabilities are enforced server-side. Module-managed files are not moved via ordinary FM operations.

## Module-managed files

Displayed with a clear **Module-managed / restricted** indicator. Ordinary trash, move, and related FM lifecycle actions are refused by the domain layer.

## Trash and restore

Unchanged from Phase 3: FM-managed only; restore fails closed when the physical object is missing or unverifiable; purge-failure recovery remains available.

## Recovery guidance

| Situation | Guidance |
|-----------|----------|
| Search empty | Clear filters or navigate to another folder |
| Rename/move rejected | Check permissions, capabilities, and ownership boundaries |
| Module-managed restricted | Use the owning module’s workflows |
| Restore fails | Clear purge failure if applicable; ensure the object still exists on the provider |
| Provider dependency | Provider deletion/reconfiguration blocked while files depend on it |
