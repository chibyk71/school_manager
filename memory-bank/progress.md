# School Manager — Development Progress

## Completed Development Phases

| Phase | Description | Status |
|-------|-------------|--------|
| Academic Sessions & Terms | Session/term period tracking, operational data boundaries | ✅ Complete |
| Academic Integration (Phase 7) | Integration of academic components | ✅ Complete |
| Address (Phases 1-4) | Core domain, backend capability, legacy removal | ✅ Complete |
| App Shell / Preskool Layout | Frontend layout infrastructure | ✅ Complete |
| DataTable (Phases 5-6) | Frontend datatable + selection/bulk export | ✅ Complete |
| Dynamic Enums (Phases 1-7) | Configurable enum options system | ✅ Complete |
| Feature & Entitlement Registry (Phase 1) | Registry, resolver, default profile, File Manager consumer | ✅ Complete |
| File Manager (Phases 1-3) | Storage providers, core domain, safety/recovery | ✅ Complete |
| File Manager Phase 4 | Integration audit & Spatie assessment | ✅ Complete |
| Permissions (Phases 1-5) | Authorization foundation, API, role management | ✅ Complete |
| Promotion Module | Student promotion workflows | ✅ Complete |
| School Management (Phases 1-2) | Lifecycle, readiness checks, profile settings | ✅ Complete |
| Settings Infrastructure | Settings registry, resolver, definitions | ✅ Complete |
| Student Lifecycle (Phases 4-8) | Enrollment, placement/numbering, consolidation/hardening | ✅ Complete |
| Person & Authentication Foundation | Auth foundation, MFA, OTP, soft-delete, middleware | ⚠️ R13 corrections in progress |

## Important Milestones

- **Phase 8 Consolidation** (PR #11) — Combined student lifecycle finalization, placement service refactor (`closeCurrentPlacement`), obsolete service removal (`StudentEnrollmentService`).
- **Person & Authentication Foundation R13** — Critical auth integration notes documented (scope boundaries, MFA challenge, credential-bearing events, cross-module TODOs).

## Known Issues

1. **Spatie Media Library uploads are out of quota scope** — Profile avatars, school branding, assignment attachments, book covers, lesson plan files, syllabus files remain outside File Manager storage quota. Documented; migration deferred.
2. **File Manager uncertain reservations have no auto-expiry** — Reconciliation must verify physical state via storage-provider abstractions.
3. **SQLite (test default) does not support row-level locking** — Concurrency guarantees depend on database driver. Production deployments should use MySQL/MariaDB/PostgreSQL.
4. **Temporary files (fix.php)** — There's a UTF-16LE temporary script `fix.php` in the repo root (for test inspection). Not part of the application.

## Upcoming Phases / Known Work

1. ✅ **Person & Authentication — R13 corrections** (Active now)
2. ⬜ **HRM Staff identity resolution** — How HRM attaches Staff capacity to existing Person vs. creating new Person
3. ⬜ **Staff listing/search** — UI must not query removed `users.name` / `users.email`
4. ⬜ **Section context activation** — Clean separation from SchoolService workspace selection
5. ⬜ **Cross-school workspace authority** — Explicit catalogue capability
6. ⬜ **Spatie Media Library migration** — Pilot on educational resource collection after ownership mapping design
7. ⬜ **SaaS entitlement provider** — Implement `EntitlementValueProvider` for subscription-based limits

## Note on Phase Naming

The `feature/person-authentication-foundation` branch contains the current work. Earlier phases used different feature-branch naming conventions. Check phase documentation in `docs/` for specific phase boundaries.