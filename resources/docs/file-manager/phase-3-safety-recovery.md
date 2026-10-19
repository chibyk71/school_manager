# File Manager Phase 3 — Safety, Recovery, and Provider Integrity

## Scope

Phase 3 protects existing files and storage-provider integrity:

- Recoverable **Trash** for File Manager-managed files only
- **Restore** to the original folder (or an authorized alternative)
- Asynchronous **purge** after a retention period (default 30 days)
- Attachment preservation while trashed; unresolved attachments block purge
- Provider dependency tracking: refuse deletion and location-affecting reconfiguration while Files depend on a provider
- Disabled providers reject **new** uploads but still serve existing objects for retrieval
- Crash-safe purge protocol with revalidation at job execution time

Out of scope: Spatie mass migration, cross-provider migration, antivirus, broad module integration, full UI redesign.

## Lifecycle ownership

| Managed by | Trash / Restore / Purge via File Manager |
|------------|------------------------------------------|
| `file_manager` | Yes |
| `module` | **No** — ordinary FM operations refuse these files |

Possession of a File ID is not authority over its lifecycle. Enforcement lives in `FileService` / `FileLifecycleService`, not only in the UI.

## Trash

When an authorized actor trashes an FM-managed file:

1. Permission, ownership scope, lifecycle ownership, and state are verified
2. `original_folder_id` and immutable `purge_eligible_at` are stored
3. Physical object and storage identity are retained
4. File is excluded from ordinary browse and download
5. Attachment rows remain intact

Repeated trash is idempotent and does **not** reset retention.

## Retention

- Default: **30 days** (`file_manager.trash_retention_days`, school scope via Settings)
- Eligibility timestamp is computed **at trash time** from the then-current policy
- Policy changes affect **newly** trashed files only
- A file is eligible only when `now() >= purge_eligible_at` (not early)

## Restore

- Only trashed FM-managed files
- Prefers the original folder when it still exists and is accessible in scope
- Otherwise requires an explicit authorized `folder_id` alternative
- Clears trash metadata; file returns to ordinary browse/download
- Stale purge jobs revalidate and will not delete a restored file

## Permanent purge

- Asynchronous: `DiscoverTrashedFilesForPurgeJob` (hourly) → `PurgeTrashedFileJob`
- At execution: recheck trashed state, FM management, eligibility time, **no attachments**, storage identity
- Crash-safe sequence: claim (`purge_state = deleting_physical`) → physical delete → delete DB row
- Missing object after a claimed physical delete is treated as idempotent success
- Provider/network errors surface via failed jobs; secrets are never logged

## Attachments

- Preserved across Trash
- Ordinary download of trashed files is rejected even when attachments exist
- Purge is blocked while any attachment remains
- Detach remains the owning-module / authorized attach path; Phase 3 does not invent generic detach-all

## Provider integrity

- A provider is in use if **any** `fm_files` row (active or trashed) references it
- **Disable**: sets `enabled = false`; blocks new uploads; existing retrieval still works when the provider remains reachable
- **Reconfigure**: location-affecting keys (bucket, root, endpoint, region, prefix, credentials) refused while dependencies exist; cosmetic fields (e.g. name) and enable/disable remain allowed
- **Delete**: refused while dependencies exist; enforced in `StorageProviderManager` with row locking

## Compensating upload cleanup

Unchanged from Phase 2: if physical write succeeds and DB persistence fails, compensating delete is attempted; failures are logged without reporting success.

## Administrator UI

Browser extensions (minimum):

- Trash list separated from active files
- Restore action; message when alternative destination is required
- Provider dependency messages when deletion or location changes are refused

Server authorization remains authoritative regardless of UI state.

## Developer notes

- Columns on `fm_files`: `trashed_at`, `original_folder_id`, `purge_eligible_at`, `purge_state`, `lifecycle_version`
- Services: `FileLifecycleService` (authoritative transitions), `StorageProviderDependencyChecker` (real tracking)
- Jobs under `App\Jobs\FileManager\`
- Schedule: hourly discovery in `routes/console.php`
