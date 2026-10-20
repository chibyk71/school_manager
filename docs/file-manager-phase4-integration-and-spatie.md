# File Manager Phase 4 — Integration Audit & Spatie Assessment

**Assessment only** for Spatie: no migration, dual-write, or legacy behavior changes in this phase.

## 1. Registration and attachment contracts (unchanged)

- Ordinary uploads → `FileService::upload` → always `managed_by = file_manager`
- Module content → `FileService::registerModuleManaged` (server-side only; never client-controlled)
- Attachments → `AttachmentService` + `AttachableOwnerRegistry` allow-list
- Calling module retains record-level authorization

## 2. Representative integration audit

### 2.1 Profile / avatar

| Item | Finding |
|------|---------|
| Model / trait | `App\Models\Profile` + `App\Traits\HasAvatar` (`InteractsWithMedia`) |
| Behavior | Spatie collection `avatar`; conversions thumb/small/medium; gender fallbacks |
| FM contract | **Does not** use File Manager identity/lifecycle |
| Classification | **Compatible legacy** — no Phase 4 blocker |
| Fix | None in Phase 4 |
| Deferred | Optional future migration to FM module-managed + attachment |

### 2.2 School logos / branding

| Item | Finding |
|------|---------|
| Entry points | `SchoolController` uses `$school->addMediaFromRequest(...)` |
| Behavior | Spatie Media Library on `School` |
| FM contract | **Does not** use File Manager |
| Classification | **Compatible legacy** — no Phase 4 blocker |
| Fix | None |
| Deferred | Future branding media migration plan |

### 2.3 Educational resource (Lesson plans)

| Item | Finding |
|------|---------|
| Controllers | `LessonPlanController`, `LessonPlanDetailController` |
| Collections | `lesson_plan_files`, `lesson_plan_detail_files` |
| Behavior | `$model->addMedia($file)->toMediaCollection(...)` |
| FM contract | **Does not** use File Manager |
| Classification | **Compatible legacy** — no Phase 4 blocker |
| Fix | None |
| Deferred | Prefer as an early migration pilot after ownership mapping is designed |

### 2.4 Direct File Manager callers

| Caller pattern | Classification |
|----------------|----------------|
| `FileService::upload` / browser store | In-contract FM-managed |
| `FileService::registerModuleManaged` | In-contract module path |
| `AttachmentService` | Allow-listed owners only |
| Controllers under `FileManager\` | In-contract |

No demonstrated blocker requiring code change in Phase 4 beyond browser UX and documentation.

## 3. Spatie Media Library inventory (repository evidence)

| Area | Collections / notes |
|------|---------------------|
| Profile avatars | `avatar` via `HasAvatar` |
| School media | logos / branding via `SchoolController` |
| Assignments | `assignments` |
| Assignment submissions | `submissions` |
| Book lists | `book_covers` |
| Lesson plans / details | `lesson_plan_files`, `lesson_plan_detail_files` |
| Syllabus / details | `syllabus_files`, `syllabus_detail_files` |

**Unknowns (do not invent):** production media volume, disk layout per environment, conversion dependency depth in production URLs.

### Migration risk factors (future)

1. Stable identity — Spatie media IDs/paths vs FM UUID identity
2. Ownership mapping — school/tenant boundaries must map cleanly
3. URL and conversion compatibility
4. Lifecycle — trash/restore/purge semantics differ from Spatie soft delete
5. Rollout — prefer pilot on one educational collection after dual-read design (not dual-write)

## 4. Deferred / non-goals

- Mass migration, dual-write, storage rewrites
- Changing existing Spatie behavior as a side effect of Phase 4
- File rename product feature

## 5. Storage quota (Entitlement Phase 1)

File Manager storage entitlement (`file_manager.storage_bytes`, default 1 TiB) applies **only** to File Manager-managed objects that pass through `FileService::upload` / `registerModuleManaged`.

Direct Spatie Media Library upload paths listed above are **not** covered by the quota. No dual-write or migration was performed in Entitlement Phase 1. See `docs/entitlements/feature-entitlement-registry-phase-1.md`.
