# School Manager — Project Brief

## Purpose

School Manager is a **school administration platform** built with Laravel and Vue. It brings day-to-day academic and operational workflows into one application, with a domain-oriented approach to school records, permissions, lifecycle management, and configurable platform capabilities.

## Business Model

SaaS (Software-as-a-Service) multi-tenant platform. Schools (tenants) share a database instance with tenant-level scoping via `school_id`. Future entitlement providers (subscription/SaaS) can replace the current default profile without changing consuming modules.

## Core Requirements

1. **Multi-tenant school management** — Each school is a tenant, scoped by `BelongsToSchool` trait + `SchoolScope` global scope. School is also the Laratrust Team for role/permission authorization pivots.

2. **Academic administration** — Sections, class levels, subjects, academic sessions/terms, timetables, lesson plans, assignments, attendance, examinations, score entry, and report cards.

3. **People management** — Admissions, students, guardians, staff (HRM domain). Person identity (Profile) is independently creatable, belongs to the tenant boundary (not a School), and may hold multiple role capacities. Zero or one Authentication Account per Person.

4. **Finance** — Fees, payments, concessions, installments, expenses, transactions, and financial reports.

5. **Operations** — Notices, events, feedback, leave management, payroll, transport, hostel management, library/book workflows.

6. **File Manager** — Managed file/folder workflows with school ownership, lifecycle operations (trash/restore/purge), storage providers, and quota enforcement via the Entitlement Registry.

7. **Platform foundations** — Roles/permissions (Laratrust), settings infrastructure (`SettingsRegistry`), activity logging (Spatie), media handling (Spatie Media Library), notifications, feature/entitlement registration.

## Major Functional Areas

| Area | Implementation Status |
|------|----------------------|
| School Management (lifecycle: Draft → Ready → Active → Suspended + Archive) | Implemented (Phases 1-2) |
| Person & Authentication Foundation | Active (R13 corrections in progress) |
| Academic (sessions, terms, classes, subjects, timetables) | Implemented (multiple phases) |
| Student Lifecycle (enrollment → placement → promotion) | Implemented (Phases 4-8) |
| Address Management | Implemented |
| File Manager (storage, lifecycle, quota) | Implemented (Phases 1-4) |
| Feature & Entitlement Registry | Phase 1 (File Manager, School Management consumers) |
| Permissions & Authorization | Implemented (Phase 4-5) |
| Settings Infrastructure | Implemented |
| Finance | Partial (routes, controllers exist) |
| HRM / Staff / Payroll | Partial |
| Transport, Hostel, Library | Partial |
| Dynamic Enums | Implemented (Phase 1-7) |
| DataTable Infrastructure | Implemented (Phases 5-6) |